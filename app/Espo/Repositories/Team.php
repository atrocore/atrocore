<?php

declare(strict_types=1);

namespace Espo\Repositories;

use Espo\Core\AclManager;
use Espo\ORM\Entity;

/**
 * Class Team
 * @package Espo\Repositories
 */
class Team extends \Espo\Core\ORM\Repositories\RDB
{
    protected function init()
    {
        parent::init();
        $this->addDependency('container');
    }

    /**
     * @param Entity $entity
     *
     * @param array $options
     */
    protected function afterSave(Entity $entity, array $options = array())
    {
        parent::afterSave($entity, $options);

        if ($entity->isAttributeChanged('rolesIds')) {
            $this
                ->getAclManager()
                ->clearAclCache();
        }
    }

    /**
     * @param Entity $entity
     * @param $relationName
     * @param $foreign
     * @param null $data
     * @param array $options
     */
    protected function afterRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = array())
    {
        parent::afterRelate($entity, $relationName, $foreign, $data, $options);

        if ($relationName == 'users') {
            $this
                ->getAclManager()
                ->clearAclCache();
        }
    }

    /**
     * @param Entity $entity
     * @param $relationName
     * @param $foreign
     * @param array $options
     */
    protected function afterUnrelate(Entity $entity, $relationName, $foreign, array $options = array())
    {
        parent::afterUnrelate($entity, $relationName, $foreign, $options);

        if ($relationName == 'users') {
            $this
                ->getAclManager()
                ->clearAclCache();
        }
    }

    /**
     * @return AclManager
     */
    protected function getAclManager(): AclManager
    {
        return $this->getInjection('container')->get('aclManager');
    }
}
