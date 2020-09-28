<?php

namespace Espo\Core\Portal;

use \Espo\ORM\Entity;
use \Espo\Entities\User;

class Acl extends \Espo\Core\Acl
{
    public function checkReadOnlyAccount($scope)
    {
        return $this->getAclManager()->checkReadOnlyAccount($this->getUser(), $scope);
    }

    public function checkReadOnlyContact($scope)
    {
        return $this->getAclManager()->checkReadOnlyContact($this->getUser(), $scope);
    }

    public function checkInAccount(Entity $entity)
    {
        return $this->getAclManager()->checkInAccount($this->getUser(), $entity);
    }

    public function checkIsOwnContact(Entity $entity)
    {
        return $this->getAclManager()->checkIsOwnContact($this->getUser(), $entity);
    }
}

