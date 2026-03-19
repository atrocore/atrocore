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
    /** @var array<string, list<array{class: string, types: string[], excludeEntities: string[]}>> routeKey → candidates */
    private array $handlerList = [];

    private ?FastRouteRouter $router = null;

    /**
     * Returns FQCN of the first EntityTypeHandler that matches the request path/method
     * AND accepts the given entity type. Returns null if no match.
     */
    public function findHandlerClass(ServerRequestInterface $request, string $entityType, string $entityName = ''): ?string
    {
        $result = $this->getRouter()->match($request);

        if ($result->isFailure()) {
            return null;
        }

        $routeKey   = $result->getMatchedRouteName();
        $candidates = $this->handlerList[$routeKey] ?? [];

        foreach ($candidates as $entry) {
            if (!in_array($entityType, $entry['types'], true)) {
                continue;
            }
            if ($entityName !== '' && in_array($entityName, $entry['excludeEntities'], true)) {
                continue;
            }
            return $entry['class'];
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

        // Collect all route entries first, then sort by specificity before registering.
        // FastRoute matches in registration order when multiple patterns could match the same path
        // (e.g. /{a}/action/tree vs /{a}/{b}/{c}). More specific routes must be registered first.
        $entries = $this->collectRouteEntries();

        usort($entries, fn(array $a, array $b) => $this->pathSpecificity($b['path']) <=> $this->pathSpecificity($a['path']));

        $registered = [];
        foreach ($entries as $entry) {
            $routeKey = $entry['key'];
            if (!isset($registered[$routeKey])) {
                $this->router->addRoute(new MezzioRoute($entry['path'], $stub, $entry['methods'], $routeKey));
                $registered[$routeKey] = true;
            }
            $this->handlerList[$routeKey][] = ['class' => $entry['class'], 'types' => $entry['types'], 'excludeEntities' => $entry['excludeEntities']];
        }

        return $this->router;
    }

    /** @return array<array{class: string, methods: string[], path: string, key: string, types: string[]}> */
    private function collectRouteEntries(): array
    {
        $entries = [];

        foreach ($this->scanCoreHandlerClasses() as $className) {
            if (!class_exists($className)) {
                continue;
            }

            $ref             = new \ReflectionClass($className);
            $routeAttrs      = $ref->getAttributes(Route::class);
            $entityTypeAttrs = $ref->getAttributes(EntityType::class);

            if (empty($routeAttrs) || empty($entityTypeAttrs)) {
                continue;
            }

            /** @var EntityType $entityTypeAttr */
            $entityTypeAttr  = $entityTypeAttrs[0]->newInstance();
            $types           = $entityTypeAttr->types;
            $excludeEntities = $entityTypeAttr->excludeEntities;

            foreach ($routeAttrs as $attrObj) {
                /** @var Route $routeAttr */
                $routeAttr = $attrObj->newInstance();
                $methods   = array_map('strtoupper', (array) $routeAttr->methods);
                $fullPath  = '/api/v1' . $routeAttr->path;
                $routeKey  = implode(',', $methods) . ':' . $fullPath;

                $entries[] = ['class' => $className, 'methods' => $methods, 'path' => $fullPath, 'key' => $routeKey, 'types' => $types, 'excludeEntities' => $excludeEntities];
            }
        }

        return $entries;
    }

    /** Returns number of static (non-placeholder) segments — higher means register first. */
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
