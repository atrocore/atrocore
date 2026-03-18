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

use Atro\Core\Http\Response\Errors\ForbiddenResponse;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Atro\Core\Routing\EntityType;

#[Route(
    path: '/{entityName}',
    methods: ['GET'],
    summary: 'Returns a collection of records',
    description: 'Returns a collection of records for the specified entity.',
    tag: '{entityName}',
    parameters: [
        ['name' => 'entityName', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']], ['name' => 'select',  'in' => 'query', 'required' => false, 'description' => 'Comma-separated fields', 'schema' => ['type' => 'string', 'example' => 'id,name,createdAt']], ['name' => 'where',   'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']], ['name' => 'offset',  'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'example' => 0]], ['name' => 'maxSize', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'example' => 50]], ['name' => 'sortBy',  'in' => 'query', 'required' => false, 'schema' => ['type' => 'string',  'example' => 'name']], ['name' => 'asc',     'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean', 'example' => true]],
    ],
    responses: [
        200 => ['description' => 'Collection of records', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['total' => ['type' => 'integer'], 'list' => ['type' => 'array', 'items' => ['type' => 'object']]]]]]],
    ],
)]
#[EntityType(types: ['Base', 'Hierarchy', 'Archive', 'Relation', 'ReferenceData'])]
class ListHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);

        if (!$this->getAcl()->check($entityName, 'read')) {
            return new ForbiddenResponse();
        }

        $params = $this->buildListParams($request);
        $result = $this->getRecordService($entityName)->findEntities($params);

        return new JsonResponse($this->buildListResult($result, $params));
    }
}