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
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Atro\Core\Routing\EntityType;
use Atro\Handlers\AbstractHandler;

#[Route(
    path: '/{entityName}/action/getDuplicateAttributes',
    methods: [
        'POST',
    ],
    summary: 'Get duplicate attributes',
    description: 'Returns attribute values suitable for pre-filling a duplicate of the given record.',
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
            'description' => 'Entity record',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'x-entity-read' => true,
                    ],
                ],
            ],
        ],
    ],
)]
#[EntityType(types: ['Base', 'Hierarchy', 'Relation'], requires: ['hasAttribute'])]
class GetDuplicateAttributesHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $data       = $this->getRequestBody($request);

        if (empty($data->id)) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($entityName, 'create') || !$this->getAcl()->check($entityName, 'read')) {
            throw new Forbidden();
        }

        $result = $this->getRecordService($entityName)->getDuplicateAttributes($data->id);

        return new JsonResponse(is_array($result) ? $result : (array) $result);
    }
}