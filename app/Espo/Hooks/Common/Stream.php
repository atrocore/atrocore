<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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

namespace Espo\Hooks\Common;

use Atro\ORM\DB\RDB\Mapper;
use Espo\ORM\Entity;

class Stream extends \Espo\Core\Hooks\Base
{
    protected $streamService = null;

    protected $hasStreamCache = array();

    protected $isLinkObservableInStreamCache = array();

    public static $order = 9;

    protected function init()
    {
        parent::init();
        $this->addDependency('serviceFactory');
    }

    protected function getServiceFactory()
    {
        return $this->getInjection('serviceFactory');
    }

    protected function getPreferences()
    {
        return $this->getInjection('container')->get('preferences');
    }

    protected function checkHasStream(Entity $entity)
    {
        $entityType = $entity->getEntityType();
        if (!array_key_exists($entityType, $this->hasStreamCache)) {
            $this->hasStreamCache[$entityType] = $this->getMetadata()->get("scopes.{$entityType}.stream");
        }
        return $this->hasStreamCache[$entityType];
    }

    protected function isLinkObservableInStream($scope, $link)
    {
        $key = $scope . '__' . $link;
        if (!array_key_exists($key, $this->isLinkObservableInStreamCache)) {
            $this->isLinkObservableInStreamCache[$key] =
                $this->getMetadata()->get(['scopes', $scope, 'stream']) &&
                $this->getMetadata()->get(['entityDefs', $scope, 'links', $link, 'audited']);
        }

        return $this->isLinkObservableInStreamCache[$key];
    }

    public function afterRemove(Entity $entity)
    {
        if($this->getMetadata()->get(['scopes', $entity->getEntityType(), 'type']) === 'Relation') {
            $this->handledStreamForRelationEntity($entity, 'Unrelate');
        }

        if ($this->checkHasStream($entity)) {
            $this->getStreamService()->unfollowAllUsersFromEntity($entity);
        }

        $connection = $this->getEntityManager()->getConnection();

        $connection->createQueryBuilder()
            ->update($connection->quoteIdentifier('note'))
            ->set('deleted', ':deleted')
            ->setParameter('deleted', true, Mapper::getParameterType(true))
            ->where("(related_id = :entityId AND related_type = :entityType) OR (parent_id = :entityId AND parent_type = :entityType)")
            ->setParameter('entityId', $entity->id)
            ->setParameter('entityType', $entity->getEntityType())
            ->executeQuery();
    }

    protected function handleCreateRelated(Entity $entity)
    {
        $linkDefs = $this->getMetadata()->get("entityDefs." . $entity->getEntityType() . ".links", array());

        $scopeNotifiedList = array();
        foreach ($linkDefs as $link => $defs) {
            if ($defs['type'] == 'belongsTo') {
                if (empty($defs['foreign']) || empty($defs['entity'])) {
                    continue;
                }
                $foreign = $defs['foreign'];
                $scope = $defs['entity'];
                $entityId = $entity->get($link . 'Id');
                if (!empty($scope) && !empty($entityId)) {
                    if (in_array($scope, $scopeNotifiedList) || !$this->isLinkObservableInStream($scope, $foreign)) {
                        continue;
                    }
                    $this->getStreamService()->noteCreateRelated($entity, $scope, $entityId);
                    $scopeNotifiedList[] = $scope;
                }
            } else if ($defs['type'] == 'belongsToParent') {
                if (empty($defs['foreign'])) {
                    continue;
                }
                $foreign = $defs['foreign'];
                $scope = $entity->get($link . 'Type');
                $entityId = $entity->get($link . 'Id');
                if (!empty($scope) && !empty($entityId)) {
                    if (in_array($scope, $scopeNotifiedList) || !$this->isLinkObservableInStream($scope, $foreign)) {
                        continue;
                    }
                    $this->getStreamService()->noteCreateRelated($entity, $scope, $entityId);
                    $scopeNotifiedList[] = $scope;

                }
            } else if ($defs['type'] == 'hasMany') {
                if (empty($defs['foreign']) || empty($defs['entity'])) {
                    continue;
                }
                $foreign = $defs['foreign'];
                $scope = $defs['entity'];
                $entityIds = $entity->get($link . 'Ids');
                if (!empty($scope) && is_array($entityIds) && !empty($entityIds)) {
                    if (in_array($scope, $scopeNotifiedList) || !$this->isLinkObservableInStream($scope, $foreign)) {
                        continue;
                    }
                    $entityId = $entityIds[0];
                    $this->getStreamService()->noteCreateRelated($entity, $scope, $entityId);
                    $scopeNotifiedList[] = $scope;
                }
            }
        }
    }

