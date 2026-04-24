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
use Atro\Handlers\AbstractHandler;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/entityTree',
    methods: [
        'GET',
    ],
    summary: 'Get hierarchy tree',
    description: 'Returns tree-structured data for a hierarchy entity. Supports node-based lazy loading and selected-node expansion.',
    tag: 'Global',
    parameters: [
        [
            'name'        => 'entityName',
            'in'          => 'query',
            'required'    => true,
            'description' => 'Entity name to load the tree for (e.g. "Category")',
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'node',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Parent node ID for lazy loading — returns direct children of this node',
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'selectedId',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Expand the tree to reveal and highlight this record ID',
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'link',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Relation link name used to scope the tree to a specific relation context',
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'scope',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Entity scope for the relation context — usually the entity name of the linking side',
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'where',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Standard AtroCore where-clause to filter tree nodes',
            'schema'      => [
                'anyOf' => [
                    [
                        'type' => 'array',
                    ],
                    [
                        'type' => 'object',
                    ],
                    [
                        'type' => 'string',
                    ],
                ],
            ],
        ],
        [
            'name'        => 'foreignWhere',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Additional where-clause applied in the relation context defined by `link`',
            'schema'      => [
                'anyOf' => [
                    [
                        'type' => 'array',
                    ],
                    [
                        'type' => 'object',
                    ],
                    [
                        'type' => 'string',
                    ],
                ],
            ],
        ],
        [
            'name'        => 'sortBy',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Field to sort tree nodes by',
            'schema'      => [
                'type'    => 'string',
                'example' => 'name',
            ],
        ],
        [
            'name'        => 'asc',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Sort direction. `true` for ascending (default), `false` for descending.',
            'schema'      => [
                'anyOf'   => [
                    [
                        'type' => 'boolean',
                    ],
                    [
                        'type' => 'string',
                    ],
                ],
                'example' => true,
            ],
        ],
        [
            'name'        => 'isTreePanel',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Set to `true` when the request comes from a tree panel — enables tree-panel-specific filtering',
            'schema'      => [
                'type' => 'boolean',
            ],
        ],
        [
            'name'        => 'offset',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Number of records to skip for pagination',
            'schema'      => [
                'type'    => 'integer',
                'example' => 0,
            ],
        ],
        [
            'name'        => 'maxSize',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Maximum number of records to return per page',
            'schema'      => [
                'type'    => 'integer',
                'example' => 20,
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Tree nodes for the requested level or selected-node expansion',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'total' => [
                                'type'        => 'integer',
                                'description' => 'Total number of matching records or nodes',
                            ],
                            'list'  => [
                                'type'        => 'array',
                                'description' => 'Tree nodes or flat records depending on entity type and query mode',
                                'items'       => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'id'             => [
                                            'type'        => 'string',
                                            'description' => 'Record ID',
                                        ],
                                        'name'           => [
                                            'type'        => 'string',
                                            'description' => 'Localized display name of the record',
                                        ],
                                        'offset'         => [
                                            'type'        => 'integer',
                                            'description' => 'Position of this item within the full result set — useful for virtual scroll and pagination',
                                        ],
                                        'total'          => [
                                            'type'        => 'integer',
                                            'description' => 'Total number of siblings at this level — repeated on each item so the client does not need a separate count request',
                                        ],
                                        'disabled'       => [
                                            'type'        => 'boolean',
                                            'description' => '`true` when the current user cannot read this record — the node is shown but not selectable',
                                        ],
                                        'load_on_demand' => [
                                            'type'        => 'boolean',
                                            'description' => '`true` when the node has children that should be lazy-loaded by requesting this endpoint again with `node` set to this item\'s `id`',
                                        ],
                                        'scope'          => [
                                            'type'        => 'string',
                                            'description' => 'Entity name of this record (e.g. "Category") — may differ from `entityName` for polymorphic trees',
                                        ],
                                        'children'       => [
                                            'type'        => 'array',
                                            'description' => 'Nested child nodes of the same shape — only present for Hierarchy entities when `selectedId` is used to pre-expand the tree',
                                            'items'       => [
                                                'type' => 'object',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'Bad request — `entityName` query parameter is missing',
        ],
        403 => [
            'description' => 'Forbidden — the entity type is not supported in tree view, or the current user does not have read access',
        ],
    ],
)]
class EntityTreeHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $qp         = $request->getQueryParams();
        $entityName = (string) ($qp['entityName'] ?? '');

        if ($entityName === '') {
            throw new BadRequest('entityName is required');
        }

        $method = 'process' . $entityName;
        if (method_exists($this, $method)) {
            return $this->$method($qp);
        }

        $excludedEntities = ['UserProfile', 'Connection'];
        $supportedTypes   = ['Base', 'Hierarchy', 'Relation', 'ReferenceData'];
        $scopeType        = (string) ($this->getMetadata()->get(['scopes', $entityName, 'type']) ?? 'Base');

        if (in_array($entityName, $excludedEntities, true) || !in_array($scopeType, $supportedTypes, true)) {
            throw new Forbidden();
        }

        if (!$this->getAcl()->check($entityName, 'read')) {
            throw new Forbidden();
        }

        $service = $this->getRecordService($entityName);

        if (method_exists($service, 'isHierarchy') && $service->isHierarchy()) {
            if (empty($qp['node']) && !empty($qp['selectedId'])) {
                $sortParams = [
                    'asc'    => ($qp['asc'] ?? 'true') === 'true',
                    'sortBy' => $qp['sortBy'] ?? null,
                ];
                return new JsonResponse($service->getTreeDataForSelectedNode((string) $qp['selectedId'], $sortParams));
            }

            $params = [
                'where'        => $this->prepareWhereQuery($qp['where'] ?? null),
                'foreignWhere' => $this->prepareWhereQuery($qp['foreignWhere'] ?? null),
                'link'         => (string) ($qp['link'] ?? ''),
                'scope'        => (string) ($qp['scope'] ?? ''),
                'asc'          => ($qp['asc'] ?? 'true') === 'true',
                'sortBy'       => $qp['sortBy'] ?? null,
                'isTreePanel'  => !empty($qp['isTreePanel']),
                'offset'       => (int) ($qp['offset'] ?? 0),
                'maxSize'      => !empty($qp['maxSize'])
                    ? (int) $qp['maxSize']
                    : $this->getConfig()->get('recordsPerPageSmall', 20),
            ];

            return new JsonResponse($service->getChildren((string) ($qp['node'] ?? ''), $params));
        }

        $params = [
            'where'        => $this->prepareWhereQuery($qp['where'] ?? null),
            'foreignWhere' => $this->prepareWhereQuery($qp['foreignWhere'] ?? null),
            'asc'          => ($qp['asc'] ?? 'true') === 'true',
            'sortBy'       => $qp['sortBy'] ?? null,
            'isTreePanel'  => !empty($qp['isTreePanel']),
            'offset'       => (int) ($qp['offset'] ?? 0),
            'maxSize'      => !empty($qp['maxSize'])
                ? (int) $qp['maxSize']
                : $this->getConfig()->get('recordsPerPageSmall', 20),
        ];

        return new JsonResponse($service->getTreeItems(
            (string) ($qp['link'] ?? ''),
            (string) ($qp['scope'] ?? ''),
            $params
        ));
    }

    private function processBookmark(array $qp): ResponseInterface
    {
        $scope = (string) ($qp['scope'] ?? '');
        if ($scope === '') {
            throw new BadRequest('scope is required');
        }

        $params = [
            'where'   => $this->prepareWhereQuery($qp['where'] ?? null),
            'asc'     => ($qp['asc'] ?? 'true') === 'true',
            'sortBy'  => $qp['sortBy'] ?? 'name',
            'offset'  => (int) ($qp['offset'] ?? 0),
            'maxSize' => empty($qp['maxSize'])
                ? $this->getConfig()->get('recordsPerPageSmall', 20)
                : (int) $qp['maxSize'],
        ];

        return new JsonResponse($this->getRecordService('Bookmark')->getBookmarkTree($scope, $params));
    }

    private function processLastViewed(array $qp): ResponseInterface
    {
        $scope = (string) ($qp['scope'] ?? '');
        if ($scope === '') {
            throw new BadRequest('scope is required');
        }

        /** @var \Atro\Services\LastViewed $service */
        $service = $this->getServiceFactory()->create('LastViewed');

        return new JsonResponse($service->getLastVisitItemsTreeData($scope, (int) ($qp['offset'] ?? 0)));
    }
}
