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
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Cluster/{id}/consolidate',
    methods: [
        'POST',
    ],
    summary: 'Consolidate selected cluster items',
    description: 'Consolidates the specified cluster items of the cluster in one run: the consolidation script is rendered once for all of them, the golden record is created or updated, and all selected contributor records are linked to it.',
    tag: 'Cluster',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'ID of the Cluster whose items are consolidated.',
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
                            'description' => 'IDs of the ClusterItems to consolidate. All of them must belong to the cluster.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'true if the items were consolidated, false if there was nothing to consolidate or the master record could not be created.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'clusterItemsIds is missing or contains items that do not belong to the cluster.',
        ],
        403 => [
            'description' => 'Current user does not have edit access on the Cluster.',
        ],
        404 => [
            'description' => 'Cluster not found.',
        ],
    ],
)]
class ConsolidateHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id = (string)$request->getAttribute('id');
        $data = $this->getRequestBody($request);

        if (empty($data->clusterItemsIds) || !is_array($data->clusterItemsIds)) {
            throw new BadRequest('clusterItemsIds is required.');
        }

        return new BoolResponse($this->getRecordService('Cluster')->consolidate($id, $data->clusterItemsIds));
    }
}
