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

namespace Atro\Handlers\DataPrivacy;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/DataPrivacy/action/erase',
    methods: ['POST'],
    summary: 'Erase personal data',
    description: 'Erases the specified fields of a record for data privacy compliance.',
    tag: 'DataPrivacy',
    requestBody: [
        'required' => true,
        'content'  => ['application/json' => ['schema' => [
            'type'       => 'object',
            'required'   => ['entityType', 'id', 'fieldList'],
            'properties' => [
                'entityType' => ['type' => 'string', 'example' => 'Contact'],
                'id'         => ['type' => 'string'],
                'fieldList'  => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ]]],
    ],
    responses: [
        200 => ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
    ],
)]
class DataPrivacyEraseHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->getAcl()->get('dataPrivacyPermission') === 'no') {
            throw new Forbidden();
        }

        $data = $this->getRequestBody($request);

        if (empty($data->entityType) || empty($data->id) || empty($data->fieldList) || !is_array($data->fieldList)) {
            throw new BadRequest();
        }

        $result = $this->getServiceFactory()->create('DataPrivacy')->erase($data->entityType, $data->id, $data->fieldList);

        return new JsonResponse(['true' => $result]);
    }
}
