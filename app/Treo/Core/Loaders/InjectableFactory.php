<?php
declare(strict_types=1);

namespace Treo\Core\Loaders;

use Treo\Core\InjectableFactory as Instance;

/**
 * InjectableFactory loader
 *
 * @author r.ratsun@zinitsolutions.com
 */
class InjectableFactory extends Base
{

    /**
     * Load InjectableFactory
     *
     * @return Instance
     */
    public function load()
    {
        return new Instance($this->getContainer());
    }
}
