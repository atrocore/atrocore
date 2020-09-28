<?php

namespace Treo\AclPortal;

use \Espo\Entities\User;
use \Espo\ORM\Entity;

class Contact extends \Espo\Core\AclPortal\Base
{
    /**
     * @param User $user
     * @param Entity $entity
     *
     * @return bool
     */
    public function checkIsOwnContact(User $user, Entity $entity)
    {
        $contactId = $user->get('contactId');
        if ($contactId) {
            if ($entity->id === $contactId) {
                return true;
            }
        }
        return false;
    }
}
