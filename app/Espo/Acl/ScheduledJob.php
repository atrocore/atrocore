<?php

namespace Espo\Acl;

use \Espo\Entities\User as EntityUser;
use \Espo\ORM\Entity;

class ScheduledJob extends \Espo\Core\Acl\Base
{
    public function checkEntityRead(EntityUser $user, Entity $entity, $data)
    {
        if ($entity->get('isInternal')) return false;
        return $this->checkEntity($user, $entity, $data, 'read');
    }

    public function checkEntityEdit(EntityUser $user, Entity $entity, $data)
    {
        if ($entity->get('isInternal')) return false;
        return $this->checkEntity($user, $entity, $data, 'edit');
    }

    public function checkEntityDelete(EntityUser $user, Entity $entity, $data)
    {
        if ($entity->get('isInternal')) return false;
        return $this->checkEntity($user, $entity, $data, 'delete');
    }

    public function checkEntityCreate(EntityUser $user, Entity $entity, $data)
    {
        if ($entity->get('isInternal')) return false;
        return $this->checkEntity($user, $entity, $data, 'create');
    }
}

