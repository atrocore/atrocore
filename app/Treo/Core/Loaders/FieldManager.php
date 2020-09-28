<?php
declare(strict_types=1);

namespace Treo\Core\Loaders;

use Treo\Core\Utils\FieldManager as Instance;

/**
 * FieldManager loader
 *
 * @author r.ratsun@zinitsolutions.com
 */
class FieldManager extends Base
{

    /**
     * Load FieldManager
     *
     * @return Instance
     */
    public function load()
    {
        return (new Instance())->setContainer($this->getContainer());
    }
}
