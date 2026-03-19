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

namespace Atro\Handlers\Admin;

use Atro\Core\Exceptions\Forbidden;
use Psr\Container\ContainerInterface;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Admin/clearCache',
    methods: ['POST'],
    summary: 'Clear application cache',
    description: 'Clears the application cache. Admin only.',
    tag: 'Admin',
    responses: [
        200 => ['description' => 'true on success', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
        403 => ['description' => 'Forbidden'],
    ],
)]
class ClearCacheHandler implements MiddlewareInterface
{
    public function __construct(
        private readonly ContainerInterface $container
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->container->get('user')->isAdmin()) {
            throw new Forbidden();
        }

        return new BoolResponse($this->container->get('dataManager')->clearCache());
    }
}
