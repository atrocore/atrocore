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
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Atro\Core\Routing\EntityType;
use Atro\Handlers\AbstractHandler;

#[Route(
    path: '/{entityName}/{id}/upsertAttributeValues',
    methods: [
        'POST',
    ],
    summary: 'Upsert attribute values for a record',
    description: 'Creates or updates one or more attribute values for the specified entity record. Each item must contain an `attributeId`. Existing attribute values are updated; missing ones are created.',
    tag: '{entityName}',
    parameters: [
        [
            'name'        => 'entityName',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Entity name (e.g. "Product")',
            'schema'      => [
                'type' => 'string',
            ],
        ],
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Record ID',
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
                    'type'  => 'array',
                    'items' => [
                        '$ref' => '#/components/schemas/AttributeValuePost',
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Operation result',
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
class UpsertAttributeValuesHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);

        $id   = $request->getAttribute('id');
        $data = $this->getRequestBody($request);

        if (empty(get_object_vars($data))) {
            throw new BadRequest();
        }

        $input                  = new \stdClass();
        $input->attributeValues = $data;

        $res = $this->getRecordService($entityName)->updateEntity($id, $input);

        return new BoolResponse($res);
    }
}