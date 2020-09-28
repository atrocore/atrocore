<?php

namespace Espo\Acl;

use \Espo\Entities\User as EntityUser;
use \Espo\ORM\Entity;

class Notification extends \Espo\Core\Acl\Base
{
    public function checkIsOwner(EntityUser $user, Entity $entity)
    {
        if ($user->id === $entity->get('userId')) {
            return true;
        }
        return false;
    }
}

