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

use Atro\Core\Middleware\ApiValidationMiddleware;
use Atro\Core\Middleware\AuthMiddleware;
use Atro\Core\Middleware\ErrorHandlerMiddleware;
use Atro\Core\Middleware\LegacyControllerHandler;
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
        $router        = new FastRouteRouter();
        $legacyHandler = new LegacyControllerHandler($container);

        $this->registerAllRoutes($router, $container, $legacyHandler);

        $pipe = new MiddlewarePipe();
        $pipe->pipe(new ErrorHandlerMiddleware());
        $pipe->pipe(new RouteMiddleware($router));
        $pipe->pipe(new AuthMiddleware($container));

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
     * Collects compiled handler routes and legacy routes into a single list,
     * sorts them globally by specificity (more static segments first), then registers.
     *
     * A global sort is required because FastRoute throws BadRouteException when a static
     * route is registered after a variable route that would match the same path, even if
     * the routes come from different sources (compiled vs legacy).
     */
    private function registerAllRoutes(FastRouteRouter $router, ContainerInterface $container, LegacyControllerHandler $legacyHandler): void
    {
        $all = [];

        foreach ($container->get(RouteCompiler::class)->getCompiledRoutes() as $entry) {
            $path = substr($entry['path'], strlen('/api/v1'));
            $all[] = [
                'specificity' => $this->segmentSpecificity($path),
                'type'        => 'handler',
                'entry'       => $entry,
            ];
        }

        foreach ($container->get('route')->getAll() as $routeConfig) {
            $all[] = [
                'specificity' => $this->segmentSpecificity($routeConfig['route']),
                'type'        => 'legacy',
                'config'      => $routeConfig,
            ];
        }

        // Primary sort: more static segments first (prevents FastRoute BadRouteException).
        // Tiebreaker: legacy routes before compiled routes at equal specificity, because
        // legacy routes often have structural keywords (e.g. "layout" in /:scope/layout/:viewType)
        // that make them more constraining than variable compiled routes (e.g. /Foo/{id}/{link})
        // even though the static segment count happens to be the same.
        usort($all, function (array $a, array $b): int {
            $cmp = $b['specificity'] <=> $a['specificity'];
            if ($cmp !== 0) {
                return $cmp;
            }
            return ($a['type'] === 'legacy' ? 0 : 1) <=> ($b['type'] === 'legacy' ? 0 : 1);
        });

        // Track registered pattern+method pairs to prevent duplicates.
        // Compiled handler routes take priority — if a legacy route resolves to the same
        // pattern+method as a compiled route, it is skipped.
        $registered = [];

        foreach ($all as $item) {
            if ($item['type'] === 'handler') {
                $entry   = $item['entry'];
                $pattern = $entry['path'];
                $methods = $entry['methods'];

                $skip = false;
                foreach ($methods as $method) {
                    if (isset($registered[$pattern . '|' . $method])) {
                        $skip = true;
                        break;
                    }
                }
                if ($skip) {
                    continue;
                }

                foreach ($methods as $method) {
                    $registered[$pattern . '|' . $method] = true;
                }

                $handler = $container->get($entry['handlerClass']);
                $route   = new Route($pattern, $handler, $methods);

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
            } else {
                $routeConfig = $item['config'];
                $pattern     = $this->convertPattern(
                    '/api/v1' . $routeConfig['route'],
                    $routeConfig['conditions'] ?? []
                );
                $method = strtoupper($routeConfig['method']);
                $key    = $pattern . '|' . $method;

                if (isset($registered[$key])) {
                    continue;
                }
                $registered[$key] = true;

                $route = new Route($pattern, $legacyHandler, [$method]);
                $route->setOptions($routeConfig);
            }

            $router->addRoute($route);
        }
    }

    /**
     * Returns a specificity score for a path segment string (without /api/v1 prefix).
     * Handles both Slim-style (:param) and FastRoute-style ({param}) placeholders.
     * More static segments = higher score = registered first.
     */
    private function segmentSpecificity(string $path): int
    {
        $score = 0;
        foreach (explode('/', trim($path, '/')) as $segment) {
            if ($segment !== '' && !str_starts_with($segment, ':') && !str_starts_with($segment, '{')) {
                $score++;
            }
        }

        return $score;
    }

    /**
     * Converts Slim-style placeholders (:param) to FastRoute syntax ({param} or {param:regex}).
     * Strips wrapping parentheses from Slim conditions (e.g. "(.*)" → ".*").
     * Skips non-string conditions (e.g. auth: false).
     */
    private function convertPattern(string $routePath, array $conditions): string
    {
        return preg_replace_callback('/:(\w+)/', function (array $m) use ($conditions): string {
            $name    = $m[1];
            $pattern = $conditions[$name] ?? null;

            if (!is_string($pattern)) {
                return '{' . $name . '}';
            }

            // Slim wraps patterns in parens: "(.*)" → ".*"
            $pattern = preg_replace('/^\((.*)\)$/', '$1', $pattern);

            return '{' . $name . ':' . $pattern . '}';
        }, $routePath);
    }

}