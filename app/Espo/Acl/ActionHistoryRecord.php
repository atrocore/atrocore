<?php

namespace Espo\Acl;

use \Espo\Entities\User as EntityUser;
use \Espo\ORM\Entity;

class ActionHistoryRecord extends \Espo\Core\Acl\Base
{
    public function checkIsOwner(EntityUser $user, Entity $entity)
    {
        return $entity->get('userId') === $user->id;
    }
}

