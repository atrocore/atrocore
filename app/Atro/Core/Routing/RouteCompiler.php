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

use Atro\Core\DataManager;
use Atro\Core\Utils\Metadata;

/**
 * Compiles the full list of PSR-15 handler routes for both direct handlers and
 * EntityTypeHandler-expanded routes (one concrete route per entity × handler combination).
 *
 * Each compiled entry contains:
 *   - path          full route path including /api/v1 prefix
 *   - methods       HTTP methods (uppercase)
 *   - handlerClass  FQCN of the PSR-15 handler
 *   - auth          whether the route requires authentication
 *   - openapi       resolved OpenAPI operation object (tags, summary, parameters, responses, …)
 *   - schemaEntities list of entity names whose component schemas must be built in the docs
 *
 * Results are cached to data/cache/routes.json when useCache is enabled.
 */
class RouteCompiler
{
    private const CACHE_KEY = 'routes';

    public function __construct(
        private readonly DataManager    $dataManager,
        private readonly Metadata       $metadata,
        private readonly HandlerRegistry $handlerRegistry,
    ) {
    }

    /** @return array<array{path:string,methods:string[],handlerClass:string,auth:bool,openapi:array,schemaEntities:string[]}> */
    public function getCompiledRoutes(): array
    {
        $cached = $this->dataManager->getCacheData(self::CACHE_KEY);
        if ($cached !== null) {
            return $cached;
        }

        $routes = $this->compile();

        $this->dataManager->setCacheData(self::CACHE_KEY, $routes);

        return $routes;
    }

    private function compile(): array
    {
        $routes = array_merge(
            $this->compileEntityTypeHandlerRoutes(),
            $this->compileDirectHandlerRoutes(),
        );

        // More specific paths (more static segments) must be registered first in FastRoute
        usort($routes, fn($a, $b) => $this->pathSpecificity($b['path']) <=> $this->pathSpecificity($a['path']));

        return $routes;
    }

    private function compileEntityTypeHandlerRoutes(): array
    {
        $handlerEntries = $this->collectEntityTypeHandlerEntries();
        $routes         = [];

        foreach ($this->metadata->get(['entityDefs'], []) as $entityName => $data) {
            $scopeData = $this->metadata->get(['scopes', $entityName], []);

            if (empty($data['fields']) || empty($scopeData['entity'])) {
                continue;
            }

            $entityType = (string) ($scopeData['type'] ?? 'Base');

            foreach ($handlerEntries as $entry) {
                if (!in_array($entityType, $entry['types'], true)) {
                    continue;
                }

                if (in_array($entityName, $entry['excludeEntities'], true)) {
                    continue;
                }

                foreach ($entry['requires'] as $key) {
                    if (empty($scopeData[$key])) {
                        continue 2;
                    }
                }

                foreach ($entry['requiresAbsent'] as $key) {
                    if (!empty($scopeData[$key])) {
                        continue 2;
                    }
                }

                /** @var Route $routeAttr */
                $routeAttr = $entry['route'];

                $routes[] = [
                    'path'           => '/api/v1' . str_replace('{entityName}', $entityName, $routeAttr->path),
                    'methods'        => array_map('strtoupper', (array) $routeAttr->methods),
                    'handlerClass'   => $entry['class'],
                    'auth'           => $routeAttr->auth,
                    'openapi'        => $this->buildOpenApiEntry($routeAttr, $entityName),
                    'schemaEntities' => [],
                ];
            }
        }

        return $routes;
    }

    private function compileDirectHandlerRoutes(): array
    {
        $routes = [];

        foreach ($this->handlerRegistry->getHandlerClasses() as $className) {
            if (!class_exists($className)) {
                continue;
            }

            $ref        = new \ReflectionClass($className);
            $routeAttrs = $ref->getAttributes(Route::class);

            foreach ($routeAttrs as $attrObj) {
                $routeAttr = $attrObj->newInstance();

                if (empty($routeAttr->responses)) {
                    continue;
                }

                $routes[] = [
                    'path'           => '/api/v1' . $routeAttr->path,
                    'methods'        => array_map('strtoupper', (array) $routeAttr->methods),
                    'handlerClass'   => $className,
                    'auth'           => $routeAttr->auth,
                    'openapi'        => $this->buildOpenApiEntry($routeAttr),
                    'schemaEntities' => $routeAttr->entities,
                ];
            }
        }

        return $routes;
    }

