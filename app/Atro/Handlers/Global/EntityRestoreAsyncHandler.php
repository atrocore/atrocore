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
    path: '/entityRestoreAsync',
    methods: [
        'POST',
    ],
    summary: 'Restore records (asynchronous)',
    description: 'Schedules a restore as a background job and returns immediately with the job ID. The actual restore logic is identical to `POST /entityRestore` but runs asynchronously via the job queue.',
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
                    ],
                    'properties' => [
                        'entityName' => [
                            'type'    => 'string',
                            'example' => 'Product',
                        ],
                        'where'       => [
                            'type'        => 'array',
                            'description' => 'Filter conditions that define which records to restore. Uses the same format as the list endpoint `where` parameter. Pass an empty array to restore all records.',
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
        403 => [
            'description' => 'Access denied',
        ],
    ],
)]
class EntityRestoreAsyncHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);
        $data->byWhere = true;

        $entityName = (string)$data->entityName;

        $params = $this->buildMassParams($data);
        $params['maxCountWithoutJob'] = -1;

        $result = $this->getRecordService($entityName)->massRestore($params);

        return new JsonResponse($result);
    }
}
