<?php

namespace Espo\Acl;

use \Espo\Entities\User as EntityUser;
use \Espo\ORM\Entity;

class EmailFilter extends \Espo\Core\Acl\Base
{
    public function checkIsOwner(EntityUser $user, Entity $entity)
    {
        if ($entity->has('parentId') && $entity->has('parentType')) {
            $parentType = $entity->get('parentType');
            $parentId = $entity->get('parentId');
            if (!$parentType || !$parentId) return;

            $parent = $this->getEntityManager()->getEntity($parentType, $parentId);

            if ($parent->getEntityType() === 'User') {
                return $parent->id === $user->id;
            }
            if ($parent && $parent->has('assignedUserId') && $parent->get('assignedUserId') === $user->id) {
                return true;
            }
        }
        return;
    }
}

