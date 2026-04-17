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
    path: '/upsert',
    methods: [
        'POST',
    ],
    summary: 'Bulk upsert entities',
    description: 'Creates or updates multiple records in a single request. For each item the system looks up an existing record by `id`, unique fields, or unique indexes. If found — updates it, otherwise creates a new one.',
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
            'description' => 'One result object per input item.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'required'   => [
                                'status',
                                'stored',
                            ],
                            'properties' => [
                                'status'  => [
                                    'type' => 'string',
                                    'enum' => [
                                        'Created',
                                        'Updated',
                                        'NotModified',
                                        'Failed',
                                    ],
                                ],
                                'stored'  => [
                                    'type'        => 'boolean',
                                    'description' => 'Whether the record was persisted to the database.',
                                ],
                                'entity'  => [
                                    'type'        => 'object',
                                    'description' => 'Full entity data after create or update. Present on Created and Updated statuses.',
                                ],
                                'message' => [
                                    'type'        => 'string',
                                    'description' => 'Error description. Present on Failed status.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
)]
class UpsertHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $body = (string) $request->getBody();
        $data = $body !== '' ? json_decode($body) : [];
        if (!is_array($data)) {
            $data = [];
        }

        return new JsonResponse($this->getServiceFactory()->create('MassActions')->upsert($data));
    }
}
