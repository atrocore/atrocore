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
    summary: 'Merge multiple records into the golden record',
    description: 'Merge multiple records into the golden record.',
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
                            'type' => 'string',
                        ],
                        'sourceIds'  => [
                            'type'  => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                        ],
                        'attributes' => [
                            'type' => 'object',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Merged entity record',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                    ],
                ],
            ],
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
