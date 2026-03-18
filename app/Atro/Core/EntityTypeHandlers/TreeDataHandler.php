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
use Atro\Core\EntityTypeHandlers\AbstractHandler;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Atro\Core\Routing\EntityType;

#[Route(
    path: '/{entityName}/action/treeData',
    methods: ['GET'],
    summary: 'Get full tree data',
    description: 'Returns flat tree data for a hierarchy entity, used to build complete tree views on the client.',
    tag: '{entityName}',
    parameters: [
        ['name' => 'entityName',  'in' => 'path',  'required' => true,  'schema' => ['type' => 'string']],
        ['name' => 'ids',         'in' => 'query', 'required' => false, 'schema' => ['type' => 'array', 'items' => ['type' => 'string']]],
        ['name' => 'where',       'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ['name' => 'foreignWhere','in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ['name' => 'link',        'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ['name' => 'scope',       'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'Array of tree nodes', 'content' => ['application/json' => ['schema' => ['type' => 'array', 'items' => ['type' => 'object']]]]],
    ],
)]
#[EntityType(types: ['Hierarchy'])]
class TreeDataHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $entityName = $this->getEntityName($request);
        $qp         = $request->getQueryParams();

        if (!$this->getAcl()->check($entityName, 'read')) {
            throw new Forbidden();
        }

        if (!empty($qp['ids'])) {
            $params = ['ids' => (array) $qp['ids']];
        } else {
            $params = [
                'where'        => $this->prepareWhereQuery($qp['where'] ?? []),
                'foreignWhere' => $this->prepareWhereQuery($qp['foreignWhere'] ?? []),
                'link'         => (string) ($qp['link'] ?? ''),
                'scope'        => (string) ($qp['scope'] ?? ''),
                'offset'       => 0,
                'maxSize'      => 5000,
                'asc'          => true,
                'sortBy'       => 'id',
            ];
        }

        $result = $this->getRecordService($entityName)->getTreeData($params);

        return new JsonResponse($result);
    }
}
