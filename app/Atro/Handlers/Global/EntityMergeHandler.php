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
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/entityMerge',
    methods: [
        'POST',
    ],
    summary: 'Merge multiple entity records',
    description: 'Merges two or more records of the same entity type into a single target record.

The merge logic is entity-specific — each entity type may implement its own `merge()` service method with custom field resolution, relation re-linking, and cleanup. The `attributes` object defines the final field values for the resulting record.

If `targetId` is provided, the existing record with that ID becomes the merge target and surviving record. If omitted, a new record is created from the merged data.',
    tag: 'Global',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'entityName',
                        'sourceIds',
                        'attributes',
                    ],
                    'properties' => [
                        'entityName' => [
                            'type'        => 'string',
                            'description' => 'Entity type name (e.g. Product, Contact).',
                        ],
                        'sourceIds'  => [
                            'type'        => 'array',
                            'items'       => ['type' => 'string'],
                            'description' => 'IDs of the records to merge.',
                        ],
                        'targetId'   => [
                            'type'        => 'string',
                            'description' => 'ID of the record to merge into. If omitted, a new record is created.',
                        ],
                        'attributes' => [
                            'type'        => 'object',
                            'description' => 'Final field values for the resulting merged record.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'true if merged successfully',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'Missing or invalid required fields',
        ],
        403 => [
            'description' => 'No create access for the given entity type',
        ],
    ],
)]
class EntityMergeHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (empty($data->entityName) || empty($data->sourceIds) || !is_array($data->sourceIds) || !($data->attributes instanceof \stdClass)) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($data->entityName, 'create')) {
            throw new Forbidden();
        }

        $this->getServiceFactory()->create($data->entityName)->merge(
            !empty($data->targetId) ? $data->targetId : null,
            $data->sourceIds,
            $data->attributes
        );

        return new BoolResponse(true);
    }
}
