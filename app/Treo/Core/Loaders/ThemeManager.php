<?php
declare(strict_types=1);

namespace Treo\Core\Loaders;

use Espo\Entities\Portal;
use Treo\Core\ORM\EntityManager;
use Treo\Core\Utils\Config;
use Treo\Core\Utils\Metadata;

/**
 * ThemeManager loader
 *
 * @author r.ratsun@gmail.com
 */
class ThemeManager extends Base
{

    /**
     * Load ThemeManager
     *
     * @return \Espo\Core\Utils\ThemeManager
     */
    public function load()
    {
        /** @var Portal $portal */
        $portal = $this->getContainer()->get('portal');

        if (!empty($portal)) {
            return new \Espo\Core\Portal\Utils\ThemeManager(
                $this->getConfig(),
                $this->getMetadata(),
                $portal
            );
        }

        return new \Espo\Core\Utils\ThemeManager(
            $this->getConfig(),
            $this->getMetadata()
        );
    }

    /**
     * Get config
     *
     * @return Config
     */
    protected function getConfig(): Config
    {
        return $this->getContainer()->get('config');
    }

    /**
     * Get metadata
     *
     * @return Metadata
     */
    protected function getMetadata(): Metadata
    {
        return $this->getContainer()->get('metadata');
    }
}