    protected function getAutofollowUserIdList(Entity $entity, array $ignoreList = array())
    {
        $userIdList = [];

        $rows = $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->select('user_id')
            ->from('autofollow')
            ->where("entity_type = :entityType")
            ->setParameter('entityType', $entity->getEntityType())
            ->fetchAllAssociative();

        foreach ($rows as $row) {
            $userId = $row['user_id'];
            if (in_array($userId, $ignoreList)) {
                continue;
            }
            $userIdList[] = $userId;
        }

        return $userIdList;
    }

    public function afterSave(Entity $entity, array $options = [])
    {
        // exit if importing
        if (!empty($this->getEntityManager()->getMemoryStorage()->get('importJobId'))) {
            return;
        }

        $entityType = $entity->getEntityType();

        if($this->getMetadata()->get(['scopes', $entityType, 'type']) === 'Relation') {
            $this->handledStreamForRelationEntity($entity);
        }

        if ($this->checkHasStream($entity)) {

            $hasAssignedUsersField = false;
            if ($entity->hasLinkMultipleField('assignedUsers')) {
                $hasAssignedUsersField = true;
            }

            if ($entity->isNew()) {
                $userIdList = [];

                $assignedUserId = $entity->get('assignedUserId');
                $createdById = $entity->get('createdById');

                $assignedUserIdList = [];
                if ($hasAssignedUsersField) {
                    $assignedUserIdList = $entity->getLinkMultipleIdList('assignedUsers');
                }

                if (
                    !$this->getUser()->isSystem()
                    &&
                    $createdById
                    &&
                    $createdById === $this->getUser()->id
                    &&
                    (
                        $this->getPreferences()->get('followCreatedEntities')
                        ||
                        (
                            is_array($this->getPreferences()->get('followCreatedEntityTypeList'))
                            &&
                            in_array($entityType, $this->getPreferences()->get('followCreatedEntityTypeList'))
                        )
                    )
                ) {
                    $userIdList[] = $createdById;
                }

                if ($hasAssignedUsersField) {
                    foreach ($assignedUserIdList as $userId) {
                        if (!empty($userId) && !in_array($userId, $userIdList)) {
                            $userIdList[] = $userId;
                        }
                    }
                }

                if (!empty($assignedUserId) && !in_array($assignedUserId, $userIdList)) {
                    $userIdList[] = $assignedUserId;
                }

                if (!empty($userIdList)) {
                    $this->getStreamService()->followEntityMass($entity, $userIdList);
                }

                if (empty($options['noStream']) && empty($options['silent'])) {
                    $this->getStreamService()->noteCreate($entity);
                }

                if (in_array($this->getUser()->id, $userIdList)) {
                	$entity->set('isFollowed', true);
                }

                $autofollowUserIdList = $this->getAutofollowUserIdList($entity, $userIdList);
                foreach ($autofollowUserIdList as $i => $userId) {
                    if (in_array($userId, $userIdList)) {
                        unset($autofollowUserIdList[$i]);
                    }
                }
                $autofollowUserIdList = array_values($autofollowUserIdList);

                if (!empty($autofollowUserIdList)) {
                    $job = $this->getEntityManager()->getEntity('Job');
                    $job->set(array(
                        'serviceName' => 'Stream',
                        'methodName' => 'afterRecordCreatedJob',
                        'data' => array(
                            'userIdList' => $autofollowUserIdList,
                            'entityType' => $entity->getEntityType(),
                            'entityId' => $entity->id
                        )
                    ));
                    $this->getEntityManager()->saveEntity($job);
                }
            } else {
                if (empty($options['noStream']) && empty($options['silent'])) {
                    $this->getStreamService()->handleAudited($entity);

                    $assignedUserIdList = [];
                    if ($hasAssignedUsersField) {
                        $assignedUserIdList = $entity->getLinkMultipleIdList('assignedUsers');
                    }

                    if ($hasAssignedUsersField) {
                        $fetchedAssignedUserIdList = $entity->getFetched('assignedUsersIds');
                        if (!is_array($fetchedAssignedUserIdList)) {
                            $fetchedAssignedUserIdList = [];
                        }
                        foreach ($assignedUserIdList as $userId) {
                            if (in_array($userId, $fetchedAssignedUserIdList)) {
                                continue;
                            }
                            $this->getStreamService()->followEntity($entity, $userId);
                            if ($this->getUser()->id === $userId) {
                                $entity->set('isFollowed', true);
                            }
                        }
                    }
                }

                $methodName = 'isChangedWithAclAffect';
                if (
                    (
                        method_exists($entity, $methodName) && $entity->$methodName()
                    )
                    ||
                    (
                        !method_exists($entity, $methodName)
                        &&
                        (
                            $entity->isAttributeChanged('assignedUserId')
                            ||
                            $entity->isAttributeChanged('teamsIds')
                            ||
                            $entity->isAttributeChanged('assignedUsersIds')
                        )
                    )
                ) {
                    $job = $this->getEntityManager()->getEntity('Job');
                    $job->set(array(
                        'serviceName' => 'Stream',
                        'methodName' => 'controlFollowersJob',
                        'data' => array(
                            'entityType' => $entity->getEntityType(),
                            'entityId' => $entity->id
                        )
                    ));
                    $this->getEntityManager()->saveEntity($job);
                }
            }
        }

        if ($entity->isNew() && empty($options['noStream']) && empty($options['silent']) && $this->getMetadata()->get(['scopes', $entityType, 'object'])) {
            $this->handleCreateRelated($entity);
        }
    }

