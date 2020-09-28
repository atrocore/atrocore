<?php
declare(strict_types=1);

namespace Treo\Core\Loaders;

use Espo\Core\ORM\EntityManager;
use Espo\Entities\User;
use Espo\Core\Exceptions\Error;

/**
 * Preferences loader
 *
 * @author r.ratsun@zinitsolutions.com
 */
class Preferences extends Base
{

    /**
     * Load Preferences
     *
     * @return mixed
     *
     * @throws Error
     */
    public function load()
    {
        return $this
            ->getEntityManager()
            ->getEntity('Preferences', (!is_object($this->getUser())) ? $this->getUser() : $this->getUser()->id);
    }

    /**
     * Get entity manager
     *
     * @return EntityManager
     */
    protected function getEntityManager()
    {
        return $this->getContainer()->get('entityManager');
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
