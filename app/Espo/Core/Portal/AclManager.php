<?php

namespace Espo\Core\Portal;

use \Espo\ORM\Entity;
use \Espo\Entities\User;
use \Espo\Core\Utils\Util;

class AclManager extends \Espo\Core\AclManager
{
    protected $tableClassName = '\\Espo\\Core\\AclPortal\\Table';

    protected $userAclClassName = '\\Espo\\Core\\Portal\\Acl';

    public function getImplementation($scope)
    {
        if (empty($this->implementationHashMap[$scope])) {
            $normalizedName = Util::normilizeClassName($scope);

            $className = '\\Espo\\Custom\\AclPortal\\' . $normalizedName;
            if (!class_exists($className)) {
                $moduleName = $this->getMetadata()->getScopeModuleName($scope);
                if ($moduleName) {
                    $className = '\\' . $moduleName . '\\AclPortal\\' . $normalizedName;
                } else {
                    $className = '\\Espo\\AclPortal\\' . $normalizedName;
                }
                if (!class_exists($className)) {
                    $className = '\\Espo\\Core\\AclPortal\\Base';
                }
            }

            if (class_exists($className)) {
                $acl = new $className($scope);
                $dependencies = $acl->getDependencyList();
                foreach ($dependencies as $name) {
                    $acl->inject($name, $this->getContainer()->get($name));
                }
                $this->implementationHashMap[$scope] = $acl;
            } else {
                throw new Error();
            }
        }

        return $this->implementationHashMap[$scope];
    }

    protected function getTable(User $user)
    {
        $key = $user->id;
        if (empty($key)) {
            $key = spl_object_hash($user);
        }

        if (empty($this->tableHashMap[$key])) {
            $config = $this->getContainer()->get('config');
            $fileManager = $this->getContainer()->get('fileManager');
            $metadata = $this->getContainer()->get('metadata');
            $fieldManager = $this->getContainer()->get('fieldManagerUtil');
            $portal = $this->getContainer()->get('portal');

            $this->tableHashMap[$key] = new $this->tableClassName($user, $portal, $config, $fileManager, $metadata, $fieldManager);
        }

        return $this->tableHashMap[$key];
    }

    public function checkReadOnlyAccount(User $user, $scope)
    {
        if ($user->isAdmin()) {
            return false;
        }
        $data = $this->getTable($user)->getScopeData($scope);
        return $this->getImplementation($scope)->checkReadOnlyAccount($user, $data);
    }

    public function checkReadOnlyContact(User $user, $scope)
    {
        if ($user->isAdmin()) {
            return false;
        }
        $data = $this->getTable($user)->getScopeData($scope);
        return $this->getImplementation($scope)->checkReadOnlyContact($user, $data);
    }

    public function checkInAccount(User $user, Entity $entity, $action)
    {
        return $this->getImplementation($entity->getEntityType())->checkInAccount($user, $entity);
    }

    public function checkIsOwnContact(User $user, Entity $entity, $action)
    {
        return $this->getImplementation($entity->getEntityType())->checkIsOwnContact($user, $entity);
    }

    public function checkReadOnlyTeam(User $user, $scope)
    {
        if ($this->checkUserIsNotPortal($user)) {
            $scope = $this->getTable($user)->getScopeData($scope);
        }

        return parent::checkReadOnlyTeam($user, $scope);
    }

    public function checkReadNo(User $user, $scope)
    {
        if ($this->checkUserIsNotPortal($user)) {
            $scope = $this->getTable($user)->getScopeData($scope);
        }

        return parent::checkReadNo($user, $scope);
    }

    public function checkReadOnlyOwn(User $user, $scope)
    {
        if ($this->checkUserIsNotPortal($user)) {
            $scope = $this->getTable($user)->getScopeData($scope);
        }

        return parent::checkReadOnlyOwn($user, $scope);
    }

    protected function checkUserIsNotPortal($user)
    {
        return !$user->get('isPortalUser');
    }
}

