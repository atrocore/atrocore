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

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\EntityType;
use Atro\Core\Routing\Route;
use Atro\Core\Templates\Services\Hierarchy;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/{entityName}/fillFromChildrenAsync',
    methods: [
        'POST',
    ],
    summary: 'Fill the selected records from their children asynchronously',
    description: 'Creates a background job that fills the empty fields, attribute values and relations of each record matching the filter with the values shared by all of its direct children, the same way as filling a single record. Records without children are left unchanged. Not available when multiple parents are enabled for the entity.',
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
                            'description' => 'Filter conditions that define which records to fill. Uses the same format as the list endpoint `where` parameter.',
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
            'description' => 'Reference to the created background job that fills the records matching the filter',
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
            'description' => 'Bad request — the entity is not of type Hierarchy, or neither field value inheritance nor relation inheritance is enabled for it',
        ],
        403 => [
            'description' => 'Forbidden — the current user does not have edit access to the entity',
        ],
    ],
)]
#[EntityType(types: ['Hierarchy'], requiresAbsent: ['multiParents'])]
class FillFromChildrenAsyncHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var Hierarchy $service */
        $service = $this->getRecordService($this->getEntityName($request));

        return new JsonResponse($service->fillFromChildrenViaJob($this->getRequestBody($request)->where));
    }
}
