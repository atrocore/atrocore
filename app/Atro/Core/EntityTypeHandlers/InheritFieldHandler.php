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
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Atro\Core\Routing\EntityType;

#[Route(
    path: '/{entityName}/{id}/inheritField',
    methods: [
        'POST',
    ],
    summary: 'Inherit a single field from parent',
    description: 'Pulls the value of a specific field from the parent record into the specified record.',
    tag: '{entityName}',
    operationId: 'inherit{entityName}Field',
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
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'ID of the child record to inherit the field into',
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
                    'required'   => ['field'],
                    'properties' => [
                        'field' => [
                            'type'        => 'string',
                            'description' => 'Name of the field to inherit from the parent record',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Whether the field was inherited',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        403 => [
            'description' => 'Forbidden — the current user does not have edit access to this entity type',
        ],
        404 => [
            'description' => 'Not found — no record exists with the given ID',
        ],
    ],
)]
#[EntityType(types: ['Hierarchy'])]
class InheritFieldHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $id         = $request->getAttribute('id');
        $data       = $this->getRequestBody($request);
        $result     = $this->getRecordService($entityName)->inheritField($data->field, $id);

        return new BoolResponse($result);
    }
}
