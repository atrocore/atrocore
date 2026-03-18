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

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Atro\Core\Routing\EntityType;

#[Route(
    path: '/{entityName}/{id}/{link}',
    methods: ['GET'],
    summary: 'Returns linked records',
    description: 'Returns a collection of records linked to the specified entity record.',
    tag: '{entityName}',
    parameters: [
        ['name' => 'entityName', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']], ['name' => 'id',         'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']], ['name' => 'link',       'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']], ['name' => 'where',   'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']], ['name' => 'offset',  'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'example' => 0]], ['name' => 'maxSize', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'example' => 50]], ['name' => 'sortBy',  'in' => 'query', 'required' => false, 'schema' => ['type' => 'string',  'example' => 'name']], ['name' => 'asc',     'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean', 'example' => true]],
    ],
    responses: [
        200 => ['description' => 'Collection of records', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['total' => ['type' => 'integer'], 'list' => ['type' => 'array', 'items' => ['type' => 'object']]]]]]],
    ],
)]
#[EntityType(types: ['Base', 'Hierarchy', 'Relation'])]
class ListLinkedHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $id         = (string) $request->getAttribute('id');
        $link       = (string) $request->getAttribute('link');
        $qp         = $request->getQueryParams();

        $params = $this->buildListParams($request);
        $params['whereRelation'] = $this->prepareWhereQuery($qp['whereRelation'] ?? null);

        $result = $this->getRecordService($entityName)->findLinkedEntities($id, $link, $params);

        return new JsonResponse($this->buildListResult($result, $params));
    }
}