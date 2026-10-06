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

use Atro\Core\Http\Response\ErrorResponse;
use Atro\Core\RateLimiter;
use Atro\Core\Utils\Config;
use Mezzio\Router\RouteResult;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Rate limits the routes that declare `rateLimit` in their #[Route] attribute, per IP: `limit` requests
 * per `period` seconds (both default to the rateLimitLimit / rateLimitPeriod settings). Allowed requests
 * count whatever their outcome; rejected ones get a 429 and are not recorded. Every request to
 * /api/userSession is limited, whichever credentials it carries, and additionally per user: no more than
 * rateLimitMaxIpsPerUser distinct IPs within rateLimitUserPeriod seconds. If the limiter itself fails
 * (e.g. the table is missing before the schema update), the request goes through and the error is logged.
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private const USER_SESSION_ROUTE = '/api/userSession';
    private const DEFAULT_USER_NAME_LENGTH = 255;
    private const DEFAULT_LIMIT = 3;
    private const DEFAULT_PERIOD = 1.0;
    private const DEFAULT_MAX_IPS_PER_USER = 5;
    private const DEFAULT_USER_PERIOD = 60;

    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routeResult = $request->getAttribute(RouteResult::class);
        if (!$routeResult || $routeResult->isFailure()) {
            return $handler->handle($request);
        }

        $matchedRoute = $routeResult->getMatchedRoute();
        $options = $matchedRoute->getOptions()['rateLimit'] ?? null;
        if ($options === null || !$this->getConfig()->get('isInstalled')) {
            return $handler->handle($request);
        }

        $route = $matchedRoute->getPath();
        $method = $request->getMethod();
        $ipAddress = $request->getServerParams()['REMOTE_ADDR'] ?? '';

        $userName = null;
        if ($route === self::USER_SESSION_ROUTE) {
            [$rawUserName] = AuthMiddleware::extractCredentials($request);
            // logging out sends no credentials worth limiting
            if ($rawUserName === '**logout') {
                return $handler->handle($request);
            }
            $userName = $this->normalizeUserName($rawUserName);
        }

        $limit = max(1, (int)($options['limit'] ?? $this->getConfig()->get('rateLimitLimit', self::DEFAULT_LIMIT)));
        $period = max(0.0, (float)($options['period'] ?? $this->getConfig()->get('rateLimitPeriod', self::DEFAULT_PERIOD)));

        try {
            $rateLimiter = new RateLimiter($this->container->get('entityManager')->getDbal());

            $ipState = $rateLimiter->getIpState($route, $method, $ipAddress, $limit, $period);
            $retryAfter = $ipState['retryAfter'];

            if ($userName !== null) {
                $userRetryAfter = $rateLimiter->getUserRetryAfter(
                    $route,
                    $method,
                    $userName,
                    (int)$this->getConfig()->get('rateLimitMaxIpsPerUser', self::DEFAULT_MAX_IPS_PER_USER),
                    (float)$this->getConfig()->get('rateLimitUserPeriod', self::DEFAULT_USER_PERIOD)
                );
                if ($userRetryAfter !== null) {
                    $retryAfter = max($retryAfter ?? 0.0, $userRetryAfter);
                }
            }

            if ($retryAfter === null) {
                $rateLimiter->recordHit($route, $method, $ipAddress, $userName);
            }
        } catch (\Throwable $e) {
            $GLOBALS['log']->error('RateLimit: ' . $e->getMessage());

            return $handler->handle($request);
        }

        $headers = [
            'RateLimit-Limit'     => (string)$limit,
            'RateLimit-Remaining' => (string)($retryAfter !== null ? 0 : max(0, $limit - $ipState['count'] - 1)),
            'RateLimit-Reset'     => (string)(int)ceil(round($ipState['reset'], 3)),
        ];

        if ($retryAfter !== null) {
            $headers['Retry-After'] = (string)max(1, (int)ceil(round($retryAfter, 3)));

            return new ErrorResponse(429, 'Too many requests. Please try again later.', $headers);
        }

        $response = $handler->handle($request);
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    /**
     * Lowercased and trimmed; a name the column cannot hold (too long, invalid UTF-8, NUL byte) is replaced
     * by its hash, so it still maps to a stable bucket and never makes the insert fail.
     */
    private function normalizeUserName(?string $userName): ?string
    {
        if ($userName === null || $userName === '') {
            return null;
        }

        if (!mb_check_encoding($userName, 'UTF-8') || str_contains($userName, "\0")) {
            return 'h:' . sha1($userName);
        }

        $userName = trim(mb_strtolower($userName));

        $maxLength = (int)$this->container->get('metadata')->get(['entityDefs', 'RateLimitHit', 'fields', 'userName', 'len'], self::DEFAULT_USER_NAME_LENGTH);

        return mb_strlen($userName) > $maxLength ? 'h:' . sha1($userName) : $userName;
    }

    private function getConfig(): Config
    {
        return $this->container->get('config');
    }
}
