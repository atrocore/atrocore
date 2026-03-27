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

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Mezzio\Router\RouteResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Variable/{id}',
    methods: [
        'GET',
    ],
    summary: 'Read variable',
    description: 'Returns a single variable by ID. Admin only.',
    tag: 'Variable',
    parameters: [
        [
            'name'     => 'id',
            'in'       => 'path',
            'required' => true,
            'schema'   => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Variable record',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        '$ref' => '#/components/schemas/Variable',
                    ],
                ],
            ],
        ],
        403 => [
            'description' => 'Forbidden',
        ],
        404 => [
            'description' => 'Not found',
        ],
    ],
    entities: [
        'Variable',
    ],
)]
class ReadVariableHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        $id = $request->getAttribute(RouteResult::class)?->getMatchedParams()['id'] ?? '';

        /** @var \Atro\Services\Variable $service */
        $service = $this->getServiceFactory()->create('Variable');

        return new JsonResponse($service->readEntity($id));
    }
}
