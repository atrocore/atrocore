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

namespace Atro\Core\Middleware;

use Atro\Core\Routing\EntityTypeHandlerRegistry;
use Atro\Core\Utils\Metadata;
use Mezzio\Router\RouteResult;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * When an EntityTypeHandler is registered for the request path AND the entity's template type,
 * dispatches to it — injecting the resolved entityName into request attributes.
 *
 * Dispatch priority:
 *   1. Direct handlers (registered in FastRoute via Handlers/) — already handled before this middleware.
 *   2. EntityTypeHandlers (this middleware) — matched by path pattern + entity template type.
 */
class EntityTypeDispatchMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly EntityTypeHandlerRegistry $registry,
        private readonly Metadata                  $metadata,
        private readonly ContainerInterface        $container,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var RouteResult|null $routeResult */
        $routeResult = $request->getAttribute(RouteResult::class);

        if (!$routeResult || $routeResult->isFailure()) {
            return $handler->handle($request);
        }

        // Resolve entity name from URL-matched segments
        $params     = $routeResult->getMatchedParams();
        $entityName = ucfirst((string) ($params['controller'] ?? $params['entityName'] ?? ''));

        if ($entityName === '') {
            return $handler->handle($request);
        }

        // Skip non-entity scopes (e.g. Variable — handled by dedicated handlers)
        if (!$this->metadata->get(['scopes', $entityName, 'entity'])) {
            return $handler->handle($request);
        }

        // Determine the entity's template type from metadata (defaults to 'Base')
        $entityType = (string) $this->metadata->get(['scopes', $entityName, 'type'], 'Base');

        // Try to find a matching EntityTypeHandler
        $handlerClass = $this->registry->findHandlerClass($request, $entityType, $entityName);

        if ($handlerClass === null) {
            return $handler->handle($request);
        }

        // Forward to the EntityTypeHandler, injecting entityName into request attributes
        $request = $request->withAttribute('entityName', $entityName);

        /** @var MiddlewareInterface $entityTypeHandler */
        $entityTypeHandler = $this->container->get($handlerClass);

        return $entityTypeHandler->process($request, $handler);
    }
}
