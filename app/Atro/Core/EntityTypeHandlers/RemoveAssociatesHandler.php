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
use Atro\Core\Http\Response\BoolResponse;
use Atro\Handlers\AbstractHandler;
use Atro\Core\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Atro\Core\Routing\EntityType;

#[Route(
    path: '/{entityName}/removeAssociates',
    methods: [
        'POST',
    ],
    summary: 'Remove associations between records',
    description: 'Removes all or a specific association between a main record and a related record. If associationId is provided, only that association is removed; otherwise all associations between the two records are removed.',
    tag: '{entityName}',
    parameters: [
        [
            'name'        => 'entityName',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Relation entity name (e.g. "ProductAssociation")',
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
                        'mainRecordId',
                        'relatedRecordId',
                    ],
                    'properties' => [
                        'mainRecordId'    => [
                            'type'        => 'string',
                            'description' => 'ID of the main record from which associations are removed',
                        ],
                        'relatedRecordId' => [
                            'type'        => 'string',
                            'description' => 'ID of the related record to disassociate from the main record',
                        ],
                        'associationId'   => [
                            'type'        => 'string',
                            'description' => 'ID of a specific association to remove; if omitted, all associations between the two records are removed',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Whether the associations were successfully removed',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'Bad request — mainRecordId or relatedRecordId is missing',
        ],
        403 => [
            'description' => 'Forbidden — the current user does not have delete access to this entity type',
        ],
    ],
)]
#[EntityType(types: ['Relation'])]
class RemoveAssociatesHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $data       = $this->getRequestBody($request);

        if (!$this->getAcl()->check($entityName, 'delete')) {
            throw new Forbidden();
        }

        $associationId = property_exists($data, 'associationId') ? (string) $data->associationId : '';
        $result        = $this->getRecordService($entityName)->removeAssociates(
            (string) $data->mainRecordId,
            (string) $data->relatedRecordId,
            $associationId
        );

        return new BoolResponse($result);
    }
}
