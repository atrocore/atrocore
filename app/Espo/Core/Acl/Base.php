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
 */

namespace Espo\Core\Acl;

use \Espo\Core\Interfaces\Injectable;

use \Espo\Entities\User;
use \Espo\ORM\Entity;

class Base implements Injectable
{
    protected $dependencies = array(
        'config',
        'entityManager',
        'aclManager'
    );

    protected $scope;

    protected $injections = array();

    protected $ownerUserIdAttribute = null;

    public function inject($name, $object)
    {
        $this->injections[$name] = $object;
    }

    public function __construct($scope)
    {
        $this->init();
        $this->scope = $scope;
    }

    protected function init()
    {
    }

    protected function getInjection($name)
    {
        return $this->injections[$name];
    }

    protected function addDependencyList(array $list)
    {
        foreach ($list as $item) {
            $this->addDependency($item);
        }
    }

    protected function addDependency($name)
    {
        $this->dependencies[] = $name;
    }

    public function getDependencyList()
    {
        return $this->dependencies;
    }

    protected function getConfig()
    {
        return $this->getInjection('config');
    }

    protected function getEntityManager()
    {
        return $this->getInjection('entityManager');
    }

    protected function getAclManager()
    {
        return $this->getInjection('aclManager');
    }

    public function checkReadOnlyTeam(User $user, $data)
    {
        if (empty($data) || !is_object($data) || !isset($data->read)) {
            return false;
        }
        return $data->read === 'team';
    }

    public function checkReadNo(User $user, $data)
    {
        if (empty($data) || !is_object($data) || !isset($data->read)) {
            return false;
        }
        return $data->read === 'no';
    }

    public function checkReadOnlyOwn(User $user, $data)
    {
        if (empty($data) || !is_object($data) || !isset($data->read)) {
            return false;
        }
        return $data->read === 'own';
    }

    public function checkEntity(User $user, Entity $entity, $data, $action)
    {
        if ($user->isAdmin()) {
            return true;
        }
        return $this->checkScope($user, $data, $action, $entity);
    }

    public function checkScope(User $user, $data, $action = null, Entity $entity = null, $entityAccessData = array())
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (is_null($data)) {
            return false;
        }
        if ($data === false) {
            return false;
        }
        if ($data === true) {
            return true;
        }
        if (is_string($data)) {
            return true;
        }

        $isOwner = null;
        if (isset($entityAccessData['isOwner'])) {
            $isOwner = $entityAccessData['isOwner'];
        }
        $inTeam = null;
        if (isset($entityAccessData['inTeam'])) {
            $inTeam = $entityAccessData['inTeam'];
        }

        if (is_null($action)) {
            return true;
        }

        if (!isset($data->$action)) {
            if ($action === 'stream') {
                return true;
            }

            return false;
        }

        $value = $data->$action;

        if ($value === 'all' || $value === 'yes' || $value === true) {
            return true;
        }

        if (!$value || $value === 'no') {
            return false;
        }

        if (is_null($isOwner)) {
            if ($entity) {
                $isOwner = $this->checkIsOwner($user, $entity);
            } else {
                return true;
            }
        }

        if ($isOwner) {
            if ($value === 'own' || $value === 'team') {
                return true;
            }
        }
        if (is_null($inTeam) && $entity) {
            $inTeam = $this->checkInTeam($user, $entity);
        }

        if ($inTeam) {
            if ($value === 'team') {
                return true;
            }
        }
        return false;
    }

    public function checkIsOwner(User $user, Entity $entity)
    {
        if ($entity->hasAttribute('ownerUserId')) {
            if ($entity->has('ownerUserId')) {
                if ($user->id === $entity->get('ownerUserId')) {
                    return true;
                }
            }
        }


        if ($entity->hasAttribute('assignedUserId')) {
            if ($entity->has('assignedUserId')) {
                if ($user->id === $entity->get('assignedUserId')) {
                    return true;
                }
            }
        }

        if ($entity->hasAttribute('createdById')) {
            if ($entity->has('createdById')) {
                if ($user->id === $entity->get('createdById')) {
                    return true;
                }
            }
        }

        if ($entity->hasLinkMultipleField('assignedUsers')) {
            if ($entity->hasLinkMultipleId('assignedUsers', $user->id)) {
                return true;
            }
        }

        return false;
    }

    public function checkInTeam(User $user, Entity $entity)
    {
        $userTeamIdList = $user->getLinkMultipleIdList('teams');

        if (!$entity->hasRelation('teams') || !$entity->hasAttribute('teamsIds')) {
            return false;
        }

        $entityTeamIdList = $entity->getLinkMultipleIdList('teams');

        if (empty($entityTeamIdList)) {
            return false;
        }

        foreach ($userTeamIdList as $id) {
            if (in_array($id, $entityTeamIdList)) {
                return true;
            }
        }
        return false;
    }

    public function checkEntityRead(User $user, Entity $entity, $data)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($this->isRelationEntity($entity->getEntityType())) {
            return $this->checkEntityRelation($user, $entity, 'read') && $this->checkEntity($user, $entity, $data, 'read');
        }

        if ($this->checkEntity($user, $entity, $data, 'read')) {
            return true;
        }

        return false;
    }

    public function checkEntityCreate(User $user, Entity $entity, $data)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($this->checkEntity($user, $entity, $data, 'create')) {
            return true;
        }

        return false;
    }

    public function checkEntityEdit(User $user, Entity $entity, $data)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($this->isRelationEntity($entity->getEntityType())) {
            return $this->checkEntityRelation($user, $entity, 'read') && $this->checkEntity($user, $entity, $data, 'edit');
        }

        if ($this->checkEntity($user, $entity, $data, 'edit')) {
            return true;
        }

        return false;
    }

    public function checkEntityDelete(User $user, Entity $entity, $data)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($this->isRelationEntity($entity->getEntityType())) {
            return $this->checkEntityRelation($user, $entity, 'read') && $this->checkEntity($user, $entity, $data, 'delete');
        }

        if ($this->checkEntity($user, $entity, $data, 'delete')) {
            return true;
        }

        return false;
    }

    public function getOwnerUserIdAttribute(Entity $entity)
    {
        if ($this->ownerUserIdAttribute) {
            return $this->ownerUserIdAttribute;
        }

        if ($entity->hasLinkMultipleField('assignedUsers')) {
            return 'assignedUsersIds';
        }

        if ($entity->hasAttribute('assignedUserId')) {
            return 'assignedUserId';
        }

        if ($entity->hasAttribute('createdById')) {
            return 'createdById';
        }
    }

    protected function checkEntityRelation(User $user, $entity, $action): bool
    {
        foreach ($this->getRelationEntities($entity->getEntityType()) as $link => $scope) {
            $relEntity = $entity->get($link);

            if ($relEntity && !$this->getAclManager()->checkEntity($user, $relEntity, $action)) {
                return false;
            }
        }

        return true;
    }

    public function isRelationEntity(string $entityName): bool
    {
        return $this->getEntityManager()->getEspoMetadata()->get(['scopes', $entityName, 'type'], 'Base') == 'Relation';
    }

    public function getRelationEntities(string $entityName): array
    {
        $result = [];

        $metadata = $this->getEntityManager()->getEspoMetadata();

        foreach ($metadata->get(['entityDefs', $entityName, 'fields']) as $field => $defs) {
            if (array_key_exists('relationField', $defs)) {
                $relationEntityName = $metadata->get(['entityDefs', $entityName, 'links', $field, 'entity']);

                if (!empty($relationEntityName)) {
                    $result[$field] = $relationEntityName;
                }
            }
        }

        return $result;
    }
}
