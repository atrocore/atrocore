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

use Atro\Core\OpenApiGenerator;
use Atro\Core\Routing\Route as RouteAttribute;
use Atro\Core\Utils\Config;
use Atro\Core\Utils\Metadata;
use Atro\Entities\User;
use Atro\Handlers\Global\UpsertAsyncHandler;
use Atro\Handlers\Global\UpsertHandler;
use Espo\ORM\EntityManager;
use Mezzio\Router\RouteResult;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ActionHistoryMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $historyRecord   = null;
        $historyData     = [];

        if ($this->shouldLog($request)) {
            $entityName = $this->resolveEntityName($request);
            $method     = $request->getMethod();
            $user       = $this->getUser();

            $historyRecord = $this->getEntityManager()->getEntity('ActionHistoryRecord');
            $historyRecord->set('controllerName', $entityName);
            $historyRecord->set('action', $method);
            $historyRecord->set('userId', $user->id);
            $historyRecord->set('authTokenId', $user->get('authTokenId'));
            $historyRecord->set('ipAddress', $user->get('ipAddress'));
            $historyRecord->set('authLogRecordId', $user->get('authLogRecordId'));

            $id = (string) $request->getAttribute('id');
            if ($id !== '') {
                $historyRecord->set('targetId', $id);
            }

            $headers = $request->getHeaders();
            foreach (array_keys($headers) as $h) {
                if (in_array(strtolower((string)$h), ['authorization-token', 'authorization', 'cookie'])) {
                    unset($headers[$h]);
                }
            }

            $historyData = ['request' => ['headers' => $headers, 'params' => $request->getQueryParams()]];

            $body = (string) $request->getBody();
            if ($body !== '') {
                $decoded = json_decode($body, true);
                if (is_array($decoded) && ($routeAttr = $this->resolveRouteAttribute($request)) !== null) {
                    $routeHandler = $request->getAttribute(RouteResult::class)->getMatchedRoute()->getMiddleware();
                    if ($routeHandler instanceof UpsertHandler || $routeHandler instanceof UpsertAsyncHandler) {
                        $decoded = $this->maskUpsertRequestBody($routeAttr, $decoded);
                    } else {
                        $decoded = $this->maskRequestBody($request, $routeAttr, $decoded);
                    }
                }
                $historyData['request']['body'] = $decoded ?? $body;
            }

            $historyRecord->set('data', $historyData);
            $this->getEntityManager()->saveEntity($historyRecord);
        }

        $response = $handler->handle($request);

        if ($historyRecord !== null) {
            $historyData['response'] = ['status' => $response->getStatusCode()];
            $historyRecord->set('data', $historyData);
            $this->getEntityManager()->saveEntity($historyRecord);
        }

        return $response;
    }

    private function shouldLog(ServerRequestInterface $request): bool
    {
        $config = $this->getConfig();

        if (!$config->get('isInstalled')) {
            return false;
        }

        if ($config->get('disableActionHistory')) {
            return false;
        }

        $user = $this->container->get('user');

        if (!$user instanceof User) {
            return false;
        }

        if ($user->get('disableActionHistory') || $user->isSystemUser()) {
            return false;
        }

        /** @var RouteResult|null $routeResult */
        $routeResult = $request->getAttribute(RouteResult::class);
        if ($routeResult && !$routeResult->isFailure()) {
            if ($routeResult->getMatchedRoute()->getOptions()['skipActionHistory'] ?? false) {
                return false;
            }
        }

        $entityName = $this->resolveEntityName($request);
        $method     = $request->getMethod();

        // Skip GET requests for layout/i18n/settings endpoints
        if ($method === 'GET') {
            if (in_array($entityName, ['Layout', 'I18n', 'Settings'])) {
                return false;
            }
            if (str_contains($request->getUri()->getPath(), '/layout/')) {
                return false;
            }
        }

        if ($this->getMetadata()->get("scopes.{$entityName}.disableActionHistory")) {
            return false;
        }

        return true;
    }

    private function maskRequestBody(ServerRequestInterface $request, RouteAttribute $routeAttr, array $body): array
    {
        $properties = [];

        $entityNames = [(string)($request->getAttribute(RouteResult::class)->getMatchedRoute()->getOptions()['entityName'] ?? '')];
        if (is_string($body['entityName'] ?? null) && $this->getMetadata()->get(['scopes', $body['entityName']])) {
            $entityNames[] = $body['entityName'];
        }

        foreach (array_unique($entityNames) as $entityName) {
            $schema = $this->container->get(OpenApiGenerator::class)->getSchemaForHandler($routeAttr, $entityName);
            $properties += $this->getSensitivePropertiesFromSchema($schema);
        }

        return $this->maskSensitiveProperties($body, $properties);
    }

    private function maskUpsertRequestBody(RouteAttribute $routeAttr, array $body): array
    {
        $propertiesByEntity = [];

        foreach ($body as $key => $item) {
            $entityName = is_array($item) ? $item['entity'] ?? null : null;
            if (!is_string($entityName) || !is_array($item['payload'] ?? null) || !$this->getMetadata()->get(['scopes', $entityName])) {
                continue;
            }

            $propertiesByEntity[$entityName] ??= $this->getSensitivePropertiesFromSchema(
                $this->container->get(OpenApiGenerator::class)->getSchemaForHandler($routeAttr, $entityName)
            );

            $body[$key]['payload'] = $this->maskSensitiveProperties($item['payload'], $propertiesByEntity[$entityName]);
        }

        return $body;
    }

    private function resolveRouteAttribute(ServerRequestInterface $request): ?RouteAttribute
    {
        $routeResult = $request->getAttribute(RouteResult::class);

        if (!$routeResult instanceof RouteResult || $routeResult->isFailure()) {
            return null;
        }

        $handlerMiddleware = $routeResult->getMatchedRoute()->getMiddleware();

        $ref        = new \ReflectionClass($handlerMiddleware);
        $attributes = $ref->getAttributes(RouteAttribute::class);

        if (empty($attributes)) {
            return null;
        }

        // Match the attribute whose path corresponds to the current route
        $path = $routeResult->getMatchedRouteName();
        foreach ($attributes as $attr) {
            /** @var RouteAttribute $routeAttr */
            $routeAttr = $attr->newInstance();
            if (str_ends_with($path, $routeAttr->path)) {
                return $routeAttr;
            }
        }

        return $attributes[0]->newInstance();
    }

    private function getSensitivePropertiesFromSchema(array $schema): array
    {
        $properties = [];

        foreach ($schema as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            if ($key === 'properties') {
                foreach ($value as $property => $definition) {
                    if (is_array($definition) && ($definition['format'] ?? null) === 'password') {
                        $properties[$property] = true;
                    }
                }
            }

            $properties += $this->getSensitivePropertiesFromSchema($value);
        }

        return $properties;
    }

    private function maskSensitiveProperties(array $data, array $fields): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && isset($fields[$key])) {
                if ($value !== null && $value !== '' && $value !== []) {
                    $data[$key] = '***';
                }
            } elseif (is_array($value)) {
                $data[$key] = $this->maskSensitiveProperties($value, $fields);
            }
        }

        return $data;
    }

    /**
     * Resolves the entity/scope name from the matched route options (EntityType-expanded routes)
     * or falls back to the first URL segment after /api/.
     */
    private function resolveEntityName(ServerRequestInterface $request): string
    {
        /** @var RouteResult|null $routeResult */
        $routeResult = $request->getAttribute(RouteResult::class);

        if ($routeResult && !$routeResult->isFailure()) {
            $entityName = $routeResult->getMatchedRoute()->getOptions()['entityName'] ?? '';
            if ($entityName !== '') {
                return $entityName;
            }
        }

        // Fallback: extract first path segment after /api/
        $path     = $request->getUri()->getPath();
        $stripped = preg_replace('#^/api/v\d+/#', '', $path);
        $parts    = explode('/', $stripped);

        return ucfirst($parts[0] ?? '');
    }

    private function getConfig(): Config
    {
        return $this->container->get('config');
    }

    private function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    private function getUser(): User
    {
        return $this->container->get('user');
    }

    private function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }
}
