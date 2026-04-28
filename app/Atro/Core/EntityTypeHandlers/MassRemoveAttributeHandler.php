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

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Atro\Core\Routing\EntityType;
use Atro\Handlers\AbstractHandler;

#[Route(
    path: '/{entityName}/massRemoveAttributeAsync',
    methods: [
        'POST',
    ],
    summary: 'Mass-remove attributes from records (async)',
    description: 'Schedules a background job that removes the specified attributes from multiple records of the given entity. Always returns a job ID — the operation is never executed synchronously.',
    tag: '{entityName}',
    parameters: [
        [
            'name'        => 'entityName',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Entity name whose records will have attributes removed (e.g. "Product")',
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
                        'attributeWhere',
                        'recordWhere',
                    ],
                    'properties' => [
                        'attributeWhere' => [
                            'type'        => 'array',
                            'description' => 'Filter conditions used to select attributes to remove',
                            'items'       => [
                                'type' => 'object',
                            ],
                        ],
                        'recordWhere'    => [
                            'type'        => 'array',
                            'description' => 'Filter conditions used to select records to process',
                            'items'       => [
                                'type' => 'object',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'ID of the created background job',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'string',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'Bad request — attributeWhere or recordWhere is missing',
        ],
        403 => [
            'description' => 'Forbidden — the current user does not have update access to this entity type',
        ],
    ],
)]
#[EntityType(types: ['Base', 'Hierarchy', 'Relation'], requires: ['hasAttribute'])]
class MassRemoveAttributeHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);

        if (!$this->getAcl()->check($entityName, 'update')) {
            throw new Forbidden();
        }

        $data       = $this->getRequestBody($request);
        $massData   = (object) ['where' => $data->recordWhere, 'byWhere' => true];
        $params     = $this->buildMassParams($massData);
        $attributes = ['where' => json_decode(json_encode($data->attributeWhere), true)];

        $result = $this->getRecordService($entityName)->massRemoveAttribute($attributes, $params);

        return new JsonResponse($result['jobId']);
    }
}