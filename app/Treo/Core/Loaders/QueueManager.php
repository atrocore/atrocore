<?php

declare(strict_types=1);

namespace Treo\Core\Loaders;

use Treo\Core\QueueManager as Instance;

/**
 * Class QueueManager
 *
 * @author r.ratsun <r.ratsun@zinitsolutions.com>
 */
class QueueManager extends Base
{
    /**
     * Load QueueManager
     *
     * @return Instance
     */
    public function load()
    {
        return (new Instance())->setContainer($this->getContainer());
    }
}
