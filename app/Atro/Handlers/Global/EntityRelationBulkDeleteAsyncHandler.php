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
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/entityRelationBulkAsync',
    methods: [
        'DELETE',
    ],
    summary: 'Remove relations in bulk (asynchronous)',
    description: 'Schedules bulk relation removal as a background job and returns immediately with the job ID. The actual removal logic is identical to `DELETE /entityRelationBulk` but runs asynchronously via the job queue.',
    tag: 'Global',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'entityName',
                        'link',
                        'where',
                        'foreignWhere',
                    ],
                    'properties' => [
                        'entityName'   => [
                            'type'        => 'string',
                            'description' => 'Entity name (e.g. "Product")',
                        ],
                        'link'         => [
                            'type'        => 'string',
                            'description' => 'Relation link name (e.g. "categories")',
                        ],
                        'where'        => [
                            'type'        => 'array',
                            'items'       => [
                                'type' => 'object',
                            ],
                            'description' => 'Filter conditions for main entity records.',
                        ],
                        'foreignWhere' => [
                            'type'        => 'array',
                            'items'       => [
                                'type' => 'object',
                            ],
                            'description' => 'Filter conditions for foreign entity records.',
                        ],
                        'data'         => [
                            'type'        => 'object',
                            'description' => 'Extra relation attributes used to narrow which relations to remove',
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
            'description' => 'entityName, link, where or foreignWhere are missing',
        ],
        403 => [
            'description' => 'Access denied',
        ],
    ],
)]
class EntityRelationBulkDeleteAsyncHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data       = $this->getRequestBody($request);
        $entityName = (string)($data->entityName ?? '');
        $link       = (string)($data->link ?? '');

        if (empty($entityName) || empty($link)) {
            throw new BadRequest('entityName and link are required');
        }

        if (!isset($data->where)) {
            throw new BadRequest('where is required');
        }

        if (!isset($data->foreignWhere)) {
            throw new BadRequest('foreignWhere is required');
        }

        $result = $this->getServiceFactory()->create('MassActions')->removeRelationViaJob(
            $entityName,
            $link,
            [
                'where'        => json_decode(json_encode($data->where), true),
                'foreignWhere' => json_decode(json_encode($data->foreignWhere), true),
                'relationData' => !empty($data->data) ? json_decode(json_encode($data->data), true) : null,
            ]
        );

        return new JsonResponse($result);
    }
}
