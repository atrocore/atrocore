<?php

declare(strict_types=1);

namespace Treo\Core\Loaders;

use Treo\Core\Migration\Migration as Instance;

/**
 * Migration Loader
 *
 * @author r.ratsun <r.ratsun@zinitsolutions.com>
 */
class Migration extends Base
{

    /**
     * Load Migration
     *
     * @return Instance
     */
    public function load()
    {
        return (new Instance())->setContainer($this->getContainer());
    }
}
