<?php
declare(strict_types=1);

namespace Treo\Core\Loaders;

use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\File\Manager;
use Espo\Core\Utils\Config;
use Treo\Core\Utils\Metadata\OrmMetadata as Instance;

/**
 * OrmMetadata loader
 *
 * @author r.ratsun@zinitsolutions.com
 */
class OrmMetadata extends Base
{

    /**
     * Load OrmMetadata
     *
     * @return \Espo\Core\Utils\Metadata\OrmMetadata
     */
    public function load()
    {
        return new Instance(
            $this->getMetadata(),
            $this->getFileManager(),
            $this->getConfig()->get('useCache')
        );
    }

    /**
     * Get metadata
     *
     * @return Metadata
     */
    protected function getMetadata()
    {
        return $this->getContainer()->get('metadata');
    }

    /**
     * Get file manager
     *
     * @return Manager
     */
    protected function getFileManager()
    {
        return $this->getContainer()->get('fileManager');
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
