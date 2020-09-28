<?php

declare(strict_types=1);

namespace Treo\Services;

/**
 * AdminNotifications service
 *
 * @author r.ratsun <r.ratsun@zinitsolutions.com>
 */
class AdminNotifications extends \Espo\Core\Services\Base
{

    /**
     * New version checker
     *
     * @param array $data
     *
     * @return bool
     */
    public function newVersionChecker($data): bool
    {
        return true;
    }
}
