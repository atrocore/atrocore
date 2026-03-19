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
use Atro\Handlers\AbstractHandler;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Atro\Core\Routing\EntityType;

#[Route(
    path: '/{entityName}/action/Tree',
    methods: ['GET'],
    summary: 'Get hierarchy tree',
    description: 'Returns tree-structured data for a hierarchy entity. Supports node-based navigation and selected node expansion.',
    tag: '{entityName}',
    parameters: [
        ['name' => 'entityName', 'in' => 'path',  'required' => true,  'schema' => ['type' => 'string']],
        ['name' => 'node',       'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'], 'description' => 'Parent node ID for lazy loading'],
        ['name' => 'selectedId', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'], 'description' => 'Expand tree to reveal this record'],
        ['name' => 'link',       'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ['name' => 'scope',      'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ['name' => 'offset',     'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'example' => 0]],
        ['name' => 'maxSize',    'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'example' => 20]],
        ['name' => 'sortBy',     'in' => 'query', 'required' => false, 'schema' => ['type' => 'string',  'example' => 'name']],
        ['name' => 'asc',        'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean', 'example' => true]],
        ['name' => 'isTreePanel','in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean']],
        ['name' => 'where',      'in' => 'query', 'required' => false, 'schema' => ['anyOf' => [['type' => 'array'], ['type' => 'object'], ['type' => 'string']]]],
        ['name' => 'foreignWhere','in' => 'query','required' => false, 'schema' => ['anyOf' => [['type' => 'array'], ['type' => 'object'], ['type' => 'string']]]],
    ],
    responses: [
        200 => ['description' => 'Tree nodes collection', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['total' => ['type' => 'integer'], 'list' => ['type' => 'array', 'items' => ['type' => 'object']]]]]]],
    ],
)]
#[EntityType(types: ['Base', 'Hierarchy', 'Relation', 'ReferenceData'], excludeEntities: ['UserProfile'])]
class TreeHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $qp         = $request->getQueryParams();

        if (!$this->getAcl()->check($entityName, 'read')) {
            throw new Forbidden();
        }

        $service = $this->getRecordService($entityName);

        // If entity supports hierarchy tree navigation
        if (method_exists($service, 'isHierarchy') && $service->isHierarchy()) {
            if (empty($qp['node']) && !empty($qp['selectedId'])) {
                $sortParams = [
                    'asc'    => ($qp['asc'] ?? 'true') === 'true',
                    'sortBy' => $qp['sortBy'] ?? null,
                ];
                $result = $service->getTreeDataForSelectedNode((string) $qp['selectedId'], $sortParams);
                return new JsonResponse($result);
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

            $result = $service->getChildren((string) ($qp['node'] ?? ''), $params);
            return new JsonResponse($result);
        }

        // Fallback to generic tree (non-hierarchy entity)
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

        $result = $service->getTreeItems(
            (string) ($qp['link'] ?? ''),
            (string) ($qp['scope'] ?? ''),
            $params
        );

        return new JsonResponse($result);
    }
}
