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
use Atro\Services\DataLineage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/dataLineageTree',
    methods: [
        'GET',
    ],
    summary: 'Get data lineage tree',
    description: 'Returns the records connected with the given record through data pipelines and contributor-master relations. The tree starts from the selected endpoint (the final record downstream) and goes upstream to the data sources.',
    tag: 'Global',
    parameters: [
        [
            'name'        => 'entityName',
            'in'          => 'query',
            'required'    => true,
            'description' => 'Entity name of the current record',
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'id',
            'in'          => 'query',
            'required'    => true,
            'description' => 'ID of the current record',
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'endpointEntityName',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Entity name of the selected endpoint. The first available endpoint is used when it is empty or not reachable from the current record.',
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'endpointId',
            'in'          => 'query',
            'required'    => false,
            'description' => 'ID of the selected endpoint',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Data lineage tree of the selected endpoint',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'currentEndpoint' => [
                                'type'        => 'object',
                                'description' => 'Selected endpoint, the root of the tree',
                                'properties'  => [
                                    'scope' => ['type' => 'string'],
                                    'id'    => ['type' => 'string'],
                                ],
                            ],
                            'endpoints'       => [
                                'type'        => 'array',
                                'description' => 'All endpoints reachable from the current record',
                                'items'       => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'scope' => ['type' => 'string'],
                                        'id'    => ['type' => 'string'],
                                        'name'  => ['type' => 'string', 'nullable' => true],
                                    ],
                                ],
                            ],
                            'records'         => [
                                'type'        => 'array',
                                'description' => 'Records of the tree. Each record refers to the record it passes the data to.',
                                'items'       => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'scope'    => ['type' => 'string'],
                                        'id'       => ['type' => 'string'],
                                        'name'     => ['type' => 'string', 'nullable' => true],
                                        'toScope'  => ['type' => 'string', 'nullable' => true],
                                        'toId'     => ['type' => 'string', 'nullable' => true],
                                        'disabled' => [
                                            'type'        => 'boolean',
                                            'description' => '`true` when the current user cannot read the record',
                                        ],
                                    ],
                                ],
                            ],
                            'links'           => [
                                'type'        => 'array',
                                'description' => 'Relations between the entities of the tree',
                                'items'       => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'type' => ['type' => 'string', 'enum' => ['dataPipeline', 'consolidation']],
                                        'from' => ['type' => 'string'],
                                        'to'   => ['type' => 'string'],
                                        'id'   => [
                                            'type'        => 'string',
                                            'nullable'    => true,
                                            'description' => 'ID of the data pipeline or consolidation configuration',
                                        ],
                                        'number' => [
                                            'type'        => 'integer',
                                            'nullable'    => true,
                                            'description' => 'Number of the data pipeline or consolidation configuration',
                                        ],
                                    ],
                                ],
                            ],
                            'truncated'       => [
                                'type'        => 'boolean',
                                'description' => '`true` when the tree is cut by the maximum number of records',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'Bad request — `entityName` or `id` query parameter is missing',
        ],
        403 => [
            'description' => 'Forbidden — the current user does not have read access to the record',
        ],
        404 => [
            'description' => 'Record not found',
        ],
    ],
)]
class DataLineageTreeHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $qp = $request->getQueryParams();
        $entityName = (string)($qp['entityName'] ?? '');
        $id = (string)($qp['id'] ?? '');

        if ($entityName === '' || $id === '') {
            throw new BadRequest('entityName and id are required');
        }

        if (!$this->getAcl()->check($entityName, 'read')) {
            throw new Forbidden();
        }

        /** @var DataLineage $service */
        $service = $this->getServiceFactory()->create('DataLineage');

        $tree = $service->getTree(
            $entityName,
            $id,
            !empty($qp['endpointEntityName']) ? (string)$qp['endpointEntityName'] : null,
            !empty($qp['endpointId']) ? (string)$qp['endpointId'] : null
        );

        return new JsonResponse($tree->toArray());
    }
}
