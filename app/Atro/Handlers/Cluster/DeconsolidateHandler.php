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
    path: '/Cluster/{id}/deconsolidate',
    methods: [
        'POST',
    ],
    summary: 'Deconsolidate selected cluster items',
    description: 'Unlinks the specified contributor items of the cluster from its golden record. The items stay in the cluster; items that are not consolidated are skipped. At least one contributor item must stay consolidated. If Update Master Automatically is enabled, the golden record is updated from the remaining contributor records.',
    tag: 'Cluster',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'ID of the Cluster whose items are deconsolidated.',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => ['clusterItemsIds'],
                    'properties' => [
                        'clusterItemsIds' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'string'],
                            'description' => 'IDs of the ClusterItems to deconsolidate. All of them must belong to the cluster.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Deconsolidation result.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'count'  => [
                                'type'        => 'integer',
                                'description' => 'Number of cluster items deconsolidated.',
                            ],
                            'sync'   => [
                                'type'        => 'boolean',
                                'description' => 'Always true — deconsolidation is executed synchronously.',
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
            'description' => 'clusterItemsIds is missing, contains items that do not belong to the cluster or the master entity item, or would leave no contributor item consolidated.',
        ],
        403 => [
            'description' => 'Current user does not have edit access on the Cluster.',
        ],
        404 => [
            'description' => 'Cluster not found.',
        ],
    ],
)]
class DeconsolidateHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id = (string)$request->getAttribute('id');
        $data = $this->getRequestBody($request);

        if (empty($data->clusterItemsIds) || !is_array($data->clusterItemsIds)) {
            throw new BadRequest('clusterItemsIds is required.');
        }

        return new JsonResponse(get_object_vars($this->getRecordService('Cluster')->deconsolidate($id, $data->clusterItemsIds)));
    }
}
