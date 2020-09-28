<?php

namespace Espo\Acl;

use \Espo\Entities\User as EntityUser;
use \Espo\ORM\Entity;

class EmailAddress extends \Espo\Core\Acl\Base
{
    public function checkEditInEntity(EntityUser $user, Entity $entity, Entity $excludeEntity)
    {
        $id = $entity->id;

        $isFobidden = false;

        $repository = $this->getEntityManager()->getRepository('EmailAddress');

        if (!$user->isAdmin()) {
            $entityWithSameAddressList = $repository->getEntityListByAddressId($id, $excludeEntity);
            foreach ($entityWithSameAddressList as $e) {
                if (!$this->getAclManager()->check($user, $e, 'edit')) {
                    $isFobidden = true;
                    if (
                        $e->get('isPortalUser') && $excludeEntity->getEntityType() === 'Contact' &&
                        $e->get('contactId') === $excludeEntity->id
                    ) {
                        $isFobidden = false;
                    }
                    if ($isFobidden) break;
                }
            }
        }
        return !$isFobidden;
    }
}

