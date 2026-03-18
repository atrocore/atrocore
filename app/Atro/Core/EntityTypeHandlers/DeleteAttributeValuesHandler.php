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
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Atro\Core\Routing\EntityType;

#[Route(
    path: '/{entityName}/{id}/attributeValues',
    methods: ['DELETE'],
    summary: 'Delete attribute values',
    description: 'Removes one or more attribute assignments from the specified entity record.',
    tag: '{entityName}',
    parameters: [
        ['name' => 'entityName', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']], ['name' => 'id',         'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'Entity record', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
    ],
)]
#[EntityType(types: ['Base', 'Hierarchy'])]
class DeleteAttributeValuesHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $data       = $this->getRequestBody($request);

        if (
            empty($this->metadata->get("scopes.$entityName.hasAttribute")) ||
            !empty($this->metadata->get(['scopes', $entityName, 'disableAttributeLinking']))
        ) {
            throw new BadRequest();
        }

        if (
            !$this->getAcl()->check($entityName, 'edit') ||
            !$this->getAcl()->check($entityName, 'deleteAttributeValue')
        ) {
            throw new Forbidden();
        }

        $id           = (string) $request->getAttribute('id');
        $attributeIds = $data->attributeIds ?? [];

        if (!is_array($attributeIds) || empty($attributeIds)) {
            throw new BadRequest();
        }

        $entity = $this->getRecordService($entityName)->getEntity($id);
        if (empty($entity)) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }

        $result = $this->serviceFactory->create('Attribute')
            ->removeAttributeValues($entity->getEntityName(), $entity->get('id'), $attributeIds);

        return new JsonResponse(is_array($result) ? $result : ['true' => $result]);
    }
}