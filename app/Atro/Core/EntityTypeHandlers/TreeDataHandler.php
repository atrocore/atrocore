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
    path: '/{entityName}/treeData',
    methods: [
        'GET',
    ],
    summary: 'Get tree data',
    description: 'Returns a nested tree structure for a Hierarchy-type entity. '
        . 'Supports two mutually exclusive modes: '
        . "\n\n"
        . '**IDs mode** (`ids` provided): builds the tree by expanding the full ancestor chain '
        . 'for each given ID. Useful for pre-expanding the tree to show already-selected nodes. '
        . 'Ancestor nodes that are not in `ids` are included as disabled (non-selectable) branches. '
        . "\n\n"
        . '**Filter mode** (no `ids`): resolves matching IDs from the given `where`/`foreignWhere` '
        . 'filter and then expands their ancestor chains the same way. '
        . '`link` and `scope` narrow the filter to a specific relation context. '
        . "\n\n"
        . 'Tree nodes are sorted by `sortBy` (default `id`) in ascending or descending order. '
        . 'Each node has `id`, `name`, `scope`, `disabled` and optionally a `children` array of the same shape.',
    tag: '{entityName}',
    skipActionHistory: true,
    parameters: [
        [
            'name'     => 'entityName',
            'in'       => 'path',
            'required' => true,
            'schema'   => ['type' => 'string'],
            'example'  => 'Category',
        ],
        [
            'name'        => 'ids',
            'in'          => 'query',
            'required'    => false,
            'description' => 'IDs mode: list of record IDs to expand in the tree. '
                . 'When provided, `where`, `foreignWhere`, `link` and `scope` are ignored.',
            'schema'      => [
                'type'  => 'array',
                'items' => ['type' => 'string'],
            ],
        ],
        [
            'name'        => 'where',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Filter mode: standard AtroCore where-clause to select the leaf records. Ignored when `ids` is provided.',
            'schema'      => [
                'anyOf' => [
                    ['type' => 'array'],
                    ['type' => 'object'],
                    ['type' => 'string'],
                ],
            ],
        ],
        [
            'name'        => 'foreignWhere',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Additional where-clause applied in the relation context defined by `link`.',
            'schema'      => [
                'anyOf' => [
                    ['type' => 'array'],
                    ['type' => 'object'],
                    ['type' => 'string'],
                ],
            ],
        ],
        [
            'name'        => 'link',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Relation name used to scope the filter (e.g. `products`). Used together with `scope`.',
            'schema'      => ['type' => 'string'],
        ],
        [
            'name'        => 'scope',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Entity scope for the relation context. Usually the entity name of the linking side.',
            'schema'      => ['type' => 'string'],
        ],
        [
            'name'        => 'sortBy',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Field to sort tree nodes by. Defaults to `id`.',
            'schema'      => ['type' => 'string'],
            'example'     => 'name',
        ],
        [
            'name'        => 'asc',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Sort direction. `true` for ascending (default), `false` for descending.',
            'schema'      => ['type' => 'string', 'enum' => ['true', 'false']],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Nested tree structure.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'total' => [
                                'type'        => 'integer',
                                'description' => 'Total number of matched leaf records (before tree expansion).',
                                'example'     => 5,
                            ],
                            'tree'  => [
                                'type'        => 'array',
                                'description' => 'Root-level tree nodes. Each node may contain a `children` array of the same shape.',
                                'items'       => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'id'       => ['type' => 'string', 'description' => 'Record ID.'],
                                        'name'     => ['type' => 'string', 'description' => 'Localized display name.'],
                                        'scope'    => ['type' => 'string', 'description' => 'Entity name (e.g. `Category`).'],
                                        'disabled' => [
                                            'type'        => 'boolean',
                                            'description' => '`true` for ancestor nodes that are not in the matched set and should be non-selectable.',
                                        ],
                                        'children' => [
                                            'type'        => 'array',
                                            'description' => 'Child nodes (same structure, recursively).',
                                            'items'       => ['type' => 'object'],
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
)]
#[EntityType(types: ['Hierarchy'])]
class TreeDataHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $qp         = $request->getQueryParams();

        if (!$this->getAcl()->check($entityName, 'read')) {
            throw new Forbidden();
        }

        if (!empty($qp['ids'])) {
            $params = ['ids' => (array) $qp['ids']];
        } else {
            $params = [
                'where'        => $this->prepareWhereQuery($qp['where'] ?? []),
                'foreignWhere' => $this->prepareWhereQuery($qp['foreignWhere'] ?? []),
                'link'         => (string) ($qp['link'] ?? ''),
                'scope'        => (string) ($qp['scope'] ?? ''),
                'offset'       => 0,
                'maxSize'      => 5000,
                'asc'          =>  ($qp['asc'] ?? 'true') === 'true',
                'sortBy'       => $qp['sortBy'] ?? 'id'
            ];
        }

        $result = $this->getRecordService($entityName)->getTreeData($params);

        return new JsonResponse($result);
    }
}
