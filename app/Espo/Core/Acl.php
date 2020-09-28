<?php

namespace Espo\Core;

use \Espo\ORM\Entity;
use \Espo\Entities\User;

class Acl
{
    private $user;

    private $aclManager;

    public function __construct(AclManager $aclManager, User $user)
    {
        $this->aclManager = $aclManager;
        $this->user = $user;
    }

    protected function getAclManager()
    {
        return $this->aclManager;
    }

    protected function getUser()
    {
        return $this->user;
    }

    public function getMap()
    {
        return $this->getAclManager()->getMap($this->getUser());
    }

    public function getLevel($scope, $action)
    {
        return $this->getAclManager()->getLevel($this->getUser(), $scope, $action);
    }

    public function get($permission)
    {
        return $this->getAclManager()->get($this->getUser(), $permission);
    }

    public function checkReadNo($scope)
    {
        return $this->getAclManager()->checkReadNo($this->getUser(), $scope);
    }

    public function checkReadOnlyTeam($scope)
    {
        return $this->getAclManager()->checkReadOnlyTeam($this->getUser(), $scope);
    }

    public function checkReadOnlyOwn($scope)
    {
        return $this->getAclManager()->checkReadOnlyOwn($this->getUser(), $scope);
    }

    public function check($subject, $action = null)
    {
        return $this->getAclManager()->check($this->getUser(), $subject, $action);
    }

    public function checkScope($scope, $action = null)
    {
        return $this->getAclManager()->checkScope($this->getUser(), $scope, $action);
    }

    public function checkEntity(Entity $entity, $action = 'read')
    {
        return $this->getAclManager()->checkEntity($this->getUser(), $entity, $action);
    }

    public function checkUser($permission, User $entity)
    {
        return $this->getAclManager()->checkUser($this->getUser(), $permission, $entity);
    }

    public function checkIsOwner(Entity $entity)
    {
        return $this->getAclManager()->checkIsOwner($this->getUser(), $entity);
    }

    public function checkInTeam(Entity $entity)
    {
        return $this->getAclManager()->checkInTeam($this->getUser(), $entity);
    }

    public function getScopeForbiddenAttributeList($scope, $action = 'read', $thresholdLevel = 'no')
    {
        return $this->getAclManager()->getScopeForbiddenAttributeList($this->getUser(), $scope, $action, $thresholdLevel);
    }

    public function getScopeForbiddenFieldList($scope, $action = 'read', $thresholdLevel = 'no')
    {
        return $this->getAclManager()->getScopeForbiddenFieldList($this->getUser(), $scope, $action, $thresholdLevel);
    }

    public function checkUserPermission($target, $permissionType = 'userPermission')
    {
        return $this->getAclManager()->checkUserPermission($this->getUser(), $target, $permissionType);
    }

    public function checkAssignmentPermission($target)
    {
        return $this->getAclManager()->checkAssignmentPermission($this->getUser(), $target);
    }
}

