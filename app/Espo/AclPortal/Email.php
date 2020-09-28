<?php

namespace Espo\AclPortal;

use \Espo\Entities\User as EntityUser;
use \Espo\ORM\Entity;

class Email extends \Espo\Core\AclPortal\Base
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
        if ($user->id === $entity->get('createdById')) {
            return true;
        }
        return false;
    }
}

