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

namespace Atro\Handlers\Variable;

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Variable/action/list',
    methods: ['GET'],
    summary: 'List variables',
    description: 'Returns all configured variables.',
    tag: 'Variable',
    responses: [
        200 => ['description' => 'List of variables', 'content' => ['application/json' => ['schema' => [
            'type'       => 'object',
            'properties' => [
                'total' => ['type' => 'integer'],
                'list'  => ['type' => 'array', 'items' => ['type' => 'object']],
            ],
        ]]]],
    ],
)]
class ListVariableHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var \Atro\Services\Variable $service */
        $service = $this->getServiceFactory()->create('Variable');

        return new JsonResponse($service->findEntities([]));
    }
}
