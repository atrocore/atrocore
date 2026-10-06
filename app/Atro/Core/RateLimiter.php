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

namespace Atro\Core;

use Atro\Core\Utils\IdGenerator;
use Doctrine\DBAL\Connection;

/**
 * Sliding-window-log limiter backed by the rate_limit_hit table.
 */
class RateLimiter
{
    private const TABLE = 'rate_limit_hit';

    public function __construct(private readonly Connection $dbal)
    {
    }

    public function recordHit(string $route, string $method, string $ipAddress, ?string $userName = null): void
    {
        $this->dbal->createQueryBuilder()
            ->insert(self::TABLE)
            ->values([
                'id'           => ':id',
                'route'        => ':route',
                'method'       => ':method',
                'ip_address'   => ':ipAddress',
                'user_name'    => ':userName',
                'request_time' => ':requestTime',
                'created_at'   => ':createdAt',
            ])
            ->setParameter('id', IdGenerator::sortableId())
            ->setParameter('route', $route)
            ->setParameter('method', $method)
            ->setParameter('ipAddress', $ipAddress)
            ->setParameter('userName', $userName)
            ->setParameter('requestTime', $this->getRequestTime())
            ->setParameter('createdAt', date('Y-m-d H:i:s'))
            ->executeStatement();
    }

    /**
     * State of the IP's window, taken before the current request is recorded: how many hits it holds, how
     * many seconds until the next request is allowed (null when it is allowed now) and until the window
     * starts to free up.
     *
     * @return array{count: int, retryAfter: ?float, reset: float}
     */
    public function getIpState(string $route, string $method, string $ipAddress, int $limit, float $period): array
    {
        $now = $this->getRequestTime();

        $times = array_map('floatval', $this->dbal->createQueryBuilder()
            ->select('request_time')
            ->from(self::TABLE)
            ->where('route = :route')
            ->andWhere('method = :method')
            ->andWhere('ip_address = :ipAddress')
            ->andWhere('request_time > :cutoff')
            ->orderBy('request_time', 'ASC')
            ->setParameter('route', $route)
            ->setParameter('method', $method)
            ->setParameter('ipAddress', $ipAddress)
            ->setParameter('cutoff', $now - $period)
            ->fetchFirstColumn());

        $count = count($times);

        return [
            'count'      => $count,
            'retryAfter' => $count >= $limit ? max(0.0, $times[$count - $limit] + $period - $now) : null,
            'reset'      => $count > 0 ? max(0.0, $times[0] + $period - $now) : $period,
        ];
    }

    /**
     * Seconds until requests for $userName are allowed again when they came from more than $maxDistinctIps
     * different addresses within $period seconds, null when they are allowed now.
     */
    public function getUserRetryAfter(string $route, string $method, string $userName, int $maxDistinctIps, float $period): ?float
    {
        $now = $this->getRequestTime();

        $hits = $this->dbal->createQueryBuilder()
            ->select('ip_address, request_time')
            ->from(self::TABLE)
            ->where('route = :route')
            ->andWhere('method = :method')
            ->andWhere('user_name = :userName')
            ->andWhere('request_time > :cutoff')
            ->orderBy('request_time', 'ASC')
            ->setParameter('route', $route)
            ->setParameter('method', $method)
            ->setParameter('userName', $userName)
            ->setParameter('cutoff', $now - $period)
            ->fetchAllAssociative();

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

    private function getRequestTime(): float
    {
        return (float)($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
    }
}
