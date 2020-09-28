<?php
declare(strict_types=1);

namespace Treo\Core\Loaders;

use Espo\Core\Utils\ThemeManager;
use Treo\Core\Utils\Config;

/**
 * ClientManager loader
 *
 * @author r.ratsun@treolabs.com
 */
class ClientManager extends Base
{

    /**
     * @inheritDoc
     */
    public function load()
    {
        return new \Espo\Core\Utils\ClientManager($this->getConfig(), $this->getThemeManager());
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

    /**
     * Get theme manager
     *
     * @return ThemeManager
     */
    protected function getThemeManager()
    {
        return $this->getContainer()->get('themeManager');
    }
}
