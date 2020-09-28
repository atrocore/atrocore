<?php
declare(strict_types=1);

namespace Treo\Core\Loaders;

/**
 * ServiceFactory loader
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class ServiceFactory extends Base
{

    /**
     * @inheritDoc
     */
    public function load()
    {
        return new \Treo\Core\ServiceFactory($this->getContainer());
    }
}
