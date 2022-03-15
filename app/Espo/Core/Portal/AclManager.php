<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

namespace Espo\Core\Portal;

use Espo\Core\Container;
use Espo\Entities\Portal;
use \Espo\ORM\Entity;
use \Espo\Entities\User;
use \Espo\Core\Utils\Util;

class AclManager extends \Espo\Core\AclManager
{
    protected $tableClassName = '\\Espo\\Core\\AclPortal\\Table';

    protected $userAclClassName = '\\Espo\\Core\\Portal\\Acl';

    /**
     * @var Portal
     */
    protected $portal;

    /**
     * AclManager constructor.
     *
     * @param Container $container
     * @param Portal    $portal
     */
    public function __construct(Container $container, Portal $portal)
    {
        parent::__construct($container);

        $this->portal = $portal;
    }

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
            $portal = $this->portal;

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

    public function checkInAccount(User $user, Entity $entity, $action)
    {
        return $this->getImplementation($entity->getEntityType())->checkInAccount($user, $entity);
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

