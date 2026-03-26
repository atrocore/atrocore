<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Handlers\Global;

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/MassActions/action/upsert',
    methods: ['POST'],
    summary: 'Bulk Create and Bulk Update',
    description: 'The system will try to find existing entities based on the identifier or unique fields. If an entity is found, it will be updated, otherwise it will be created.',
    tag: 'Global',
    parameters: [
        ['name' => 'Use-Queue', 'in' => 'header', 'required' => false, 'schema' => ['type' => 'boolean', 'example' => false]],
    ],
    requestBody: [
        'required' => true,
        'content'  => ['application/json' => ['schema' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['entity' => ['type' => 'string', 'example' => 'Product'], 'payload' => ['type' => 'object']]]]]],
    ],
    responses: [
        200 => ['description' => 'Upsert results', 'content' => ['application/json' => ['schema' => ['type' => 'array', 'items' => ['type' => 'object']]]]],
    ],
)]
class MassActionsUpsertHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $useQueue = $request->getHeaderLine('Use-Queue');
        $viaJob   = $useQueue === '1' || strtolower($useQueue) === 'true';

        $body = (string) $request->getBody();
        $data = $body !== '' ? json_decode($body, true) : [];
        if (!is_array($data)) {
            $data = [];
        }

        $service = $this->getServiceFactory()->create('MassActions');

        if ($viaJob) {
            return new JsonResponse($service->upsertViaJob($data));
        }

        return new JsonResponse($service->upsert($data));
    }
}
