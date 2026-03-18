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

#[Route(
    path: '/{entityName}/action/tree',
    methods: ['GET'],
    summary: 'Get tree data',
    description: 'Returns tree-structured data for the specified entity.',
    tag: '{entityName}',
    parameters: [
        ['name' => 'entityName', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']], ['name' => 'link', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']], ['name' => 'scope', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']], ['name' => 'offset',  'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'example' => 0]], ['name' => 'maxSize', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'example' => 50]], ['name' => 'sortBy',  'in' => 'query', 'required' => false, 'schema' => ['type' => 'string',  'example' => 'name']], ['name' => 'asc',     'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean', 'example' => true]],
    ],
    responses: [
        200 => ['description' => 'Array result', 'content' => ['application/json' => ['schema' => ['type' => 'array', 'items' => ['type' => 'object']]]]],
    ],
)]
#[EntityType(types: ['Base', 'Relation', 'ReferenceData'])]
class TreeHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $qp         = $request->getQueryParams();

        if (empty($qp['link']) || empty($qp['scope'])) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($entityName, 'read')) {
            throw new Forbidden();
        }

        $params = [
            'where'        => $this->prepareWhereQuery($qp['where'] ?? null),
            'foreignWhere' => $this->prepareWhereQuery($qp['foreignWhere'] ?? null),
            'asc'          => ($qp['asc'] ?? 'true') === 'true',
            'sortBy'       => $qp['sortBy'] ?? null,
            'isTreePanel'  => !empty($qp['isTreePanel']),
            'offset'       => (int) ($qp['offset'] ?? 0),
            'maxSize'      => !empty($qp['maxSize'])
                ? (int) $qp['maxSize']
                : $this->config->get('recordsPerPageSmall', 20),
        ];

        $result = $this->getRecordService($entityName)->getTreeItems((string) $qp['link'], (string) $qp['scope'], $params);

        return new JsonResponse($result);
    }
}