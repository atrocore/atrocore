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
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ClusterItem/{id}/move',
    methods: [
        'PATCH',
    ],
    summary: 'Move a single cluster item to another cluster',
    description: 'Move the specified cluster item to a target cluster identified by targetClusterId. Returns false if the item is already in the target cluster, its entity type is incompatible with the target cluster masterEntity, or it is rejected in the target cluster.',
    tag: 'ClusterItem',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'ID of the ClusterItem to move.',
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
                    'required'   => [
                        'targetClusterId',
                    ],
                    'properties' => [
                        'targetClusterId' => [
                            'type'        => 'string',
                            'description' => 'ID of the target Cluster to move the item into.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'true if the item was moved, false if it was skipped.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'targetClusterId is missing.',
        ],
        403 => [
            'description' => 'Current user does not have edit access on ClusterItem.',
        ],
        404 => [
            'description' => 'ClusterItem or target cluster not found.',
        ],
    ],
)]
class ClusterItemMoveHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id   = (string)$request->getAttribute('id');
        $data = $this->getRequestBody($request);

        $targetClusterId = null;
        if (property_exists($data, 'targetClusterId') && !empty($data->targetClusterId)) {
            $targetClusterId = (string)$data->targetClusterId;
        }

        if (empty($targetClusterId)) {
            throw new BadRequest($this->getLanguage()->translate('targetClusterIdRequired', 'exceptions', 'ClusterItem'));
        }

        $recordService = $this->getRecordService('ClusterItem');

        $entity = $recordService->getEntity($id);
        if (empty($entity)) {
            throw new NotFound();
        }

        return new BoolResponse($recordService->moveItem($entity, $targetClusterId));
    }
}
