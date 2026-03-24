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

namespace Atro\Handlers\MatchedRecord;

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/MatchedRecord',
    methods: ['GET'],
    summary: 'Returns a list of matched records',
    description: 'Returns a list of matched records with filtering and pagination.',
    tag: 'MatchedRecord',
    parameters: [
        ['name' => 'offset',          'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
        ['name' => 'maxSize',         'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
        ['name' => 'sortBy',          'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ['name' => 'asc',             'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean']],
        ['name' => 'where',           'in' => 'query', 'required' => false, 'schema' => ['anyOf' => [['type' => 'array'], ['type' => 'object'], ['type' => 'string']]]],
        ['name' => 'q',               'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ['name' => 'textFilter',      'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ['name' => 'collectionOnly',  'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean']],
        ['name' => 'totalOnly',       'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean']],
        ['name' => 'attributes',      'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ['name' => 'allAttributes',   'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'Collection of matched records', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['total' => ['type' => 'integer'], 'list' => ['type' => 'array', 'items' => ['type' => 'object']]]]]]],
    ],
)]
class MatchedRecordListHandler extends AbstractHandler
{
    const MAX_SIZE_LIMIT = 200;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('MatchedRecord', 'read')) {
            throw new Forbidden();
        }

        $qp          = $request->getQueryParams();
        $totalOnly   = ($qp['totalOnly'] ?? null) === 'true';
        $collectionOnly = ($qp['collectionOnly'] ?? null) === 'true';

        $params = [
            'where'          => $this->prepareWhereQuery($qp['where'] ?? null),
            'offset'         => $qp['offset'] ?? null,
            'maxSize'        => $qp['maxSize'] ?? self::MAX_SIZE_LIMIT,
            'asc'            => ($qp['asc'] ?? 'true') === 'true',
            'sortBy'         => $qp['sortBy'] ?? null,
            'q'              => $qp['q'] ?? null,
            'textFilter'     => $qp['textFilter'] ?? null,
            'totalOnly'      => $totalOnly,
            'collectionOnly' => $collectionOnly,
        ];

        if (!empty($qp['attributes'])) {
            $params['attributesIds'] = explode(',', $qp['attributes']);
        }

        if (($qp['allAttributes'] ?? null) === 'true' || ($qp['allAttributes'] ?? null) === '1') {
            $params['allAttributes'] = true;
        }

        $result = $this->getRecordService('MatchedRecord')->findEntities($params);

        if (!empty($totalOnly)) {
            return new JsonResponse(['total' => $result['total']]);
        }

        if (isset($result['collection'])) {
            $list = $result['collection']->getValueMapList();
        } elseif (isset($result['list'])) {
            $list = $result['list'];
        } else {
            $list = [];
        }

        if (!empty($collectionOnly)) {
            return new JsonResponse(['list' => $list]);
        }

        return new JsonResponse([
            'total' => $result['total'] ?? null,
            'list'  => $list,
        ]);
    }
}
