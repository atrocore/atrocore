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
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Handlers\AbstractHandler;
use Atro\Core\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/removeAssociates',
    methods: [
        'POST',
    ],
    summary: 'Remove record associations',
    description: 'Removes one or all associations between two records linked via an associates-relation entity (e.g. ProductAssociation). Pass associationId to remove a specific association type; omit it to remove all associations between the two records.',
    tag: 'Global',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'entityName',
                        'mainRecordId',
                        'relatedRecordId',
                    ],
                    'properties' => [
                        'entityName'      => [
                            'type'        => 'string',
                            'description' => 'Relation entity name (e.g. "ProductAssociation")',
                        ],
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
class RemoveAssociatesHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (empty($data->entityName) || empty($data->mainRecordId) || empty($data->relatedRecordId)) {
            throw new BadRequest('entityName, mainRecordId and relatedRecordId are required');
        }

        $entityName = (string)$data->entityName;

        if (empty($this->getMetadata()->get(['scopes', $entityName, 'associatesForEntity']))) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($entityName, 'delete')) {
            throw new Forbidden();
        }

        $associationId = property_exists($data, 'associationId') ? (string)$data->associationId : '';

        $result = $this
            ->getRecordService($entityName)
            ->removeAssociates((string)$data->mainRecordId, (string)$data->relatedRecordId, $associationId);

        return new BoolResponse($result);
    }
}
