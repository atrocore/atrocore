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
use Atro\Core\Routing\EntityType;
use Atro\Core\Routing\Route;
use Atro\Core\Templates\Services\Hierarchy;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/{entityName}/{id}/fillFromChildren',
    methods: [
        'POST',
    ],
    summary: 'Fill a record from its children',
    description: 'Fills the empty fields, attribute values and relations of the record with the values shared by all of its direct children. A field or relation is filled only if every direct child has exactly the same value for it and the record itself has no value; existing values are never replaced. Attributes present on every child are added to the record if the user may add attributes. Fields and relations excluded from inheritance are skipped. Not available when multiple parents are enabled for the entity.',
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
            'description' => 'ID of the parent record to fill',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'true if at least one field, attribute or relation was filled, false if the record has no children or nothing could be filled',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'Bad request — the entity is not of type Hierarchy, or neither field value inheritance nor relation inheritance is enabled for it',
        ],
        403 => [
            'description' => 'Forbidden — the current user does not have edit access to the record',
        ],
        404 => [
            'description' => 'Not found — no record exists with the given ID',
        ],
    ],
)]
#[EntityType(types: ['Hierarchy'], requiresAbsent: ['multiParents'])]
class FillFromChildrenHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var Hierarchy $service */
        $service = $this->getRecordService($this->getEntityName($request));

        return new BoolResponse($service->fillFromChildren($request->getAttribute('id')));
    }
}
