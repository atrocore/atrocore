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
    description: 'Returns tree-structured data for a hierarchy entity. Supports node-based navigation and selected node expansion.',
    tag: 'Global',
    parameters: [
        [
            'name'        => 'entityName',
            'in'          => 'query',
            'required'    => true,
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'node',
            'in'          => 'query',
            'required'    => false,
            'schema'      => [
                'type' => 'string',
            ],
            'description' => 'Parent node ID for lazy loading',
        ],
        [
            'name'        => 'selectedId',
            'in'          => 'query',
            'required'    => false,
            'schema'      => [
                'type' => 'string',
            ],
            'description' => 'Expand tree to reveal this record',
        ],
        [
            'name'     => 'link',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type' => 'string',
            ],
        ],
        [
            'name'     => 'scope',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type' => 'string',
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
                'example' => 20,
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
            'name'     => 'isTreePanel',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type' => 'boolean',
            ],
        ],
        [
            'name'     => 'where',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
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
            'name'     => 'foreignWhere',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
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
    ],
    responses: [
        200 => [
            'description' => 'Tree nodes collection',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'total' => [
                                'type' => 'integer',
                            ],
                            'list'  => [
                                'type'  => 'array',
                                'items' => [
                                    'type' => 'object',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
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
