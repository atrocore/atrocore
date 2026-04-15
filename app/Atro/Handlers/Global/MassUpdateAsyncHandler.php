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

namespace Atro\Handlers\Global;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/entityMassUpdateAsync',
    methods: [
        'PATCH',
    ],
    summary: 'Mass update (asynchronous)',
    description: 'Schedules a mass update as a background job and returns immediately with the job ID. The actual update logic is identical to `PATCH /entityMassUpdate` but runs asynchronously via the job queue.',
    tag: 'Global',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'entityName',
                        'where',
                        'values',
                    ],
                    'properties' => [
                        'entityName' => [
                            'type'    => 'string',
                            'example' => 'Product',
                        ],
                        'where'      => [
                            'type'        => 'array',
                            'description' => 'Filter conditions that define which records to update. Uses the same format as the list endpoint `where` parameter.',
                            'items'       => [
                                'type'       => 'object',
                                'properties' => [
                                    'type'      => [
                                        'type'    => 'string',
                                        'example' => 'equals',
                                    ],
                                    'attribute' => [
                                        'type'    => 'string',
                                        'example' => 'status',
                                    ],
                                    'value'     => [
                                        'example' => 'Draft',
                                    ],
                                ],
                            ],
                        ],
                        'values'     => [
                            'type'        => 'object',
                            'description' => 'Field values to apply to every matched record. The accepted keys depend on the entity specified in `entityName` — use the same field names as in a regular entity update request.',
                            'example'     => ['status' => 'Active', 'assignedUserId' => 'user-uuid'],
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
            'description' => 'entityName, values or where are missing',
        ],
        403 => [
            'description' => 'Access denied',
        ],
    ],
)]
class MassUpdateAsyncHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (empty($data->entityName)) {
            throw new BadRequest('entityName is required');
        }

        if (empty($data->values)) {
            throw new BadRequest('values is required');
        }

        if (!isset($data->where)) {
            throw new BadRequest('where is required');
        }

        $entityName = (string)$data->entityName;

        if (!$this->getAcl()->check($entityName, 'edit')) {
            throw new Forbidden();
        }

        $params = [
            'where'              => json_decode(json_encode($data->where), true),
            'maxCountWithoutJob' => -1,
        ];
        $result = $this->getRecordService($entityName)->massUpdate($data->values, $params);

        return new JsonResponse($result);
    }
}
