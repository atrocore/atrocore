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

namespace Atro\Repositories;

use Atro\Core\Templates\Repositories\Archive;
use Atro\Core\Utils\IdGenerator;

class RateLimitHit extends Archive
{
    protected $processFieldsAfterSaveDisabled = true;

    protected $processFieldsBeforeSaveDisabled = true;

    protected $processFieldsAfterRemoveDisabled = true;

    protected bool $cacheable = false;

    public function recordHit(string $route, string $method, string $ipAddress, ?string $userName, float $requestTime): void
    {
        $this->getDbal()->createQueryBuilder()
            ->insert('rate_limit_hit')
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
            ->setParameter('requestTime', $requestTime)
            ->setParameter('createdAt', date('Y-m-d H:i:s'))
            ->executeStatement();
    }

    /**
     * Times (oldest first) of the hits of one IP address on one route since $since.
     *
     * @return float[]
     */
    public function getIpHitTimes(string $route, string $method, string $ipAddress, float $since): array
    {
        return array_map('floatval', $this->getDbal()->createQueryBuilder()
            ->select('request_time')
            ->from('rate_limit_hit')
            ->where('route = :route')
            ->andWhere('method = :method')
            ->andWhere('ip_address = :ipAddress')
            ->andWhere('request_time > :since')
            ->orderBy('request_time', 'ASC')
            ->setParameter('route', $route)
            ->setParameter('method', $method)
            ->setParameter('ipAddress', $ipAddress)
            ->setParameter('since', $since)
            ->fetchFirstColumn());
    }

    /**
     * IP address and time (oldest first) of the hits of one user name on one route since $since.
     *
     * @return array<int, array{ip_address: string, request_time: float|string}>
     */
    public function getUserHits(string $route, string $method, string $userName, float $since): array
    {
        return $this->getDbal()->createQueryBuilder()
            ->select('ip_address, request_time')
            ->from('rate_limit_hit')
            ->where('route = :route')
            ->andWhere('method = :method')
            ->andWhere('user_name = :userName')
            ->andWhere('request_time > :since')
            ->orderBy('request_time', 'ASC')
            ->setParameter('route', $route)
            ->setParameter('method', $method)
            ->setParameter('userName', $userName)
            ->setParameter('since', $since)
            ->fetchAllAssociative();
    }
}
