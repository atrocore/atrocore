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

use Atro\Core\DataManager;
use Atro\Core\Middleware\AuthMiddleware;
use Atro\Core\Middleware\LegacyControllerHandler;
use Atro\Core\Middleware\NotFoundMiddleware;
use Atro\Core\Routing\Route as RouteAttribute;
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

        $this->registerHandlerRoutes($router, $container);

        foreach ($this->sortRoutes($container->get('route')->getAll()) as $routeConfig) {
            $pattern = $this->convertPattern(
                '/api/v1' . $routeConfig['route'],
                $routeConfig['conditions'] ?? []
            );

            $route = new Route($pattern, $legacyHandler, [strtoupper($routeConfig['method'])]);
            $route->setOptions($routeConfig);
            $router->addRoute($route);
        }

        $pipe = new MiddlewarePipe();
        $pipe->pipe(new RouteMiddleware($router));
        $pipe->pipe(new AuthMiddleware($container));
        $pipe->pipe(new DispatchMiddleware());
        $pipe->pipe(new NotFoundMiddleware());

        return $pipe;
    }

    private function registerHandlerRoutes(FastRouteRouter $router, ContainerInterface $container): void
    {
        foreach ($this->discoverHandlerClasses($container) as $className) {
            if (!class_exists($className)) {
                continue;
            }

            $ref        = new \ReflectionClass($className);
            $attributes = $ref->getAttributes(RouteAttribute::class);

            if (empty($attributes)) {
                continue;
            }

            $handler = $container->get($className);

            foreach ($attributes as $attr) {
                /** @var RouteAttribute $routeAttr */
                $routeAttr = $attr->newInstance();
                $methods   = array_map('strtoupper', (array) $routeAttr->methods);
                $route     = new Route('/api/v1' . $routeAttr->path, $handler, $methods);

                if (!$routeAttr->auth) {
                    $route->setOptions(['conditions' => ['auth' => false]]);
                }

                $router->addRoute($route);
            }
        }
    }

    private function discoverHandlerClasses(ContainerInterface $container): array
    {
        $dataManager = $container->get(DataManager::class);

        $cached = $dataManager->getCacheData('handler_routes');
        if ($cached !== null) {
            return $cached;
        }

        $classes = [];

        // Core handlers: CORE_PATH/Atro/Handlers/ → namespace Atro\Handlers\...
        $coreBase = CORE_PATH . '/';
        foreach ($this->scanHandlers(CORE_PATH . '/Atro/Handlers/') as $file) {
            $relative  = substr($file, strlen($coreBase));
            $classes[] = str_replace('/', '\\', substr($relative, 0, -4));
        }

        // Module handlers: each module discovers its own handler classes
        foreach ($container->get('moduleManager')->getModules() as $module) {
            $classes = array_merge($classes, $module->getHandlerClasses());
        }

        $dataManager->setCacheData('handler_routes', $classes);

        return $classes;
    }

    private function scanHandlers(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files    = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Sorts routes so that static (more specific) routes are registered before variable ones.
     * FastRoute throws BadRouteException if a static route is added after a variable route
     * that matches the same path.
     */
    private function sortRoutes(array $routes): array
    {
        usort($routes, function (array $a, array $b): int {
            return $this->routeSpecificity($b['route']) <=> $this->routeSpecificity($a['route']);
        });

        return $routes;
    }

    /**
     * Returns a specificity score: more static segments = higher score = registered first.
     */
    private function routeSpecificity(string $route): int
    {
        $segments = explode('/', trim($route, '/'));
        $score    = 0;
        foreach ($segments as $segment) {
            $score += str_starts_with($segment, ':') ? 0 : 1;
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