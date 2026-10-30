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

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Cluster/{id}/consolidateManually',
    methods: [
        'POST',
    ],
    summary: 'Consolidate cluster items manually',
    description: 'Builds the cluster\'s golden record from values selected by the user. If no golden record exists yet, one is resolved from the sources or created from the provided attributes. Values of the fields listed in suggestedFields are rendered by the consolidation script. Relationship data from all source records is merged into the golden record. The cluster gets the ruleBased consolidation method when all values of `attributes.input` are taken from the consolidation script, manual when no suggested fields are given, otherwise hybrid. Returns the resulting golden record\'s field values.',
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
                    'required'   => [
                        'sourceIds',
                        'attributes',
                    ],
                    'properties' => [
                        'sourceIds'       => [
                            'type'        => 'array',
                            'description' => 'Entity IDs of the ClusterItems to consolidate into the golden record.',
                            'minItems'    => 1,
                            'items'       => [
                                'type' => 'string',
                            ],
                        ],
                        'attributes'      => [
                            'type'        => 'object',
                            'description' => 'Field values and relationship data to apply to the golden record.',
                            'required'    => [
                                'input',
                                'relationshipData',
                            ],
                            'properties'  => [
                                'input'            => [
                                    'type'        => 'object',
                                    'description' => 'Field values of the golden record.',
                                ],
                                'relationshipData' => [
                                    'type'        => 'object',
                                    'description' => 'Has-many link mutations keyed by link name.',
                                ],
                            ],
                        ],
                        'suggestedFields' => [
                            'type'        => 'array',
                            'description' => 'Fields of the golden record whose values are taken from the consolidation script instead of `attributes.input`.',
                            'items'       => [
                                'type' => 'string',
                            ],
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
            'description' => 'sourceIds, attributes or suggestedFields is missing or invalid; the cluster was not found; or the consolidation script failed or returned no values while suggestedFields is given.',
        ],
        403 => [
            'description' => 'Current user does not have create access on the cluster\'s masterEntity.',
        ],
    ],
)]
class ConsolidateManuallyHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id = (string)$request->getAttribute('id');
        $data = $this->getRequestBody($request);

        $entity = $this->getRecordService('Cluster')->mergeItems($id, $data->sourceIds, $data->attributes, $data->suggestedFields ?? []);

        return new JsonResponse((array) $entity->getValueMap());
    }
}
