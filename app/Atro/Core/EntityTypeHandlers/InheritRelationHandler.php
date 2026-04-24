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

use Atro\Core\Http\Response\BoolResponse;
use Atro\Handlers\AbstractHandler;
use Atro\Core\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Atro\Core\Routing\EntityType;

#[Route(
    path: '/{entityName}/{id}/inheritRelation',
    methods: [
        'POST',
    ],
    summary: 'Inherit a relation record from parent',
    description: 'Copies additional field values for a specific relation record from the parent hierarchy record into the same relation record on the current record.',
    tag: '{entityName}',
    parameters: [
        [
            'name'        => 'entityName',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Hierarchy entity name (e.g. "Category")',
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'ID of the hierarchy record to inherit the relation into',
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
                    'required'   => ['relationName', 'relationId'],
                    'properties' => [
                        'relationName' => [
                            'type'        => 'string',
                            'description' => 'Name of the relation link on the hierarchy entity (e.g. "attributes")',
                        ],
                        'relationId'   => [
                            'type'        => 'string',
                            'description' => 'ID of the related record to inherit additional field values for',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Whether the relation record was updated with inherited field values',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'Bad request — the specified relation link does not exist on the entity',
        ],
        403 => [
            'description' => 'Forbidden — the current user does not have edit access to this entity type',
        ],
        404 => [
            'description' => 'Not found — the hierarchy record or the relation record does not exist',
        ],
    ],
)]
#[EntityType(types: ['Hierarchy'])]
class InheritRelationHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $id         = (string) $request->getAttribute('id');
        $data       = $this->getRequestBody($request);

        $result = $this->getRecordService($entityName)->inheritRelation($id, (string) $data->relationName, (string) $data->relationId);

        return new BoolResponse($result);
    }
}