    public function afterRelate(Entity $entity, array $options = array(), array $data = array())
    {
    }

    public function afterUnrelate(Entity $entity, array $options = array(), array $data = array())
    {
    }

    protected function handledStreamForRelationEntity(Entity $entity, string $type= 'Relate') : void
    {
        $entityType = $entity->getEntityType();
        $relationFields = $this->getEntityManager()->getRepository($entityType)->getRelationFields();
        $links = [];
        foreach ($relationFields as $relationField) {
            $foreignEntity = $this->getMetadata()->get(['entityDefs', $entityType, 'links', $relationField, 'entity']);
            foreach ($this->getMetadata()->get(['entityDefs', $foreignEntity, 'links']) as $link => $linkDefs){
                if(!empty($linkDefs['relationName']) && ucfirst($linkDefs['relationName']) === $entityType) {
                    $links[$relationField] = $link;
                }
            }
        }
        if(!empty($links[$relationFields[0]])){

            $this->processRelationEntityNote($entity->get($relationFields[0]), $entity->get($relationFields[1]), $links[$relationFields[0]], $type);
        }
        if(!empty($links[$relationFields[1]])){

            $this->processRelationEntityNote($entity->get($relationFields[1]), $entity->get($relationFields[0]), $links[$relationFields[1]], $type);
        }
    }
    protected function processRelationEntityNote(Entity $entity, Entity $foreignEntity, $link, $type = 'Relate') : void
    {

        $entityType = $entity->getEntityType();
        if ($this->getMetadata()->get(['entityDefs', $entityType, 'links', $link, 'audited'])) {

            $note = $this->getEntityManager()->getEntity('Note');
            $note->set(array(
                'type' => $type,
                'parentId' => $entity->id,
                'parentType' => $entityType,
                'relatedId' => $foreignEntity->id,
                'relatedType' => $foreignEntity->getEntityType()
            ));
            $this->getEntityManager()->saveEntity($note);
        }
    }

    protected function getStreamService()
    {
        if (empty($this->streamService)) {
            $this->streamService = $this->getServiceFactory()->create('Stream');
        }
        return $this->streamService;
    }
}
