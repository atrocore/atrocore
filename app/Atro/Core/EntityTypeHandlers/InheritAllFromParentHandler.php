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
    path: '/{entityName}/{id}/inheritAllFromParent',
    methods: [
        'POST',
    ],
    summary: 'Inherit all fields and links from parent',
    description: 'Pulls all inheritable field values and linked records from the parent record into the specified record. Only fields that are currently null on the record are updated — already-set values are not overwritten.',
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
            'description' => 'ID of the child record to inherit into',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Whether any fields and links were inherited',
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
class InheritAllFromParentHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $id         = $request->getAttribute('id');

        $result = $this->getRecordService($entityName)->inheritAllFromParent($id);

        return new BoolResponse($result);
    }
}
