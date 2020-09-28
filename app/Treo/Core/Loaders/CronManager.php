<?php
declare(strict_types=1);

namespace Treo\Core\Loaders;

use Treo\Core\CronManager as Instance;

/**
 * CronManager loader
 *
 * @author r.ratsun@zinitsolutions.com
 */
class CronManager extends Base
{

    /**
     * Load CronManager
     *
     * @return Instance
     */
    public function load()
    {
        return new Instance($this->getContainer());
    }
}
