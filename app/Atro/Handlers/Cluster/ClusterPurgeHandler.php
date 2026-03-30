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
    description: 'Deletes all ClusterItems and RejectedClusterItems belonging to the selected clusters, then deletes the clusters themselves. Pass idList or where as query parameters to target specific clusters, or all=true to purge every cluster.',
    tag: 'Cluster',
    parameters: [
        [
            'name'        => 'all',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Set to true to purge all clusters, ignoring idList and where.',
            'schema'      => [
                'type' => 'boolean',
            ],
        ],
        [
            'name'        => 'idList[]',
            'in'          => 'query',
            'required'    => false,
            'description' => 'List of Cluster IDs to purge.',
            'schema'      => [
                'type'  => 'array',
                'items' => [
                    'type' => 'string',
                ],
            ],
        ],
        [
            'name'        => 'where',
            'in'          => 'query',
            'required'    => false,
            'description' => 'Filter criteria selecting Clusters to purge (JSON-encoded array).',
            'schema'      => [
                'type' => 'string',
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

        $query  = $request->getQueryParams();
        $params = [];

        if (!empty($query['all']) && $query['all'] !== 'false') {
            $params['where'] = [];
        } elseif (!empty($query['idList']) && is_array($query['idList'])) {
            $params['ids'] = $query['idList'];
        } elseif (!empty($query['where'])) {
            $params['where'] = json_decode($query['where'], true);
        } else {
            $params['where'] = [];
        }

        return new JsonResponse($this->getRecordService('Cluster')->purge($params));
    }
}
