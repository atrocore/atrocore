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

namespace Atro\Core\Factories;

use Atro\Core\Middleware\ActionHistoryMiddleware;
use Atro\Core\Middleware\ApiValidationMiddleware;
use Atro\Core\Middleware\AuthMiddleware;
use Atro\Core\Middleware\ErrorHandlerMiddleware;
use Atro\Core\Middleware\NotFoundMiddleware;
use Atro\Core\ModuleManager\Manager as ModuleManager;
use Atro\Core\Routing\RouteCompiler;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\Stratigility\MiddlewarePipe;
use Mezzio\Router\FastRouteRouter;
use Mezzio\Router\Middleware\DispatchMiddleware;
use Mezzio\Router\Middleware\RouteMiddleware;
use Mezzio\Router\Route;
use Psr\Container\ContainerInterface;

class HttpPipeline implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): MiddlewarePipe
    {
        $router = new FastRouteRouter();

        $this->registerAllRoutes($router, $container);

        $pipe = new MiddlewarePipe();
        $pipe->pipe(new ErrorHandlerMiddleware());
        $pipe->pipe(new RouteMiddleware($router));
        $pipe->pipe(new AuthMiddleware($container));
        $pipe->pipe($container->get(ActionHistoryMiddleware::class));

        $pipe->pipe($container->get(ApiValidationMiddleware::class));

        foreach ($this->collectModuleMiddlewares($container) as $middleware) {
            $pipe->pipe($middleware);
        }

        $pipe->pipe(new DispatchMiddleware());
        $pipe->pipe(new NotFoundMiddleware());

        return $pipe;
    }

    private function collectModuleMiddlewares(ContainerInterface $container): array
    {
        $middlewares = [];

        /** @var ModuleManager $moduleManager */
        $moduleManager = $container->get('moduleManager');

        foreach ($moduleManager->getModules() as $module) {
            foreach ($module->getMiddlewares() as $class) {
                $middlewares[] = $container->get($class);
            }
        }

        return $middlewares;
    }

    /**
     * Registers all compiled PSR-15 handler routes, sorted by specificity so that
     * FastRoute never sees a variable route before a static one with the same regex.
     * Direct handlers (no entityName) are registered before EntityType-expanded routes
     * at equal specificity.
     */
    private function registerAllRoutes(FastRouteRouter $router, ContainerInterface $container): void
    {
        $entries = $container->get(RouteCompiler::class)->getCompiledRoutes();

        usort($entries, function (array $a, array $b): int {
            $cmp = $this->pathSpecificity($b['path']) <=> $this->pathSpecificity($a['path']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return $this->routePriority($a) <=> $this->routePriority($b);
        });

        $registered = [];

        foreach ($entries as $entry) {
            $skip = false;
            foreach ($entry['methods'] as $method) {
                if (isset($registered[$entry['path'] . '|' . $method])) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            foreach ($entry['methods'] as $method) {
                $registered[$entry['path'] . '|' . $method] = true;
            }

            $handler = $container->get($entry['handlerClass']);
            $route   = new Route($entry['path'], $handler, $entry['methods']);

            $options = [];
            if (!$entry['auth']) {
                $options['conditions'] = ['auth' => false];
            }
            if (!empty($entry['openapi'])) {
                $options['openapi'] = $entry['openapi'];
            }
            if (!empty($entry['entityName'])) {
                $options['entityName'] = $entry['entityName'];
            }
            if (!empty($options)) {
                $route->setOptions($options);
            }

            $router->addRoute($route);
        }
    }

    /**
     * Number of static (non-placeholder) segments in a full path including /api/v1 prefix.
     * Higher score = register first.
     */
    private function pathSpecificity(string $path): int
    {
        $score = 0;
        foreach (explode('/', trim($path, '/')) as $segment) {
            if ($segment !== '' && !str_starts_with($segment, '{')) {
                $score++;
            }
        }
        return $score;
    }

    /**
     * Direct handlers (priority 1) are registered before EntityType-expanded routes (priority 2).
     */
    private function routePriority(array $entry): int
    {
        return empty($entry['entityName']) ? 1 : 2;
    }
}
