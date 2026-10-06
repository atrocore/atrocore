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
use Atro\Core\Utils\Config;
use Atro\Repositories\RateLimitHit;
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
 * The queries are in the RateLimitHit repository.
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

        $now = (float)($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));

        try {
            $ipState = $this->getIpState($route, $method, $ipAddress, $limit, $period, $now);
            $retryAfter = $ipState['retryAfter'];

            if ($userName !== null) {
                $userRetryAfter = $this->getUserRetryAfter(
                    $route,
                    $method,
                    $userName,
                    (int)$this->getConfig()->get('rateLimitMaxIpsPerUser', self::DEFAULT_MAX_IPS_PER_USER),
                    (float)$this->getConfig()->get('rateLimitUserPeriod', self::DEFAULT_USER_PERIOD),
                    $now
                );
                if ($userRetryAfter !== null) {
                    $retryAfter = max($retryAfter ?? 0.0, $userRetryAfter);
                }
            }

            if ($retryAfter === null) {
                $this->getRepository()->recordHit($route, $method, $ipAddress, $userName, $now);
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
     * State of the IP's window, taken before the current request is recorded: how many hits it holds, how
     * many seconds until the next request is allowed (null when it is allowed now) and until the window
     * starts to free up.
     *
     * @return array{count: int, retryAfter: ?float, reset: float}
     */
    private function getIpState(string $route, string $method, string $ipAddress, int $limit, float $period, float $now): array
    {
        $times = $this->getRepository()->getIpHitTimes($route, $method, $ipAddress, $now - $period);
        $count = count($times);

        return [
            'count'      => $count,
            'retryAfter' => $count >= $limit ? max(0.0, $times[$count - $limit] + $period - $now) : null,
            'reset'      => $count > 0 ? max(0.0, $times[0] + $period - $now) : $period,
        ];
    }

    /**
     * Seconds until requests for the user name are allowed again when they came from more than $maxDistinctIps
     * different addresses within $period seconds, null when they are allowed now.
     */
    private function getUserRetryAfter(string $route, string $method, string $userName, int $maxDistinctIps, float $period, float $now): ?float
    {
        $hits = $this->getRepository()->getUserHits($route, $method, $userName, $now - $period);

        $perIp = [];
        foreach ($hits as $hit) {
            $perIp[$hit['ip_address']] = ($perIp[$hit['ip_address']] ?? 0) + 1;
        }

        if (count($perIp) <= $maxDistinctIps) {
            return null;
        }

        // the oldest hits leave the window first: allowed again once enough of them are gone
        foreach ($hits as $hit) {
            if (--$perIp[$hit['ip_address']] === 0) {
                unset($perIp[$hit['ip_address']]);
            }
            if (count($perIp) <= $maxDistinctIps) {
                return max(0.0, (float)$hit['request_time'] + $period - $now);
            }
        }

        return null;
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

    private function getRepository(): RateLimitHit
    {
        return $this->container->get('entityManager')->getRepository('RateLimitHit');
    }

    private function getConfig(): Config
    {
        return $this->container->get('config');
    }
}
