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

namespace Atro\Handlers\ClusterItem;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ClusterItem/massMove',
    methods: [
        'POST',
    ],
    summary: 'Move cluster items to another cluster',
    description: 'Move one or more cluster items to a target cluster identified by targetClusterId. Items are skipped if they already belong to the target cluster, if their entity type is incompatible with the target cluster masterEntity, or if they are rejected in the target cluster.',
    tag: 'ClusterItem',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'targetClusterId',
                    ],
                    'properties' => [
                        'targetClusterId' => [
                            'type'        => 'string',
                            'description' => 'ID of the target Cluster to move items into.',
                        ],
                        'idList'          => [
                            'type'        => 'array',
                            'description' => 'List of ClusterItem IDs to move.',
                            'items'       => [
                                'type' => 'string',
                            ],
                        ],
                        'where'           => [
                            'type'        => 'array',
                            'description' => 'Filter criteria selecting ClusterItems to move.',
                            'items'       => [
                                'type' => 'object',
                            ],
                        ],
                    ],
                    'anyOf'      => [
                        ['required' => ['idList']],
                        ['required' => ['where']],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Move result',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'count'   => [
                                'type'        => 'integer',
                                'description' => 'Number of cluster items successfully moved.',
                            ],
                            'skipped' => [
                                'type'        => 'integer',
                                'description' => 'Number of cluster items skipped (incompatible entity type, already in target, or rejected in target).',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'targetClusterId is missing, or neither idList nor where was provided.',
        ],
        403 => [
            'description' => 'Current user does not have edit access on ClusterItem.',
        ],
        404 => [
            'description' => 'Target cluster not found.',
        ],
    ],
)]
class ClusterItemMassMoveHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('ClusterItem', 'edit')) {
            throw new Forbidden();
        }

        $data = $this->getRequestBody($request);
        $recordService = $this->getRecordService('ClusterItem');
        $params = [];

        $targetClusterId = null;
        if (property_exists($data, 'targetClusterId') && !empty($data->targetClusterId)) {
            $targetClusterId = (string)$data->targetClusterId;
        }

        if (empty($targetClusterId)) {
            throw new BadRequest($this->getLanguage()->translate('targetClusterIdRequired', 'exceptions', 'ClusterItem'));
        }

        $params['targetClusterId'] = $targetClusterId;

        if (property_exists($data, 'idList')) {
            $params['ids'] = $data->idList;
        }

        if (property_exists($data, 'where')) {
            $params['where'] = json_decode(json_encode($data->where), true);
        }

        if (empty($params['ids']) && empty($params['where'])) {
            throw new BadRequest($this->getLanguage()->translate('idOrIdListOrWhereRequired', 'exceptions', 'ClusterItem'));
        }

        return new JsonResponse($recordService->move($params));
    }
}
