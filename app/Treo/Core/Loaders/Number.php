<?php
declare(strict_types=1);

namespace Treo\Core\Loaders;

use Treo\Core\Utils\Config;

/**
 * Number loader
 *
 * @author r.ratsun@zinitsolutions.com
 */
class Number extends Base
{

    /**
     * Load Number
     *
     * @return \Espo\Core\Utils\NumberUtil
     */
    public function load()
    {
        return new \Espo\Core\Utils\NumberUtil(
            $this->getConfig()->get('decimalMark'),
            $this->getConfig()->get('thousandSeparator')
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
