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

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/DataPipeline/updateFromSourceAsync',
    methods: [
        'POST',
    ],
    summary: 'Update target records from their source records asynchronously',
    description: 'Creates a background job that updates each target record matching the filter from its source record the same way as updating a single target record.',
    tag: 'DataPipeline',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => ['entityName', 'where'],
                    'properties' => [
                        'entityName' => [
                            'type'        => 'string',
                            'description' => 'Target entity name of the data pipelines (e.g. "Product").',
                        ],
                        'where' => [
                            'type'        => 'array',
                            'description' => 'Filter conditions that define which target entity records to update. Uses the same format as the list endpoint `where` parameter.',
                            'items'       => [
                                'type'       => 'object',
                                'properties' => [
                                    'type'      => [
                                        'type'        => 'string',
                                        'description' => 'Filter operator (e.g. "equals", "notEquals", "in", "isNull", "between")',
                                        'example'     => 'in',
                                    ],
                                    'attribute' => [
                                        'type'        => 'string',
                                        'description' => 'Field name to filter on',
                                        'example'     => 'id',
                                    ],
                                    'value'     => [
                                        'description' => 'Value for match (type depends on the operator and field type)',
                                        'example'     => ['id1', 'id2'],
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
            'description' => 'Reference to the created background job that updates the records matching the filter',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'jobId' => [
                                'type'        => 'string',
                                'description' => 'ID of the created background job',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        403 => [
            'description' => 'Current user does not have edit access on the target entity.',
        ],
        404 => [
            'description' => 'The entity is not a target of any data pipeline.',
        ],
    ],
)]
class UpdateFromSourceAsyncHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        $jobId = $this->getRecordService('DataPipeline')
            ->updateFromSourceViaJob((string)$data->entityName, json_decode(json_encode($data->where), true));

        return new JsonResponse(['jobId' => $jobId]);
    }
}
