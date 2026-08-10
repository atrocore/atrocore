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

use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Services\Record;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Atro\Core\Routing\EntityType;
use Atro\Handlers\AbstractHandler;

#[Route(
    path: '/{entityName}/{id}',
    methods: [
        'GET',
    ],
    summary: 'Returns a record',
    description: 'Returns a single record by ID. If the entity has a field marked as code, its value can be passed instead of the ID.',
    tag: '{entityName}',
    parameters: [
        [
            'name'        => 'entityName',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Entity name (e.g. "Product")',
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Record ID, or the value of the entity\'s code field if one is configured',
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'withRelationships',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Comma-separated list of linkMultiple field names to load even if they have noLoad=true (e.g. "products,categories")',
            'schema'      => [
                'type' => 'string',
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
#[EntityType(types: ['Base', 'Hierarchy', 'Archive', 'Relation', 'ReferenceData'], excludeEntities: ['MatchedRecord', 'AuthToken'])]
class ReadHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName        = $this->getEntityName($request);
        $id                = (string)$request->getAttribute('id');
        $withRelationships = $request->getQueryParams()['withRelationships'] ?? null;

        /** @var Record $service */
        $service = $this->getRecordService($entityName);

        $id = $service->resolveIdByCode($id) ?? $id;

        $entity = $service->readEntity($id, $withRelationships);

        if (empty($entity)) {
            throw new NotFound();
        }

        return new JsonResponse((array)$entity->getValueMap());
    }
}