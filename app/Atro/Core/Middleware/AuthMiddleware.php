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

use Atro\Core\Authentication\CurrentUser;
use Atro\Core\Http\Response\ErrorResponse;
use Atro\Core\OAuth\BrowserSession;
use Atro\Core\OAuth\SessionCookie;
use Atro\Core\OAuth\TokenUser;
use Atro\Entities\User;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Mezzio\Router\RouteResult;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Authenticates a request by an OAuth access token: the one in the "Authorization: Bearer" header, or the one of the
 * session of the user interface (session cookie). The data of the token is put into the request attributes
 * (oauth_access_token_id, oauth_client_id, oauth_user_id, oauth_scopes); its user becomes the current user.
 *
 * A route that needs authentication rejects a request without a valid token; a route that does not need it acts as
 * the system user.
 */
class AuthMiddleware implements MiddlewareInterface
{
    /**
     * The requests a user with an expired password may still send: changing the password.
     */
    private const ALLOWED_PATHS_WITH_EXPIRED_PASSWORD = [
        '/api/',
        '/api/User/changeExpiredPassword',
        '/api/userSession',
    ];

    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (preg_match('/^\s*Bearer\s/i', $request->getHeaderLine('Authorization'))) {
            try {
                $request = $this->container->get(ResourceServer::class)->validateAuthenticatedRequest($request);
            } catch (OAuthServerException $e) {
                return $e->generateHttpResponse(new Response());
            }

            return $this->handleAuthenticated($request, $handler);
        }

        $sessionId = SessionCookie::getSessionId($request);
        if ($sessionId === null) {
            return $this->handleUnauthenticated($request, $handler);
        }

        if (!in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS']) && !$this->isRequestFromOwnOrigin($request)) {
            return new ErrorResponse(403, 'Cross-origin request is not allowed');
        }

        $accessToken = $this->container->get(BrowserSession::class)->authenticate($sessionId);
        if ($accessToken === null) {
            // the session is over
            return $this->handleUnauthenticated($request, $handler);
        }

        $request = $request
            ->withAttribute('oauth_access_token_id', $accessToken['identifier'])
            ->withAttribute('oauth_client_id', $accessToken['clientId'])
            ->withAttribute('oauth_user_id', $accessToken['userId'])
            ->withAttribute('oauth_scopes', []);

        return $this->handleAuthenticated($request, $handler);
    }

    private function handleAuthenticated(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $tokenUser = $this->container->get(TokenUser::class);

        $user = $tokenUser->resolve((string)$request->getAttribute('oauth_user_id'), (string)$request->getAttribute('oauth_client_id'));
        if ($user === null) {
            return OAuthServerException::accessDenied('The user of the access token is not available')->generateHttpResponse(new Response());
        }

        $this->container->get(CurrentUser::class)->set($user);

        if ($this->isPasswordExpired($user)) {
            if (!in_array($request->getUri()->getPath(), self::ALLOWED_PATHS_WITH_EXPIRED_PASSWORD)) {
                return new ErrorResponse(401, 'Unauthorized', ['Password-Expired' => 'true']);
            }
            $request = $request->withAttribute('passwordExpired', true);
        }

        return $handler->handle($request);
    }

    private function handleUnauthenticated(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routeResult = $request->getAttribute(RouteResult::class);
        if (!$routeResult instanceof RouteResult || $routeResult->isFailure()) {
            return $handler->handle($request);
        }

        $options = $routeResult->getMatchedRoute()->getOptions();
        if (($options['conditions']['auth'] ?? true) !== false) {
            return new ErrorResponse(401, 'Unauthorized');
        }

        $this->container->get(CurrentUser::class)->setSystemUser();

        return $handler->handle($request);
    }

    private function isPasswordExpired(User $user): bool
    {
        return $user->needToUpdatePassword($this->container->get('config')->get('passwordExpireDays', 0));
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
}
