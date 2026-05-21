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

namespace Atro\Handlers\Cluster;

use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Cluster/entityData',
    methods: [
        'GET',
    ],
    summary: 'Get cluster data for a specific entity record',
    description: 'Returns the cluster a given entity record belongs to, including its state, creation date, and the lists of master and staging records with their confirmation status.',
    tag: 'Cluster',
    parameters: [
        [
            'name'        => 'entityName',
            'in'          => 'query',
            'required'    => true,
            'description' => 'Entity name (e.g. `Product`).',
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'entityId',
            'in'          => 'query',
            'required'    => true,
            'description' => 'Entity record ID.',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Cluster data including master and staging record lists.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'id'             => [
                                'type'        => 'string',
                                'description' => 'Cluster ID.',
                            ],
                            'number'    => [
                                'type'        => 'number',
                                'description' => 'Cluster number.',
                            ],
                            'state'     => [
                                'type'        => 'string',
                                'description' => 'Cluster state (e.g. review, mergedAutomatically).',
                            ],
                            'createdAt' => [
                                'type'        => 'string',
                                'description' => 'ISO-8601 timestamp when the cluster was created.',
                            ],
                            'masterRecords'  => [
                                'type'        => 'array',
                                'description' => 'Items whose entity name is the primary (master) entity.',
                                'items'       => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'id'                     => ['type' => 'string', 'description' => 'Record ID.'],
                                        'name'                   => ['type' => 'string', 'nullable' => true,'description' => 'Record name.'],
                                        'entityName'             => ['type' => 'string', 'description' => 'Entity name.'],
                                        'confirmed'              => ['type' => 'boolean', 'description' => 'Whether the item is confirmed (determined via isClusterItemConfirmed logic).'],
                                        'confirmedAutomatically' => ['type' => 'boolean', 'nullable' => true, 'description' => 'Whether the item was confirmed automatically.'],
                                        'isGoldenRecord'         => ['type' => 'boolean', 'description' => 'Whether this record is the cluster golden record.'],
                                    ],
                                ],
                            ],
                            'stagingRecords' => [
                                'type'        => 'array',
                                'description' => 'Items whose entity name is a staging (derived) entity.',
                                'items'       => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'id'                     => ['type' => 'string', 'description' => 'Record ID.'],
                                        'name'                   => ['type' => 'string', 'nullable' => true,'description' => 'Record name.'],
                                        'entityName'             => ['type' => 'string', 'description' => 'Entity name.'],
                                        'confirmed'              => ['type' => 'boolean', 'description' => 'Whether the item is confirmed (determined via isClusterItemConfirmed logic).'],
                                        'confirmedAutomatically' => ['type' => 'boolean', 'nullable' => true, 'description' => 'Whether the item was confirmed automatically.'],
                                        'isGoldenRecord'         => ['type' => 'boolean', 'description' => 'Whether this record is the cluster golden record.'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        404 => [
            'description' => 'Record is not part of any cluster.',
        ],
    ],
)]
class ClusterEntityDataHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $qp = $request->getQueryParams();

        $data = $this->getRecordService('Cluster')->getEntityClusterData($qp['entityName'], $qp['entityId']);

        if ($data === null) {
            throw new NotFound();
        }

        return new JsonResponse($data);
    }
}
