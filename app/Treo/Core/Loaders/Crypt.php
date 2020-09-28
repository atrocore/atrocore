<?php
declare(strict_types=1);

namespace Treo\Core\Loaders;

use Espo\Core\Utils\Crypt as Instance;
use Treo\Core\Utils\Config;

/**
 * Crypt loader
 *
 * @author r.ratsun@zinitsolutions.com
 */
class Crypt extends Base
{

    /**
     * Load Crypt
     *
     * @return Instance
     */
    public function load()
    {
        return new Instance($this->getConfig());
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
