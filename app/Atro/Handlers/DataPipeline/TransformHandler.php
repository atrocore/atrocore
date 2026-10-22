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

namespace Atro\Handlers\DataPipeline;

use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/DataPipeline/transform',
    methods: [
        'POST',
    ],
    summary: 'Transform a source record into its target record',
    description: 'Finds the data pipeline for the given pair of source and target entities, renders its transformation script for the given source record and creates or updates the linked target record.',
    tag: 'DataPipeline',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => ['entityName', 'targetEntityName', 'entityId'],
                    'properties' => [
                        'entityName'       => [
                            'type'        => 'string',
                            'description' => 'Source entity name of the data pipeline (e.g. "Product").',
                        ],
                        'targetEntityName' => [
                            'type'        => 'string',
                            'description' => 'Target entity name of the data pipeline.',
                        ],
                        'entityId'   => [
                            'type'        => 'string',
                            'description' => 'ID of the source entity record to transform.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'true when the transformation is executed',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'The transformation script is missing or invalid.',
        ],
        403 => [
            'description' => 'Current user does not have read access on the source record or edit access on the target entity, or the transformation user is not allowed to create or edit the target record.',
        ],
        404 => [
            'description' => 'No data pipeline exists for the pair of entities, or the source record is not found.',
        ],
    ],
)]
class TransformHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        $this->getRecordService('DataPipeline')->transformSourceRecord((string)$data->entityName, (string)$data->targetEntityName, (string)$data->entityId);

        return new BoolResponse(true);
    }
}
