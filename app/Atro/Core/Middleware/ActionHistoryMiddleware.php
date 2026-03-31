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

use Atro\Core\Utils\Config;
use Atro\Core\Utils\Metadata;
use Atro\Entities\User;
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
            foreach (['authorization-token', 'authorization', 'Authorization-Token', 'Authorization'] as $h) {
                unset($headers[$h]);
            }

            $historyData = ['request' => ['headers' => $headers, 'params' => $request->getQueryParams()]];

            $body = (string) $request->getBody();
            if ($body !== '') {
                $decoded = json_decode($body, true);
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
