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

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Core\Routing\EntityType;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/{entityName}/action/listKanban',
    methods: [
        'GET',
    ],
    summary: 'Returns records grouped by status for kanban view',
    description: 'Returns records of the specified entity grouped by their status field values, for use in the kanban board view.',
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
            'name'     => 'where',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'anyOf' => [
                    ['type' => 'array'],
                    ['type' => 'object'],
                    ['type' => 'string'],
                ],
            ],
        ],
        [
            'name'     => 'offset',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type'    => 'integer',
                'example' => 0,
            ],
        ],
        [
            'name'     => 'maxSize',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type'    => 'integer',
                'example' => 50,
            ],
        ],
        [
            'name'     => 'sortBy',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type'    => 'string',
                'example' => 'name',
            ],
        ],
        [
            'name'     => 'asc',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'anyOf'   => [
                    ['type' => 'boolean'],
                    ['type' => 'string'],
                ],
                'example' => true,
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Records grouped by status',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'total'          => ['type' => 'integer'],
                            'list'           => [
                                'type'  => 'array',
                                'items' => ['type' => 'object'],
                            ],
                            'additionalData' => [
                                'type'        => 'object',
                                'description' => 'Contains groupList — records split by status column',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
)]
#[EntityType(types: ['Base', 'Hierarchy'])]
class ListKanbanHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);

        if (!$this->getAcl()->check($entityName, 'read')) {
            throw new Forbidden();
        }

        $params = $this->buildListParams($request);
        $result = $this->getRecordService($entityName)->getListKanban($params);

        return new JsonResponse([
            'total'          => $result->total,
            'list'           => $result->collection->getValueMapList(),
            'additionalData' => $result->additionalData,
        ]);
    }
}