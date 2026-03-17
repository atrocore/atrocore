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

use Atro\Core\Routing\Route as RouteAttribute;
use Atro\Core\Http\Validator;
use Mezzio\Router\RouteResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ApiValidationMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Validator $validator)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routeAttr = $this->resolveRouteAttribute($request);

        if ($routeAttr === null) {
            return $handler->handle($request);
        }

        $this->validator->validateHandlerRequest($routeAttr, $request);

        $response = $handler->handle($request);

        $this->validator->validateHandlerResponse($routeAttr, $request->getMethod(), $response);

        return $response;
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
}
