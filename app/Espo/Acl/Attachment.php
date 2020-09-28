<?php

namespace Espo\Acl;

use \Espo\Entities\User as EntityUser;
use \Espo\ORM\Entity;

class Attachment extends \Espo\Core\Acl\Base
{
    public function checkEntityRead(EntityUser $user, Entity $entity, $data)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($entity->get('parentType') === 'Settings') {
            return true;
        }

        $parent = null;
        $hasParent = false;
        if ($entity->get('parentId') && $entity->get('parentType')) {
            $hasParent = true;
            $parent = $this->getEntityManager()->getEntity($entity->get('parentType'), $entity->get('parentId'));
        } else if ($entity->get('relatedId') && $entity->get('relatedType')) {
            $hasParent = true;
            $parent = $this->getEntityManager()->getEntity($entity->get('relatedType'), $entity->get('relatedId'));
        }

        if ($hasParent) {
            if ($parent) {
                if ($parent->getEntityType() === 'Note') {
                    if ($parent->get('parentId') && $parent->get('parentType')) {
                        $parentOfParent = $this->getEntityManager()->getEntity($parent->get('parentType'), $parent->get('parentId'));
                        if ($parentOfParent && $this->getAclManager()->checkEntity($user, $parentOfParent)) {
                            return true;
                        }
                    } else {
                        return true;
                    }
                } else {
                    if ($this->getAclManager()->checkEntity($user, $parent)) {
                        return true;
                    }
                }
            }
        } else {
            return true;
        }

        if ($this->checkEntity($user, $entity, $data, 'read')) {
            return true;
        }

        return false;
    }

    public function checkIsOwner(EntityUser $user, Entity $entity)
    {
        if ($user->id === $entity->get('createdById')) {
            return true;
        }
        return false;
    }
}

