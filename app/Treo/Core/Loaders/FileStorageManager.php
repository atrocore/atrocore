<?php

declare(strict_types=1);

namespace Treo\Core\Loaders;

use Treo\Core\Utils\Metadata;
use Treo\Core\FileStorage\Manager;

/**
 * FileStorageManager loader
 *
 * @author r.ratsun@zinitsolutions.com
 */
class FileStorageManager extends Base
{

    /**
     * Load FileStorageManager
     *
     * @return \Treo\Core\FileStorage\Manager
     */
    public function load()
    {
        return new Manager(
            $this->getMetadata()->get(['app', 'fileStorage', 'implementationClassNameMap']),
            $this->getContainer()
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
}
