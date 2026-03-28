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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Handlers\AbstractHandler;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Atro\Core\Routing\EntityType;

#[Route(
    path: '/{entityName}/action/removeAssociates',
    methods: [
        'POST',
    ],
    summary: 'Remove associated records',
    description: 'Removes associations between main and related records, optionally filtered by association ID.',
    tag: '{entityName}',
    parameters: [
        [
            'name'     => 'entityName',
            'in'       => 'path',
            'required' => true,
            'schema'   => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Success',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
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

        if (!property_exists($data, 'mainRecordId') || !property_exists($data, 'relatedRecordId')) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($entityName, 'delete')) {
            throw new Forbidden();
        }

        $associationId = property_exists($data, 'associationId') ? (string) $data->associationId : '';
        $result        = $this->getRecordService($entityName)->removeAssociates(
            (string) $data->mainRecordId,
            (string) $data->relatedRecordId,
            $associationId
        );

        return new JsonResponse($result);
    }
}
