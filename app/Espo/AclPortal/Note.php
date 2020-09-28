<?php

namespace Espo\AclPortal;

use \Espo\Entities\User as EntityUser;
use \Espo\ORM\Entity;

class Note extends \Espo\Core\AclPortal\Base
{
    public function checkIsOwner(EntityUser $user, Entity $entity)
    {
        if ($entity->get('type') === 'Post' && $user->id === $entity->get('createdById')) {
            return true;
        }
        return false;
    }
}

