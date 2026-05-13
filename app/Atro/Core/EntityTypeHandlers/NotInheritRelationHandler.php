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
    path: '/{entityName}/{id}/notInheritRelation',
    methods: [
        'POST',
    ],
    summary: 'Remove an inherited relation record from a hierarchy child',
    description: 'Unlinks a relation record that was inherited from a parent hierarchy record, breaking the inheritance for this specific child.',
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
            'description' => 'ID of the hierarchy record to remove the inherited relation from',
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
                            'description' => 'Name of the relation link on the hierarchy entity (e.g. "files")',
                        ],
                        'relationId'   => [
                            'type'        => 'string',
                            'description' => 'ID of the related record to unlink',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Whether the relation record was successfully unlinked',
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
class NotInheritRelationHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $id         = (string) $request->getAttribute('id');
        $data       = $this->getRequestBody($request);

        $result = $this->getRecordService($entityName)->notInheritRelation($id, (string) $data->relationName, (string) $data->relationId);

        return new BoolResponse($result);
    }
}
