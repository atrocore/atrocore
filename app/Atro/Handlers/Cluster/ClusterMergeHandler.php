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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Cluster/merge',
    methods: [
        'POST',
    ],
    summary: 'Merge cluster items into a golden record',
    description: 'Merges the specified source entity records (identified by their entity IDs within the cluster) into the cluster\'s golden record. If no golden record exists yet, one is resolved from the sources or created from the provided attributes. Relationship data from all source records is merged into the golden record. Returns the resulting golden record\'s field values.',
    tag: 'Cluster',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'clusterId',
                        'sourceIds',
                        'attributes',
                    ],
                    'properties' => [
                        'clusterId'  => [
                            'type'        => 'string',
                            'description' => 'ID of the Cluster whose items are being merged.',
                        ],
                        'sourceIds'  => [
                            'type'        => 'array',
                            'description' => 'Entity IDs of the ClusterItems to merge into the golden record.',
                            'items'       => [
                                'type' => 'string',
                            ],
                        ],
                        'attributes' => [
                            'type'        => 'object',
                            'description' => 'Field values and relationship data to apply to the golden record. Must contain an `input` object with field values and a `relationshipData` array for has-many link mutations.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Field values of the resulting golden record.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'clusterId, sourceIds, or attributes is missing or invalid; or the cluster was not found.',
        ],
        403 => [
            'description' => 'Current user does not have create access on the cluster\'s masterEntity.',
        ],
    ],
)]
class ClusterMergeHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (
            empty($data->clusterId)
            || empty($data->sourceIds)
            || !is_array($data->sourceIds)
            || !($data->attributes instanceof \stdClass)
        ) {
            throw new BadRequest();
        }

        $entity = $this->getRecordService('Cluster')->mergeItems($data->clusterId, $data->sourceIds, $data->attributes);

        return new JsonResponse((array) $entity->getValueMap());
    }
}
