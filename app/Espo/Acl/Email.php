<?php

namespace Espo\Acl;

use \Espo\Entities\User as EntityUser;
use \Espo\ORM\Entity;

class Email extends \Espo\Core\Acl\Base
{
    protected $ownerUserIdAttribute = 'usersIds';

    public function checkEntityRead(EntityUser $user, Entity $entity, $data)
    {
        if ($this->checkEntity($user, $entity, $data, 'read')) {
            return true;
        }

        if ($data === false) {
            return false;
        }
        if (is_object($data)) {
            if ($data->read === false || $data->read === 'no') {
                return false;
            }
        }

        if (!$entity->has('usersIds')) {
            $entity->loadLinkMultipleField('users');
        }
        $userIdList = $entity->get('usersIds');
        if (is_array($userIdList) && in_array($user->id, $userIdList)) {
            return true;
        }
        return false;
    }

    public function checkIsOwner(EntityUser $user, Entity $entity)
    {
        if ($user->id === $entity->get('assignedUserId')) {
            return true;
        }

        if ($user->id === $entity->get('createdById')) {
            return true;
        }

        if ($entity->hasLinkMultipleId('assignedUsers', $user->id)) {
            return true;
        }

        return false;
    }

    public function checkEntityDelete(EntityUser $user, Entity $entity, $data)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($data === false) {
            return false;
        }

        if ($data->delete === 'own') {
            if ($user->id === $entity->get('assignedUserId')) {
                return true;
            }

            if ($user->id === $entity->get('createdById')) {
                return true;
            }

            $assignedUserIdList = $entity->getLinkMultipleIdList('assignedUsers');
            if (count($assignedUserIdList) === 1 && $entity->hasLinkMultipleId('assignedUsers', $user->id)) {
                return true;
            }
            return false;
        }

        if ($this->checkEntity($user, $entity, $data, 'delete')) {
            return true;
        }

        if ($data->edit !== 'no' || $data->create !== 'no') {
            if ($entity->get('createdById') === $user->id) {
                if ($entity->get('status') !== 'Sent' && $entity->get('status') !== 'Archived') {
                    return true;
                }
            }
        }

        return false;
    }
}
