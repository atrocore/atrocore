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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Espo\Core\ServiceFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/LastViewed/action/Tree',
    methods: ['GET'],
    summary: 'Get last viewed data for a scope for tree panels',
    description: 'Get last viewed data for a scope for tree panels',
    tag: 'LastViewed',
    parameters: [
        ['name' => 'scope', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'example' => 'Product']],
        ['name' => 'offset', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
    ],
    responses: [
        200 => ['description' => 'Last viewed tree data', 'content' => ['application/json' => ['schema' => [
            'type'       => 'object',
            'properties' => [
                'total' => ['type' => 'integer'],
                'list'  => ['type' => 'array', 'items' => [
                    'type'       => 'object',
                    'properties' => [
                        'id'             => ['type' => 'string'],
                        'name'           => ['type' => 'string'],
                        'offset'         => ['type' => 'integer'],
                        'disabled'       => ['type' => 'boolean', 'example' => false],
                        'load_on_demand' => ['type' => 'boolean', 'example' => false],
                        'total'          => ['type' => 'integer'],
                    ],
                ]],
            ],
        ]]]],
        400 => ['description' => 'scope is required'],
    ],
)]
class TreeHandler implements MiddlewareInterface
{
    public function __construct(
        private readonly ServiceFactory $serviceFactory
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $query = $request->getQueryParams();
        $scope = $query['scope'] ?? null;

        if (empty($scope)) {
            throw new BadRequest();
        }

        /** @var \Atro\Services\LastViewed $service */
        $service = $this->serviceFactory->create('LastViewed');

        return new JsonResponse(
            $service->getLastVisitItemsTreeData($scope, (int)($query['offset'] ?? 0))
        );
    }
}
