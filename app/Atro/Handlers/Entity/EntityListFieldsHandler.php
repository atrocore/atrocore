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

namespace Atro\Handlers\Entity;

use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Mezzio\Router\RouteResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Entity/{id}/fields',
    methods: ['GET'],
    summary: 'Returns fields linked to an Entity',
    description: 'Returns a collection of field records linked to the specified Entity record.',
    tag: 'Entity',
    parameters: [
        ['name' => 'id',      'in' => 'path',  'required' => true,  'schema' => ['type' => 'string']],
        ['name' => 'where',   'in' => 'query', 'required' => false, 'schema' => ['anyOf' => [['type' => 'array'], ['type' => 'object'], ['type' => 'string']]]],
        ['name' => 'offset',  'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'example' => 0]],
        ['name' => 'maxSize', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'example' => 50]],
        ['name' => 'sortBy',  'in' => 'query', 'required' => false, 'schema' => ['type' => 'string',  'example' => 'name']],
        ['name' => 'asc',     'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean', 'example' => true]],
        ['name' => 'select',  'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'Collection of field records', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['total' => ['type' => 'integer'], 'list' => ['type' => 'array', 'items' => ['type' => 'object']]]]]]],
        404 => ['description' => 'Entity not found'],
    ],
)]
class EntityListFieldsHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routeResult = $request->getAttribute(RouteResult::class);
        $id          = (string) ($routeResult?->getMatchedParams()['id'] ?? '');

        if ($id === '') {
            throw new NotFound();
        }

        $params = $this->buildListParams($request);
        $result = $this->getRecordService('Entity')->findLinkedEntities($id, 'fields', $params);

        return new JsonResponse($this->buildListResult($result, $params));
    }
}
