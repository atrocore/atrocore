<?php

declare(strict_types=1);

namespace Treo\Core\Loaders;

use Espo\Core\AclManager;
use Espo\Entities\User;

/**
 * Class Acl
 *
 * @author r.ratsun@gmail.com
 */
class Acl extends Base
{
    /**
     * Load Acl
     *
     * @return mixed
     */
    public function load()
    {
        if (!empty($this->getContainer()->get('portal'))) {
            return new \Espo\Core\Portal\Acl($this->getAclManager(), $this->getUser());
        }

        return new \Espo\Core\Acl($this->getAclManager(), $this->getUser());
    }

    /**
     * Get acl manager
     *
     * @return AclManager
     */
    protected function getAclManager()
    {
        return $this->getContainer()->get('aclManager');
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
