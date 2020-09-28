<?php

namespace Espo\Acl;

use \Espo\ORM\Entity;
use \Espo\Entities\User as EntityUser;

class User extends \Espo\Core\Acl\Base
{
    public function checkIsOwner(\Espo\Entities\User $user, Entity $entity)
    {
        return $user->id === $entity->id;
    }

    public function checkEntityCreate(EntityUser $user, Entity $entity, $data)
    {
        if (!$user->isAdmin()) {
            return false;
        }
        return $this->checkEntity($user, $entity, $data, 'create');
    }

    public function checkEntityDelete(EntityUser $user, Entity $entity, $data)
    {
        if ($entity->id === 'system') {
            return false;
        }
        if (!$user->isAdmin()) {
            return false;
        }
        return parent::checkEntityDelete($user, $entity, $data);
    }

    public function checkEntityEdit(EntityUser $user, Entity $entity, $data)
    {
        if ($entity->id === 'system') {
            return false;
        }
        if (!$user->isAdmin()) {
            if ($user->id !== $entity->id) {
                return false;
            }
        }
        return $this->checkEntity($user, $entity, $data, 'edit');
    }
}
