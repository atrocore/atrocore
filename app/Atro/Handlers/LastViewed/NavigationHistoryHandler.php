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
use Espo\Core\ServiceFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/LastViewed/action/getNavigationHistory',
    methods: ['GET'],
    summary: 'Get navigation history for an entity',
    description: 'Returns recently viewed records for the specified entity context.',
    tag: 'LastViewed',
    parameters: [
        ['name' => 'entity', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ['name' => 'id', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ['name' => 'tabId', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ['name' => 'maxSize', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
    ],
    responses: [
        200 => ['description' => 'Navigation history', 'content' => ['application/json' => ['schema' => [
            'type'       => 'object',
            'properties' => [
                'total'      => ['type' => 'integer'],
                'collection' => ['type' => 'array', 'items' => ['type' => 'object']],
            ],
        ]]]],
    ],
)]
class NavigationHistoryHandler implements MiddlewareInterface
{
    public function __construct(
        private readonly ServiceFactory $serviceFactory
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $query = $request->getQueryParams();

        $entityName = $query['entity'] ?? null;
        $entityId   = $query['id'] ?? null;
        $tabId      = $query['tabId'] ?? null;
        $maxSize    = (int)($query['maxSize'] ?? 0) ?: 3;

        /** @var \Atro\Services\LastViewed $service */
        $service = $this->serviceFactory->create('LastViewed');

        return new JsonResponse(
            $service->getLastEntities($maxSize, $entityName, $entityId, $tabId)
        );
    }
}
