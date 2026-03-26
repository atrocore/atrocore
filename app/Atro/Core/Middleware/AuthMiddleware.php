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

use Atro\Core\Exceptions\Unauthorized;
use Psr\Container\ContainerInterface;
use Atro\Core\Http\Response\ErrorResponse;
use Espo\Core\Utils\Auth;
use Mezzio\Router\RouteResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthMiddleware implements MiddlewareInterface
{
    private const ALLOWED_URI_WITH_EXPIRED_PASSWORD = [
        '/api/',
        '/api/User/action/changeExpiredPassword',
        '/api/App/user',
    ];

    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routeResult = $request->getAttribute(RouteResult::class);

        if (!$routeResult || $routeResult->isFailure()) {
            return $handler->handle($request);
        }

        $options      = $routeResult->getMatchedRoute()->getOptions();
        $authRequired = !isset($options['conditions']['auth']) || $options['conditions']['auth'] !== false;

        [$username, $password] = $this->extractCredentials($request);

        $auth = new Auth($this->container, false, $request);

        if (!$authRequired) {
            if ($username && $password) {
                try {
                    $auth->login($username, $password);
                } catch (\Exception $e) {
                    // optional auth — silently ignore failure
                }
            } else {
                $auth->useNoAuth();
            }

            return $handler->handle($request);
        }

        if (!$username || !$password) {
            return $this->unauthorizedResponse();
        }

        try {
            $isAuthenticated = $auth->login($username, $password);
        } catch (Unauthorized $e) {
            $uri = $request->getUri()->getPath();
            if (in_array($uri, self::ALLOWED_URI_WITH_EXPIRED_PASSWORD)) {
                return $handler->handle($request->withAttribute('passwordExpired', true));
            }

            return $this->unauthorizedResponse(['Password-Expired' => 'true']);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getCode() ?: 500, $e->getMessage(), ['X-Status-Reason' => $e->getMessage()]);
        }


        if (!$isAuthenticated) {
            return $this->unauthorizedResponse();
        }

        return $handler->handle($request);
    }

    private function extractCredentials(ServerRequestInterface $request): array
    {
        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return explode(':', base64_decode(substr($_SERVER['REDIRECT_HTTP_AUTHORIZATION'], 6)), 2);
        }

        $token = $request->getHeaderLine('Authorization-Token');
        if ($token !== '') {
            return explode(':', base64_decode($token), 2);
        }

        $params = $request->getServerParams();
        if (!empty($params['PHP_AUTH_USER'])) {
            return [$params['PHP_AUTH_USER'], $params['PHP_AUTH_PW'] ?? ''];
        }

        $cookies = $request->getCookieParams();
        if (!empty($cookies['auth-username']) && !empty($cookies['auth-token'])) {
            return [$cookies['auth-username'], $cookies['auth-token']];
        }

        return [null, null];
    }

    private function unauthorizedResponse(array $extraHeaders = []): ResponseInterface
    {
        return new ErrorResponse(401, 'Unauthorized', $extraHeaders);
    }
}
