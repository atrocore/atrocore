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

namespace Atro\Handlers\Action;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Espo\Core\ServiceFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Action/action/dynamicActions',
    methods: ['GET'],
    summary: 'Get dynamic actions for a scope',
    description: 'Returns the list of available dynamic actions for the specified entity scope.',
    tag: 'Action',
    parameters: [
        ['name' => 'scope', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'example' => 'Product']],
        ['name' => 'id', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ['name' => 'type', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ['name' => 'display', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'List of dynamic actions', 'content' => ['application/json' => ['schema' => [
            'type'  => 'array',
            'items' => ['type' => 'object'],
        ]]]],
        400 => ['description' => 'scope is required'],
    ],
)]
class DynamicActionsHandler implements MiddlewareInterface
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

        /** @var \Atro\Services\Action $service */
        $service = $this->serviceFactory->create('Action');

        return new JsonResponse(
            $service->getDynamicActions(
                (string)$scope,
                $query['id'] ?? null,
                $query['type'] ?? null,
                $query['display'] ?? null
            )
        );
    }
}
