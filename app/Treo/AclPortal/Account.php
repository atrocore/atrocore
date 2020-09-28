<?php

namespace Treo\AclPortal;

use \Espo\Entities\User;
use \Espo\ORM\Entity;

class Account extends \Espo\Core\AclPortal\Base
{
    /**
     * @param User $user
     * @param Entity $entity
     *
     * @return bool
     */
    public function checkInAccount(User $user, Entity $entity)
    {
        $accountIdList = $user->getLinkMultipleIdList('accounts');
        if (count($accountIdList)) {
            if (in_array($entity->id, $accountIdList)) {
                return true;
            }
        }
        return false;
    }
}
