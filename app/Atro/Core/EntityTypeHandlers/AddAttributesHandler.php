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
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Atro\Core\Routing\EntityType;
use Atro\Handlers\AbstractHandler;

#[Route(
    path: '/{entityName}/{id}/addAttributes',
    methods: [
        'POST',
    ],
    summary: 'Add attributes',
    description: 'Assigns one or more attributes to the specified entity record without setting values.',
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
        [
            'name'     => 'id',
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
#[EntityType(types: ['Base', 'Hierarchy'], requires: ['hasAttribute'])]
class AddAttributesHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);

        if (
            empty($this->getMetadata()->get("scopes.$entityName.hasAttribute")) ||
            !empty($this->getMetadata()->get(['scopes', $entityName, 'disableAttributeLinking']))
        ) {
            throw new BadRequest();
        }

        if (
            !$this->getAcl()->check($entityName, 'edit') ||
            !$this->getAcl()->check($entityName, 'createAttributeValue')
        ) {
            throw new Forbidden();
        }

        $id   = (string) $request->getAttribute('id');
        $data = $this->getRequestBody($request);

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

        $result = $this->getServiceFactory()->create('Attribute')
            ->addAttributeValue($entity->getEntityName(), $entity->get('id'), null, $attributeIds);

        return new BoolResponse(true);
    }
}