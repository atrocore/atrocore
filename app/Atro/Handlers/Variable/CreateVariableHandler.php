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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Variable',
    methods: ['POST'],
    summary: 'Create variable',
    description: 'Creates a new configuration variable. Admin only.',
    tag: 'Variable',
    responses: [
        200 => ['description' => 'Created variable', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
        403 => ['description' => 'Forbidden'],
    ],
)]
class CreateVariableHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        $data = json_decode((string)$request->getBody()) ?? new \stdClass();

        /** @var \Atro\Services\Variable $service */
        $service = $this->getServiceFactory()->create('Variable');

        return new JsonResponse($service->createEntity($data));
    }
}
