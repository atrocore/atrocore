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
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Cluster/purge',
    methods: [
        'DELETE',
    ],
    summary: 'Purge cluster(s)',
    description: 'Deletes all ClusterItems and RejectedClusterItems belonging to the selected clusters, then deletes the clusters themselves. Exactly one of all=true (query), idList, or where (request body) must be provided.',
    tag: 'Cluster',
    parameters: [
        [
            'name'        => 'all',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Set to true to purge all clusters, ignoring body parameters.',
            'schema'      => [
                'type' => 'boolean',
            ],
        ],
    ],
    requestBody: [
        'required' => false,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'        => 'object',
                    'description' => 'Required unless all=true is set in the query. Provide either idList or where.',
                    'properties'  => [
                        'idList' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'string'],
                            'description' => 'List of Cluster IDs to purge.',
                        ],
                        'where'  => [
                            'type'        => 'array',
                            'description' => 'Filter criteria selecting Clusters to purge.',
                        ],
                    ],
                    'oneOf'       => [
                        ['required' => ['idList']],
                        ['required' => ['where']],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Purge result.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'count'  => [
                                'type'        => 'integer',
                                'description' => 'Number of clusters successfully purged.',
                            ],
                            'sync'   => [
                                'type'        => 'boolean',
                                'description' => 'true if executed synchronously, false if dispatched as a background job.',
                            ],
                            'errors' => [
                                'type'        => 'array',
                                'items'       => [
                                    'type' => 'string',
                                ],
                                'description' => 'List of error messages, if any.',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'None of all, idList, or where was provided.',
        ],
        403 => [
            'description' => 'Current user does not have delete access on Cluster.',
        ],
    ],
)]
class ClusterPurgeHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('Cluster', 'delete')) {
            throw new Forbidden();
        }

        $query = $request->getQueryParams();
        $data = $this->getRequestBody($request);
        $params = [];

        if (!empty($query['all']) && $query['all'] !== 'false') {
            $params['where'] = [];
        } elseif (!empty($data->idList) && is_array($data->idList)) {
            $params['ids'] = $data->idList;
        } elseif (isset($data->where) && is_array($data->where)) {
            $params['where'] = $data->where;
        } else {
            throw new BadRequest('One of all, idList, or where is required.');
        }

        return new JsonResponse($this->getRecordService('Cluster')->purge($params));
    }
}
