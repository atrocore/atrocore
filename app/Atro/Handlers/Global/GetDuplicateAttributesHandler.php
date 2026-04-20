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
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\EntityType;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/entityDuplicateValues',
    methods: [
        'GET',
    ],
    summary: 'Get pre-filled fields values for duplicating an entity record',
    description: 'Returns the fields values of the given record pre-processed for duplication.
    
**Response object keys:**
- All fields values from the source record.
- `id`, `createdAt` and `modifiedAt` are always removed.
- Fields marked with `duplicateIgnore` in entity scope metadata are excluded.
- Relation virtual fields (`fieldId`, `fieldName`, `fieldIds`, `fieldNames`) are removed.
- `linkMultiple` fields (`fieldIds`, `fieldNames`, `fieldColumns`) are only present when the relation is listed in `duplicatableRelations` entity scope metadata.
- `_duplicatingEntityId` is always present and contains the `ID` of the source entity record.',
    tag: 'Global',
    parameters: [
        [
            'name'        => 'entityName',
            'in'          => 'query',
            'required'    => true,
            'schema'      => [
                'type' => 'string',
            ],
            'example'     => 'Product',
            'description' => 'The record entity name whose fields values will be returned for duplication.',
        ],
        [
            'name'        => 'id',
            'in'          => 'query',
            'required'    => true,
            'schema'      => [
                'type' => 'string',
            ],
            'example'     => '01990e5a-1f55-732b-bccd-8d200b594fc9',
            'description' => 'The ID of the entity record whose fields values will be returned for duplication.',
        ],
    ],
    responses: [
        200 => [
            'description' => 'Object of fields values ready to pre-fill a new duplicate record, with system and ignored fields removed and _duplicatingEntityId appended. The exact set of keys depends on the fields defined for the source entity.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object'
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'The source entity record was not found.',
        ],
        403 => [
            'description' => 'The current user does not have read access to the entity scope.',
        ],
    ],
)]
#[EntityType(types: ['Base', 'Hierarchy', 'Relation'])]
class GetDuplicateAttributesHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $qp = $request->getQueryParams();

        $entityName = $qp['entityName'];
        $id = $qp['id'];

        $entity = $this->getEntityManager()->getEntity($entityName, $id);

        if (empty($entity)) {
            throw new BadRequest();
        }

        $result = $this->getRecordService($entityName)->getDuplicateAttributes($id);

        return new JsonResponse(is_array($result) ? $result : (array)$result);
    }
}