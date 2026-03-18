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

namespace Atro\Core\Routing;

use Mezzio\Router\FastRouteRouter;
use Mezzio\Router\Route as MezzioRoute;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Builds a secondary FastRoute router from core EntityTypeHandler classes and resolves
 * which handler class applies to a given request + entity type at runtime.
 */
class EntityTypeHandlerRegistry
{
    /** @var array<string, list<array{class: string, types: string[]}>> routeKey → candidates */
    private array $handlerList = [];

    private ?FastRouteRouter $router = null;

    /**
     * Returns FQCN of the first EntityTypeHandler that matches the request path/method
     * AND accepts the given entity type. Returns null if no match.
     */
    public function findHandlerClass(ServerRequestInterface $request, string $entityType): ?string
    {
        $result = $this->getRouter()->match($request);

        if ($result->isFailure()) {
            return null;
        }

        $routeKey   = $result->getMatchedRouteName();
        $candidates = $this->handlerList[$routeKey] ?? [];

        foreach ($candidates as $entry) {
            if (in_array($entityType, $entry['types'], true)) {
                return $entry['class'];
            }
        }

        return null;
    }

    private function getRouter(): FastRouteRouter
    {
        if ($this->router !== null) {
            return $this->router;
        }

        $this->router     = new FastRouteRouter();
        $this->handlerList = [];

        // Stub handler — only used to satisfy Mezzio\Router\Route constructor; never dispatched.
        $stub = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $r, RequestHandlerInterface $h): ResponseInterface
            {
                throw new \LogicException('EntityTypeHandlerRegistry stub must not be dispatched');
            }
        };

        $registered = [];
        foreach ($this->scanCoreHandlerClasses() as $className) {
            $this->registerClass($className, $stub, $registered);
        }

        return $this->router;
    }

    private function registerClass(string $className, MiddlewareInterface $stub, array &$registered): void
    {
        if (!class_exists($className)) {
            return;
        }

        $ref = new \ReflectionClass($className);

        $routeAttrs      = $ref->getAttributes(Route::class);
        $entityTypeAttrs = $ref->getAttributes(EntityType::class);

        if (empty($routeAttrs) || empty($entityTypeAttrs)) {
            return;
        }

        /** @var EntityType $entityTypeAttr */
        $entityTypeAttr = $entityTypeAttrs[0]->newInstance();
        $types          = $entityTypeAttr->types;

        foreach ($routeAttrs as $attrObj) {
            /** @var Route $routeAttr */
            $routeAttr = $attrObj->newInstance();

            $methods  = array_map('strtoupper', (array) $routeAttr->methods);
            $fullPath = '/api/v1' . $routeAttr->path;

            // Unique key: used as route name and handlerList key
            $routeKey = implode(',', $methods) . ':' . $fullPath;

            if (!isset($registered[$routeKey])) {
                $this->router->addRoute(new MezzioRoute($fullPath, $stub, $methods, $routeKey));
                $registered[$routeKey] = true;
            }

            $this->handlerList[$routeKey][] = ['class' => $className, 'types' => $types];
        }
    }

    /** @return string[] */
    private function scanCoreHandlerClasses(): array
    {
        $dir = CORE_PATH . '/Atro/Core/EntityTypeHandlers/';

        if (!is_dir($dir)) {
            return [];
        }

        $classes  = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relative  = substr($file->getPathname(), strlen(CORE_PATH) + 1, -4);
            $classes[] = str_replace('/', '\\', $relative);
        }

        return $classes;
    }
}
