<?php
declare(strict_types=1);

namespace Treo\Core\Loaders;

use Treo\Core\Utils\Config;
use Espo\Core\Utils\DateTime as Instance;

/**
 * DateTime loader
 *
 * @author r.ratsun@zinitsolutions.com
 */
class DateTime extends Base
{

    /**
     * Load DateTime
     *
     * @return \Espo\Core\Utils\DateTime
     */
    public function load()
    {
        return new Instance(
            $this->getConfig()->get('dateFormat'),
            $this->getConfig()->get('timeFormat'),
            $this->getConfig()->get('timeZone')
        );
    }

    /**
     * Get config
     *
     * @return Config
     */
    protected function getConfig()
    {
        return $this->getContainer()->get('config');
    }
}
