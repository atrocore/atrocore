<?php

namespace Espo\AclPortal;

use \Espo\Entities\User as EntityUser;
use \Espo\ORM\Entity;

class PhoneNumber extends \Espo\Core\AclPortal\Base
{
    public function checkEditInEntity(EntityUser $user, Entity $entity, Entity $excludeEntity)
    {
        $id = $entity->id;

        $isFobidden = false;

        $repository = $this->getEntityManager()->getRepository('PhoneNumber');

        if (!$user->isAdmin()) {
            $entityWithSameNumberList = $repository->getEntityListByPhoneNumberId($id, $excludeEntity);
            foreach ($entityWithSameNumberList as $e) {
                if (!$this->getAclManager()->check($user, $e, 'edit')) {
                    $isFobidden = true;
                    break;
                }
            }
        }

        return !$isFobidden;
    }
}