    private function buildOpenApiEntry(Route $routeAttr, string $entityName = ''): array
    {
        $expanded = $entityName !== '';

        $tag = $expanded ? str_replace('{entityName}', $entityName, $routeAttr->tag) : $routeAttr->tag;

        $responses = [];
        foreach ($routeAttr->responses as $code => $response) {
            $responses[(string) $code] = $expanded
                ? $this->substituteEntitySchemaRef($response, $entityName)
                : $response;
        }

        $entry = [
            'tags'        => [$tag],
            'summary'     => $expanded
                ? str_replace('{entityName}', $entityName, $routeAttr->summary)
                : $routeAttr->summary,
            'description' => $expanded
                ? str_replace('{entityName}', $entityName, $routeAttr->description)
                : $routeAttr->description,
            'operationId' => md5(($expanded ? $entityName : '') . $routeAttr->path . implode(',', (array) $routeAttr->methods)),
            'responses'   => $responses,
            'security'    => $routeAttr->auth
                ? [['Authorization-Token' => []], ['basicAuth' => []], ['cookieAuth' => []]]
                : [],
        ];

        if (!empty($routeAttr->parameters)) {
            // Strip the {entityName} path parameter — it is baked into the concrete path
            $parameters = $expanded
                ? array_values(array_filter($routeAttr->parameters, fn($p) => ($p['name'] ?? '') !== 'entityName'))
                : $routeAttr->parameters;

            if (!empty($parameters)) {
                $entry['parameters'] = $parameters;
            }
        }

        if (!empty($routeAttr->requestBody)) {
            $entry['requestBody'] = $expanded
                ? $this->substituteEntitySchemaRef($routeAttr->requestBody, $entityName)
                : $routeAttr->requestBody;
        }

        return $entry;
    }

    /**
     * Recursively replaces `'schema' => ['type' => 'object']` (and only that exact pattern)
     * with `'schema' => ['$ref' => '#/components/schemas/{entityName}']`.
     */
    private function substituteEntitySchemaRef(array $data, string $entityName): array
    {
        if (isset($data['schema']) && $data['schema'] === ['type' => 'object']) {
            $data['schema'] = ['$ref' => "#/components/schemas/$entityName"];
            return $data;
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->substituteEntitySchemaRef($value, $entityName);
            }
        }

        return $data;
    }

    /**
     * @return array<array{class:string,route:Route,types:string[],excludeEntities:string[],requires:string[],requiresAbsent:string[]}>
     */
    private function collectEntityTypeHandlerEntries(): array
    {
        $entries = [];
        $dir     = CORE_PATH . '/Atro/Core/EntityTypeHandlers/';

        if (!is_dir($dir)) {
            return $entries;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative  = substr($file->getPathname(), strlen(CORE_PATH) + 1, -4);
            $className = str_replace('/', '\\', $relative);

            if (!class_exists($className)) {
                continue;
            }

            $ref             = new \ReflectionClass($className);
            $entityTypeAttrs = $ref->getAttributes(EntityType::class);
            $routeAttrs      = $ref->getAttributes(Route::class);

            if (empty($entityTypeAttrs) || empty($routeAttrs)) {
                continue;
            }

            $entityTypeAttr = $entityTypeAttrs[0]->newInstance();

            foreach ($routeAttrs as $routeAttrObj) {
                $routeAttr = $routeAttrObj->newInstance();

                if (empty($routeAttr->responses)) {
                    continue;
                }

                $entries[] = [
                    'class'           => $className,
                    'route'           => $routeAttr,
                    'types'           => $entityTypeAttr->types,
                    'excludeEntities' => $entityTypeAttr->excludeEntities,
                    'requires'        => $entityTypeAttr->requires,
                    'requiresAbsent'  => $entityTypeAttr->requiresAbsent,
                ];
            }
        }

        return $entries;
    }

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
}
