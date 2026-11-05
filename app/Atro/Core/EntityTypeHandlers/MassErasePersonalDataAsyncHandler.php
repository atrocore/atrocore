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

namespace Atro\Core\EntityTypeHandlers;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\EntityType;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/{entityName}/massErasePersonalDataAsync',
    methods: [
        'POST',
    ],
    summary: 'Mass erase personal data (asynchronous)',
    description: 'Schedules a mass erasure of personal data as a background job and returns immediately with the job ID. The actual erasure logic is identical to `POST /{entityName}/massErasePersonalData` but runs asynchronously via the job queue. Available for global administrators and data privacy administrators only.',
    tag: '{entityName}',
    parameters: [
        [
            'name'        => 'entityName',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Entity name that contains personal data',
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
                        'where',
                    ],
                    'properties' => [
                        'where' => [
                            'type'        => 'array',
                            'description' => 'Filter conditions that define which records to erase. Uses the same format as the list endpoint `where` parameter. Pass an empty array to erase all records.',
                            'items'       => [
                                'type'       => 'object',
                                'properties' => [
                                    'type'      => [
                                        'type'    => 'string',
                                        'example' => 'equals',
                                    ],
                                    'attribute' => [
                                        'type'    => 'string',
                                        'example' => 'test',
                                    ],
                                    'value'     => [
                                        'example' => 'test',
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
            'description' => 'Reference to the created background job.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'required'   => [
                            'jobId',
                        ],
                        'properties' => [
                            'jobId' => [
                                'type'        => 'string',
                                'description' => 'ID of the created background job.',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'where is missing',
        ],
        403 => [
            'description' => 'Forbidden — the current user is neither a global administrator nor a data privacy administrator',
        ],
    ],
)]
#[EntityType(types: ['Base', 'Hierarchy'], requires: ['containsPersonalData'])]
class MassErasePersonalDataAsyncHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $data       = $this->getRequestBody($request);

        if (!isset($data->where)) {
            throw new BadRequest('where is required');
        }

        $params = [
            'where'              => json_decode(json_encode($data->where), true),
            'maxCountWithoutJob' => -1,
        ];
        $result = $this->getRecordService($entityName)->massErasePersonalData($params);

        return new JsonResponse($result);
    }
}
