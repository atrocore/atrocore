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

namespace Atro\Handlers\LastViewed;

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/LastViewed',
    methods: ['GET'],
    summary: 'Get list of last viewed items',
    description: 'Returns a paginated list of last viewed records.',
    tag: 'LastViewed',
    parameters: [
        ['name' => 'offset', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
        ['name' => 'maxSize', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
    ],
    responses: [
        200 => ['description' => 'List of last viewed items', 'content' => ['application/json' => ['schema' => [
            'type'       => 'object',
            'properties' => [
                'total' => ['type' => 'integer'],
                'list'  => ['type' => 'array', 'items' => ['type' => 'object']],
            ],
        ]]]],
    ],
)]
class IndexHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $query = $request->getQueryParams();

        $offset  = (int)($query['offset'] ?? 0);
        $maxSize = (int)($query['maxSize'] ?? 0);

        $params = [
            'offset'      => $offset,
            'maxSize'     => $maxSize,
            'skipDeleted' => true,
        ];

        /** @var \Atro\Services\LastViewed $service */
        $service = $this->getServiceFactory()->create('LastViewed');
        $result  = $service->get($params);

        return new JsonResponse([
            'total' => $result['total'],
            'list'  => isset($result['collection']) ? $result['collection']->toArray() : $result['list'],
        ]);
    }
}
