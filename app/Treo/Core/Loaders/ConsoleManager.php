<?php

declare(strict_types=1);

namespace Treo\Core\Loaders;

use Treo\Core\ConsoleManager as Instance;

/**
 * ConsoleManager loader
 *
 * @author r.ratsun <r.ratsun@zinitsolutions.com>
 */
class ConsoleManager extends Base
{

    /**
     * Load ConsoleManager
     *
     * @return Instance
     */
    public function load()
    {
        return (new Instance())->setContainer($this->getContainer());
    }
}
