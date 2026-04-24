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
    path: '/{entityName}/{id}/inheritLink',
    methods: [
        'POST',
    ],
    summary: 'Inherit all linked records from parent for a relation',
    description: 'Copies all linked records for a specific relation link from the parent record into the specified record.',
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
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'ID of the child record to inherit linked records into',
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
                    'required'   => ['link'],
                    'properties' => [
                        'link' => [
                            'type'        => 'string',
                            'description' => 'Name of the relation link to inherit (e.g. "categories")',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Whether any linked records were inherited',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'anyOf' => [
                            [
                                'type' => 'boolean',
                            ],
                            [
                                'type' => 'integer',
                            ],
                        ],
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
class InheritLinkHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $id         = $request->getAttribute('id');
        $data       = $this->getRequestBody($request);
        $result     = $this->getRecordService($entityName)->inheritAllForLink($id, $data->link);

        return new BoolResponse($result);
    }
}
