<?php

namespace Espo\Acl;

use \Espo\Entities\User as EntityUser;
use \Espo\ORM\Entity;

class Import extends \Espo\Core\Acl\Base
{

    public function checkEntityRead(EntityUser $user, Entity $entity, $data)
    {
        if ($user->isAdmin()) return true;
        if ($user->id === $entity->get('createdById')) return true;

        return false;
    }

    public function checkEntityDelete(EntityUser $user, Entity $entity, $data)
    {
        if ($user->isAdmin()) return true;
        if ($user->id === $entity->get('createdById')) return true;

        return false;
    }
}
