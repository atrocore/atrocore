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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/App/action/merge',
    methods: ['POST'],
    summary: 'Merge multiple records into a target entity or a new one',
    description: 'Merge multiple records into a target entity or a new one',
    tag: 'Global',
    responses: [
        200 => ['description' => 'Merged entity data', 'content' => ['application/json' => ['schema' => [
            'type'    => 'object',
            'example' => ['id' => 'some-id', 'name' => 'a name', 'description' => 'a description'],
        ]]]],
        400 => ['description' => 'Invalid input'],
        403 => ['description' => 'Forbidden'],
    ],
)]
class MergeHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (empty($data->scope) || empty($data->sourceIds) || !is_array($data->sourceIds) || !($data->attributes instanceof \stdClass)) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($data->scope, 'create')) {
            throw new Forbidden();
        }

        $entity = $this->getServiceFactory()->create($data->scope)->merge(
            !empty($data->targetId) ? $data->targetId : null,
            $data->sourceIds,
            $data->attributes
        );

        return new JsonResponse((array)$entity->getValueMap());
    }
}
