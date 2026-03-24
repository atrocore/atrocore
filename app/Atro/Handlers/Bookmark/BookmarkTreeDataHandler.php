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

namespace Atro\Handlers\Bookmark;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Bookmark/action/TreeData',
    methods: ['GET'],
    summary: 'Returns bookmark tree data',
    description: 'Returns the bookmark tree data (total + tree) for the specified scope.',
    tag: 'Bookmark',
    parameters: [
        ['name' => 'scope',   'in' => 'query', 'required' => true,  'schema' => ['type' => 'string']],
        ['name' => 'where',   'in' => 'query', 'required' => false, 'schema' => ['anyOf' => [['type' => 'array'], ['type' => 'object'], ['type' => 'string']]]],
        ['name' => 'asc',     'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean']],
        ['name' => 'sortBy',  'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ['name' => 'offset',  'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
        ['name' => 'maxSize', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
    ],
    responses: [
        200 => ['description' => 'Bookmark tree data', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['total' => ['type' => 'integer'], 'tree' => ['type' => 'array', 'items' => ['type' => 'object']]]]]]],
    ],
)]
class BookmarkTreeDataHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $qp = $request->getQueryParams();

        if (empty($qp['scope'])) {
            throw new BadRequest();
        }

        $params = [
            'where'   => $this->prepareWhereQuery($qp['where'] ?? null),
            'asc'     => ($qp['asc'] ?? 'true') === 'true',
            'sortBy'  => $qp['sortBy'] ?? 'name',
            'offset'  => (int) ($qp['offset'] ?? 0),
            'maxSize' => empty($qp['maxSize'])
                ? $this->getConfig()->get('recordsPerPageSmall', 20)
                : (int) $qp['maxSize'],
        ];

        $result = $this->getRecordService('Bookmark')->getBookmarkTree((string) $qp['scope'], $params);

        return new JsonResponse([
            'total' => $result['total'],
            'tree'  => $result['list'],
        ]);
    }
}
