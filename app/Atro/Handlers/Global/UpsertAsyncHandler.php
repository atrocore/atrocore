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

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/upsertAsync',
    methods: [
        'POST',
    ],
    summary: 'Bulk upsert entities as a background job',
    description: 'Schedules a bulk upsert as a background job and returns immediately with the job ID. The actual create/update logic is identical to `POST /upsert` but runs asynchronously via the job queue.',
    tag: 'Global',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'required'   => [
                            'entity',
                            'payload',
                        ],
                        'properties' => [
                            'entity'  => [
                                'type'        => 'string',
                                'description' => 'Entity name (e.g. "Product")',
                                'example'     => 'Product',
                            ],
                            'payload' => [
                                'type'        => 'object',
                                'description' => 'Field values to create or update. Provide `id` to target a specific record, or include unique field values for automatic lookup.',
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
    ],
)]
class UpsertAsyncHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $body = (string) $request->getBody();
        $data = $body !== '' ? json_decode($body, true) : [];
        if (!is_array($data)) {
            $data = [];
        }

        return new JsonResponse($this->getServiceFactory()->create('MassActions')->upsertViaJob($data));
    }
}
