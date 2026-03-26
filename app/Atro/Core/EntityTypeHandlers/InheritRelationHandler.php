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
use Atro\Handlers\AbstractHandler;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Atro\Core\Routing\EntityType;

#[Route(
    path: '/{entityName}/action/inheritRelation',
    methods: ['PATCH'],
    summary: 'Inherit a relation record',
    description: 'Creates an inherited copy of a relation record from a parent entity.',
    tag: '{entityName}',
    parameters: [
        ['name' => 'entityName', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'Inherited relation record', 'content' => ['application/json' => ['schema' => ['x-entity-read' => true]]]],
    ],
)]
#[EntityType(types: ['Relation'])]
class InheritRelationHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);

        if (!$this->getAcl()->check($entityName, 'edit')) {
            throw new Forbidden();
        }

        $data   = $this->getRequestBody($request);
        $entity = $this->getRecordService($entityName)->inheritRelation($data);

        return new JsonResponse($entity->toArray());
    }
}
