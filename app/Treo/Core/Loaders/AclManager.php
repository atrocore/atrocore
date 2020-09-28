<?php

declare(strict_types=1);

namespace Treo\Core\Loaders;

/**
 * Class AclManager
 *
 * @author r.ratsun@gmail.com
 */
class AclManager extends Base
{
    /**
     * Load AclManager
     *
     * @return mixed
     */
    public function load()
    {
        $aclManager = new \Espo\Core\AclManager($this->getContainer());

        if (!empty($this->getContainer()->get('portal'))) {
            return new \Espo\Core\Portal\AclManager($this->getContainer());
        }

        return $aclManager;
    }
}
