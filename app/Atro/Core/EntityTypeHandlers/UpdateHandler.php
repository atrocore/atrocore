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

namespace Atro\Core\EntityTypeHandlers;

use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Atro\Core\Routing\EntityType;
use Atro\Handlers\AbstractHandler;

#[Route(
    path: '/{entityName}/{id}',
    methods: [
        'PATCH',
    ],
    summary: 'Updates a record',
    description: 'Updates an existing record by ID.',
    tag: '{entityName}',
    parameters: [
        [
            'name'     => 'entityName',
            'in'       => 'path',
            'required' => true,
            'schema'   => [
                'type' => 'string',
            ],
        ],
        [
            'name'     => 'id',
            'in'       => 'path',
            'required' => true,
            'schema'   => [
                'type' => 'string',
            ],
        ],
    ],
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'x-entity-patch' => true,
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Entity record',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'x-entity-read' => true,
                    ],
                ],
            ],
        ],
    ],
)]
#[EntityType(types: ['Base', 'Hierarchy', 'Relation', 'ReferenceData'], excludeEntities: ['MatchedRecord', 'AuthToken', 'Bookmark'])]
class UpdateHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $id         = (string) $request->getAttribute('id');

        if (!$this->getAcl()->check($entityName, 'edit')) {
            throw new Forbidden();
        }

        $data    = $this->getRequestBody($request);
        $service = $this->getRecordService($entityName);

        $service->updateEntity($id, $data);

        $entity = $service->prepareEntityById($id);
        if (empty($entity)) {
            throw new Error();
        }

        return new JsonResponse((array) $entity->getValueMap());
    }
}