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
    path: '/Cluster/action/purge',
    methods: [
        'POST',
    ],
    summary: 'Purge cluster(s)',
    description: 'Delete all cluster items and then delete the cluster(s). Accepts id, idList, where, or no params to purge all.',
    tag: 'Cluster',
    requestBody: [
        'required' => false,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'id'     => [
                            'type' => 'string',
                        ],
                        'idList' => [
                            'type'  => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                        ],
                        'where'  => [
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
    responses: [
        200 => [
            'description' => 'Success',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'count' => ['type' => 'integer'],
                            'sync'  => ['type' => 'boolean'],
                        ],
                    ],
                ],
            ],
        ],
    ],
)]
class PurgeHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('Cluster', 'delete')) {
            throw new Forbidden();
        }

        $data   = $this->getRequestBody($request);
        $params = [];

        if (property_exists($data, 'where')) {
            $params['where'] = json_decode(json_encode($data->where), true);
        }

        if (property_exists($data, 'idList')) {
            $params['ids'] = $data->idList;
        }

        if (empty($params) && !empty($data->id)) {
            $params['ids'] = [(string)$data->id];
        }

        // Purge all if nothing specified
        if (empty($params)) {
            $params['where'] = [];
        }

        $result = $this->getRecordService('Cluster')->purge($params);

        return new JsonResponse($result);
    }
}
