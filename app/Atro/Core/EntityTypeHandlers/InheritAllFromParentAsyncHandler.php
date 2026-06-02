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
use Atro\Core\Templates\Services\Hierarchy;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/{entityName}/inheritAllFromParentAsync',
    methods: [
        'POST',
    ],
    summary: 'Inherit all fields and links from the corresponding parent record (if one exists) for the record(s) included in the selection. Relevant only if multi-parent parameter is disabled for the entity.',
    description: 'For each entity record(s) that match the filter criteria, retrieves all inherited field values and linked records from the corresponding parent (if one exists) into the record. Only those fields that currently have null values are updated — existing values are not overwritten. Relevant only if multi-parent parameter is disabled for the entity.',
    tag: '{entityName}',
    parameters: [
        [
            'name'        => 'entityName',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Entity name (e.g. "Category")',
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
                            'description' => 'Filter conditions that define which records to inherit into. Uses the same format as the list endpoint `where` parameter.',
                            'items'       => [
                                'type'       => 'object',
                                'properties' => [
                                    'type'      => [
                                        'type'        => 'string',
                                        'description' => 'Filter operator (e.g. "equals", "notEquals", "in", "isNull", "between")',
                                        'example'     => 'equals',
                                    ],
                                    'attribute' => [
                                        'type'        => 'string',
                                        'description' => 'Field name to filter on',
                                        'example'     => 'status',
                                    ],
                                    'value'     => [
                                        'description' => 'Value for match (type depends on the operator and field type)',
                                        'example'     => 'Draft',
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
            'description' => 'Reference to the created background job that asynchronously performs data inheritance for the entity record(s) that match the filter criteria',
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
        400 => [
            'description' => 'Bad request — entity is not Hierarchy type or multi-parent parameter is activated for the given entity type',
        ],
        403 => [
            'description' => 'Forbidden — the current user does not have edit access to the given entity type',
        ],
    ],
)]
#[EntityType(types: ['Hierarchy'])]
class InheritAllFromParentAsyncHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $data = $this->getRequestBody($request);

        if ($this->getMetadata()->get(['scopes', $entityName, 'type'], 'Base') !== 'Hierarchy') {
            throw new BadRequest("The entity type is not a Hierarchy type.");
        }

        if ($this->getMetadata()->get(['scopes', $entityName, 'multiParents'], false)) {
            throw new BadRequest("Multi-parents for the entity are activated.");
        }

        /** @var Hierarchy $service */
        $service = $this->getRecordService($entityName);

        return new JsonResponse($service->inheritAllFromParentViaJob($data->where));
    }
}
