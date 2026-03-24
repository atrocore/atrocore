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

namespace Atro\Handlers\Selection;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Selection/action/tree',
    methods: ['GET'],
    summary: 'Returns selection tree items',
    description: 'Returns a paginated list of tree items for the selection panel.',
    tag: 'Selection',
    parameters: [
        ['name' => 'link',          'in' => 'query', 'required' => true,  'schema' => ['type' => 'string']],
        ['name' => 'scope',         'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ['name' => 'selectedScope', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ['name' => 'where',         'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ['name' => 'asc',           'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean']],
        ['name' => 'sortBy',        'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ['name' => 'isTreePanel',   'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean']],
        ['name' => 'offset',        'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
        ['name' => 'maxSize',       'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
    ],
    responses: [
        200 => ['description' => 'Tree items', 'content' => ['application/json' => ['schema' => ['type' => 'array', 'items' => ['type' => 'object']]]]],
    ],
)]
class SelectionTreeHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $qp = $request->getQueryParams();

        if (empty($qp['link']) || (empty($qp['selectedScope']) && empty($qp['scope']))) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check('Selection', 'read')) {
            throw new Forbidden();
        }

        $params = [
            'where'       => $this->prepareWhereQuery($qp['where'] ?? null),
            'asc'         => ($qp['asc'] ?? 'true') === 'true',
            'sortBy'      => $qp['sortBy'] ?? null,
            'isTreePanel' => !empty($qp['isTreePanel']),
            'offset'      => (int) ($qp['offset'] ?? 0),
            'maxSize'     => empty($qp['maxSize']) ? $this->getConfig()->get('recordsPerPageSmall', 20) : (int) $qp['maxSize'],
        ];

        $scope  = (string) ($qp['selectedScope'] ?? $qp['scope']);
        $result = $this->getRecordService('Selection')->getTreeItems((string) $qp['link'], $scope, $params);

        return new JsonResponse($result);
    }
}
