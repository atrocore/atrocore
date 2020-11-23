<?php

declare(strict_types=1);

namespace Espo\Repositories;

use Espo\Core\AclManager;
use Espo\ORM\Entity;

/**
 * Class Role
 *
 * @package Espo\Repositories
 */
class Role extends \Espo\Core\ORM\Repositories\RDB
{
    protected function init()
    {
        parent::init();
        $this->addDependency('container');
    }

    /**
     * @param Entity $entity
     * @param array $options
     */
    public function afterSave(Entity $entity, array $options = array())
    {
        parent::afterSave($entity, $options);

        $this
            ->getAclManager()
            ->clearAclCache();
    }

    /**
     * @return AclManager
     */
    protected function getAclManager(): AclManager
    {
        return $this->getInjection('container')->get('aclManager');
    }
}
