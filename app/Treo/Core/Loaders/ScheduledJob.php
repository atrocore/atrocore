<?php

declare(strict_types=1);

namespace Treo\Core\Loaders;

/**
 * ScheduledJob loader
 *
 * @author r.ratsun@zinitsolutions.com
 */
class ScheduledJob extends Base
{

    /**
     * Load ScheduledJob
     *
     * @return \Treo\Core\Utils\ScheduledJob
     */
    public function load()
    {
        return new \Treo\Core\Utils\ScheduledJob($this->getContainer());
    }
}
