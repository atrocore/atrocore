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
    path: '/ClusterItem/{id}/move',
    methods: [
        'POST',
    ],
    summary: 'Move a single cluster item to another cluster',
    description: 'Move the specified cluster item to a target cluster. The target cluster is identified by selectedRecords[0].entityId or by targetClusterId. The item is skipped if its entity type is incompatible with the target cluster masterEntity or if it is rejected in the target cluster.',
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
                        'selectedRecords',
                    ],
                    'properties' => [
                        'targetClusterId' => [
                            'type'        => 'string',
                            'description' => 'ID of the target cluster. Alternative to passing the target via selectedRecords.',
                        ],
                        'selectedRecords' => [
                            'type'        => 'array',
                            'description' => 'Array of selected records from the modal. The first entry\'s entityId is used as the target cluster ID when targetClusterId is absent.',
                            'items'       => [
                                'type'       => 'object',
                                'properties' => [
                                    'entityName' => [
                                        'type'        => 'string',
                                        'description' => 'Entity name of the selected record (always "Cluster").',
                                    ],
                                    'entityId'   => [
                                        'type'        => 'string',
                                        'description' => 'ID of the selected Cluster record used as the move target.',
                                    ],
                                ],
                            ],
                        ],
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
                                'description' => 'Number of cluster items successfully moved (0 or 1).',
                            ],
                            'skipped' => [
                                'type'        => 'integer',
                                'description' => 'Number of cluster items skipped (incompatible entity type, already in target, or rejected in target).',
                            ],
                            'sync'   => [
                                'type'        => 'boolean',
                                'description' => 'Always true — move is executed synchronously.',
                            ],
                            'errors' => [
                                'type'  => 'array',
                                'items' => [
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
            'description' => 'targetClusterId is missing.',
        ],
        403 => [
            'description' => 'Current user does not have edit access on ClusterItem.',
        ],
        404 => [
            'description' => 'Target cluster not found.',
        ],
    ],
)]
class ClusterItemMoveRecordHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('ClusterItem', 'edit')) {
            throw new Forbidden();
        }

        $id   = (string)$request->getAttribute('id');
        $data = $this->getRequestBody($request);

        $targetClusterId = null;
        if (property_exists($data, 'targetClusterId') && !empty($data->targetClusterId)) {
            $targetClusterId = (string)$data->targetClusterId;
        } elseif (property_exists($data, 'selectedRecords') && !empty($data->selectedRecords[0]->entityId)) {
            $targetClusterId = (string)$data->selectedRecords[0]->entityId;
        }

        if (empty($targetClusterId)) {
            throw new BadRequest($this->getLanguage()->translate('targetClusterIdRequired', 'exceptions', 'ClusterItem'));
        }

        return new JsonResponse($this->getRecordService('ClusterItem')->move([
            'ids'             => [$id],
            'targetClusterId' => $targetClusterId,
        ]));
    }
}
