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
    methods: ['POST'],
    summary: 'Upsert attribute values',
    description: 'Creates or updates attribute values for the specified entity record.',
    tag: '{entityName}',
    parameters: [
        ['name' => 'entityName', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']], ['name' => 'id',         'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'Array result', 'content' => ['application/json' => ['schema' => ['type' => 'array', 'items' => ['type' => 'object']]]]],
    ],
)]
#[EntityType(types: ['Base', 'Hierarchy'], requires: ['hasAttribute'])]
class UpsertAttributeValuesHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);

        if (empty($this->getMetadata()->get("scopes.$entityName.hasAttribute"))) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($entityName, 'edit')) {
            throw new Forbidden();
        }

        $id   = (string) $request->getAttribute('id');
        $data = $this->getRequestBody($request);

        if (empty($data) || !is_array($data)) {
            throw new BadRequest();
        }

        $input                    = new \stdClass();
        $input->attributeValues   = $data;

        $this->getRecordService($entityName)->updateEntity($id, $input);

        return new BoolResponse(true);
    }
}