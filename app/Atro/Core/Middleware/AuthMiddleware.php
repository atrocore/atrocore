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
use Atro\Core\Http\AuthCookie;
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
        '/api/User/changeExpiredPassword',
        '/api/userSession',
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

        [$username, $password] = self::extractCredentials($request);
        $isCookieAuth = $this->isCookieAuth($request);

        if ($isCookieAuth && !in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS']) && !$this->isRequestFromOwnOrigin($request)) {
            if ($authRequired) {
                return new ErrorResponse(403, 'Cross-origin request is not allowed', ['X-Status-Reason' => 'Cross-origin request is not allowed']);
            }
            [$username, $password] = [null, null];
            $isCookieAuth = false;
        }

        $request = $request->withAttribute('isCookieAuth', $isCookieAuth);

        $auth = new Auth($this->container, false, $request);

        if (!$authRequired) {
            if ($username && $password) {
                try {
                    if (!$auth->login($username, $password)) {
                        $auth->useNoAuth();
                    }
                } catch (\Exception $e) {
                    // optional auth — silently ignore failure
                    $auth->useNoAuth();
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
            $code = $e->getCode();
            if (!is_int($code) || $code < 100 || $code >= 600) {
                $code = 500;
            }
            return new ErrorResponse($code, $e->getMessage(), ['X-Status-Reason' => $e->getMessage()]);
        }


        if (!$isAuthenticated) {
            $response = $this->unauthorizedResponse();
            if ($isCookieAuth) {
                $siteUrl = $this->container->get('config')->getSiteUrl();
                $response = $response->withHeader('Set-Cookie', AuthCookie::buildClearHeaders($request, $siteUrl));
            }
            return $response;
        }

        return $handler->handle($request);
    }

    public static function extractCredentials(ServerRequestInterface $request): array
    {
        $token = $request->getHeaderLine('Authorization-Token');
        if ($token !== '') {
            return explode(':', base64_decode($token), 2);
        }

        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return explode(':', base64_decode(substr($_SERVER['REDIRECT_HTTP_AUTHORIZATION'], 6)), 2);
        }

        $params = $request->getServerParams();
        if (!empty($params['PHP_AUTH_USER'])) {
            return [$params['PHP_AUTH_USER'], $params['PHP_AUTH_PW'] ?? ''];
        }

        $cookies = $request->getCookieParams();
        if (!empty($cookies[AuthCookie::USERNAME]) && !empty($cookies[AuthCookie::TOKEN])) {
            return [$cookies[AuthCookie::USERNAME], $cookies[AuthCookie::TOKEN]];
        }

        return [null, null];
    }

    private function isCookieAuth(ServerRequestInterface $request): bool
    {
        $cookies = $request->getCookieParams();

        return $request->getHeaderLine('Authorization-Token') === ''
            && empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])
            && empty($request->getServerParams()['PHP_AUTH_USER'])
            && !empty($cookies[AuthCookie::USERNAME])
            && !empty($cookies[AuthCookie::TOKEN]);
    }

    private function isRequestFromOwnOrigin(ServerRequestInterface $request): bool
    {
        $source = $request->getHeaderLine('Origin') ?: $request->getHeaderLine('Referer');
        $sourceHost = parse_url($source, PHP_URL_HOST);
        if (empty($sourceHost)) {
            return false;
        }

        $sourcePort = parse_url($source, PHP_URL_PORT);
        $sourceAuthority = strtolower($sourceHost . ($sourcePort ? ':' . $sourcePort : ''));

        if ($sourceAuthority === strtolower($request->getHeaderLine('Host'))) {
            return true;
        }

        $siteUrl = $this->container->get('config')->getSiteUrl();
        $siteHost = parse_url($siteUrl, PHP_URL_HOST);
        $sitePort = parse_url($siteUrl, PHP_URL_PORT);

        return !empty($siteHost) && $sourceAuthority === strtolower($siteHost . ($sitePort ? ':' . $sitePort : ''));
    }

    private function unauthorizedResponse(array $extraHeaders = []): ResponseInterface
    {
        return new ErrorResponse(401, 'Unauthorized', $extraHeaders);
    }
}
