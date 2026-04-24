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

namespace Atro\Handlers\Selection;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Selection/tree',
    methods: [
        'GET',
    ],
    summary: 'Get selection tree items',
    description: 'Returns a paginated list of tree items for the selection panel, scoped to a specific relation link and entity scope.',
    tag: 'Selection',
    parameters: [
        [
            'name'        => 'link',
            'in'          => 'query',
            'required'    => true,
            'description' => 'Relation link name that defines which records to list (e.g. "categories")',
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'scope',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Entity scope of the linking side — used when `selectedScope` is not provided',
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'selectedScope',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Entity scope of the currently selected records — takes precedence over `scope`',
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'where',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Standard AtroCore where-clause to filter tree items',
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
            'description' => 'Field to sort tree items by',
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'asc',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Sort direction. `true` for ascending (default), `false` for descending.',
            'schema'      => [
                'type' => 'string',
                'enum' => [
                    'true',
                    'false',
                ],
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
            'description' => 'Maximum number of records to return. Defaults to the system recordsPerPageSmall setting.',
            'schema'      => [
                'type'    => 'integer',
                'example' => 20,
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Paginated list of tree items for the selection panel',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'  => 'array',
                        'items' => [
                            'type' => 'object',
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'Bad request — neither `scope` nor `selectedScope` is provided',
        ],
        403 => [
            'description' => 'Forbidden — the current user does not have read access to Selection',
        ],
    ],
)]
class SelectionTreeHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $qp = $request->getQueryParams();

        if (empty($qp['selectedScope']) && empty($qp['scope'])) {
            throw new BadRequest();
        }

        $params = [
            'where'       => $this->prepareWhereQuery($qp['where'] ?? null),
            'asc'         => ($qp['asc'] ?? 'true') === 'true',
            'sortBy'      => $qp['sortBy'] ?? null,
            'isTreePanel' => !empty($qp['isTreePanel']),
            'offset'      => (int) ($qp['offset'] ?? 0),
            'maxSize'     => empty($qp['maxSize']) ? $this->getConfig()->get('recordsPerPageSmall', 20) : (int) $qp['maxSize'],
        ];

        $scope  = (string) ($qp['selectedScope'] ?? $qp['scope']);
        $result = $this->getRecordService('Selection')->getTreeItems((string) $qp['link'], $scope, $params);

        return new JsonResponse($result);
    }
}
