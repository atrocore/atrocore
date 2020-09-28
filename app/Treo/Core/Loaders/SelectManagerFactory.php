<?php

declare(strict_types=1);

namespace Treo\Core\Loaders;

/**
 * SelectManagerFactory loader
 *
 * @author r.ratsun@zinitsolutions.com
 */
class SelectManagerFactory extends Base
{
    /**
     * Load class
     *
     * @return \Treo\Core\SelectManagerFactory
     */
    public function load()
    {
        return new \Treo\Core\SelectManagerFactory($this->getContainer());
    }
}
