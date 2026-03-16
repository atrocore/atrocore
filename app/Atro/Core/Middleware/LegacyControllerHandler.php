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

use Atro\Core\Container;
use Atro\Core\Slim\Validator;
use GuzzleHttp\Psr7\Response;
use Mezzio\Router\RouteResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Bridges the legacy controller system into the PSR-15 middleware pipeline.
 * Reads the matched route from the request, resolves controller/action from
 * route params, and delegates to ControllerManager.
 *
 * @deprecated Remove when all controllers are migrated to PSR-15 handlers.
 */
class LegacyControllerHandler implements MiddlewareInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var RouteResult $routeResult */
        $routeResult = $request->getAttribute(RouteResult::class);

        if (!$routeResult || $routeResult->isFailure()) {
            return $handler->handle($request);
        }

        $routeConfig  = $routeResult->getMatchedRoute()->getOptions();
        $routeParams  = $routeResult->getMatchedParams();
        $params       = $routeConfig['params'] ?? [];

        if (!is_array($params)) {
            return $this->jsonResponse(200, json_encode($params));
        }

        // Resolve :placeholder values inside route params from matched URL segments
        $controllerParams = [];
        foreach ($params as $key => $value) {
            if (is_string($value) && str_starts_with($value, ':')) {
                $paramName = substr($value, 1);
                $value     = $routeParams[$paramName] ?? $value;
            }
            $controllerParams[$key] = $value;
        }

        $params = array_merge($routeParams, $controllerParams);

        // Routes without a controller just echo their params (legacy behaviour)
        if (empty($controllerParams['controller'])) {
            return $this->jsonResponse(200, json_encode($controllerParams));
        }

        $controllerName = ucfirst($controllerParams['controller']);

        if (!empty($controllerParams['action'])) {
            $actionName = $controllerParams['action'];
        } else {
            $crudList   = $this->container->get('config')->get('crud');
            $actionName = $crudList[strtolower($request->getMethod())] ?? 'index';
        }

        try {
            $this->container->get(Validator::class)->validateRequest($routeConfig, $request);

            $result   = $this->container->get('controllerManager')
                ->process($controllerName, $actionName, $params, $request);

            $response = $this->jsonResponse(200, $result);

            $this->container->get(Validator::class)->validateResponse($routeConfig, $response);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }

        return $response;
    }

    private function jsonResponse(int $status, string $body): ResponseInterface
    {
        return new Response(
            $status,
            [
                'Content-Type'  => 'application/json; charset=utf-8',
                'Expires'       => '0',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma'        => 'no-cache',
            ],
            $body
        );
    }

    private function errorResponse(\Throwable $e): ResponseInterface
    {
        $code = $e->getCode() ?: 500;

        return new Response(
            $code,
            ['Content-Type' => 'application/json; charset=utf-8'],
            json_encode(['message' => $e->getMessage()])
        );
    }
}
