<?php

namespace Espo\AclPortal;

use \Espo\ORM\Entity;

class User extends \Espo\Core\AclPortal\Base
{
    public function checkIsOwner(\Espo\Entities\User $user, Entity $entity)
    {
        return $user->id === $entity->id;
    }
}

