<?php
declare(strict_types=1);

namespace Treo\Core\Loaders;

use Treo\Core\Utils\Layout as LayoutUtil;
use Espo\Core\Utils\File\Manager;
use Treo\Core\Utils\Metadata;
use Espo\Entities\User;

/**
 * Layout loader
 *
 * @author r.ratsun@zinitsolutions.com
 */
class Layout extends Base
{

    /**
     * Load Layout
     *
     * @return LayoutUtil
     */
    public function load()
    {
        return (new LayoutUtil(
            $this->getFileManager(),
            $this->getMetadata(),
            $this->getUser()
        ))->setContainer($this->getContainer());
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
     * Get metadata
     *
     * @return Metadata
     */
    protected function getMetadata()
    {
        return $this->getContainer()->get('metadata');
    }

    /**
     * Get user
     *
     * @return User
     */
    protected function getUser()
    {
        return $this->getContainer()->get('user');
    }
}
