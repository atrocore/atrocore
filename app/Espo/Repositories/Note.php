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

declare(strict_types=1);

namespace Espo\Repositories;

use Espo\Core\Acl;
use Espo\Core\AclManager;
use Espo\Core\ORM\Repositories\RDB;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

/**
 * Class Note
 */
class Note extends RDB
{
    /**
     * @var null|\Espo\Services\Notification
     */
    protected $notificationService = null;

    /**
     * @param Entity $entity
     * @param array  $options
     */
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('type') == 'Post') {
            $this->addMentionData($entity);
        }

        parent::beforeSave($entity, $options);
    }

    /**
     * @param Entity $entity
     * @param array  $options
     */
    protected function afterSave(Entity $entity, array $options = [])
    {
        $this->notifyAboutMention($entity);
        $this->sendNotifications($entity);

        parent::afterSave($entity, $options);
    }

    protected function addMentionData(Entity $entity): void
    {
        $post = $entity->get('post');

        $mentionData = new \stdClass();

        $previousMentionList = array();
        if (!$entity->isNew()) {
            $data = $entity->get('data');
            if (!empty($data) && !empty($data->mentions)) {
                $previousMentionList = array_keys(get_object_vars($data->mentions));
            }
        }

        preg_match_all('/(@[\w@.-]+)/', $post, $matches);

        $mentionCount = 0;

        if (is_array($matches) && !empty($matches[0]) && is_array($matches[0])) {
            foreach ($matches[0] as $item) {
                $userName = substr($item, 1);
                $user = $this->getEntityManager()->getRepository('User')->where(array('userName' => $userName))->findOne();
                if ($user) {
                    if (!$this->getAcl()->checkUser('assignmentPermission', $user)) {
                        continue;
                    }
                    $m = array(
                        'id'       => $user->id,
                        'name'     => $user->get('name'),
                        'userName' => $user->get('userName'),
                        '_scope'   => $user->getEntityName()
                    );
                    $mentionData->$item = (object)$m;
                    $mentionCount++;
                    if (!in_array($item, $previousMentionList)) {
                        if ($user->id == $this->getUser()->id) {
                            continue;
                        }
                        $entity->addNotifiedUserId($user->id);
                    }
                }
            }
        }

        $data = $entity->get('data');
        if (empty($data)) {
            $data = new \stdClass();
        }
        if ($mentionCount) {
            $data->mentions = $mentionData;
        } else {
            unset($data->mentions);
        }

        $entity->set('data', $data);
    }

    protected function notifyAboutMention(Entity $entity): void
    {
        if (empty($entity->get('data')) || empty($entity->get('data')->mentions)) {
            return;
        }

        $parent = null;
        if ($entity->get('parentId') && $entity->get('parentType')) {
            $parent = $this->getEntityManager()->getEntity($entity->get('parentType'), $entity->get('parentId'));
        }

        foreach ($entity->get('data')->mentions as $mention) {
            if (empty($user = $this->getEntityManager()->getEntity('User', $mention->id))) {
                continue 1;
            }

            if ($user->get('isPortalUser')) {
                continue 1;
            }

            if ($parent && !$this->getAclManager()->check($user, $parent, 'stream')) {
                continue 1;
            }

            $this->getNotificationService()->notifyAboutMentionInPost($user->id, $entity->id);
        }
    }

    protected function sendNotifications(Entity $entity): void
    {
        if ($entity->isNew()) {
            $parentType = $entity->get('parentType');
            $parentId = $entity->get('parentId');
            $superParentType = $entity->get('superParentType');
            $superParentId = $entity->get('superParentId');

            $notifyUserIdList = [];

            if ($parentType && $parentId) {
                $userList = $this->getSubscriberList($parentType, $parentId, $entity->get('isInternal'));
                $userIdMetList = [];
                foreach ($userList as $user) {
                    $userIdMetList[] = $user->id;
                }
                if ($superParentType && $superParentId) {
                    $additionalUserList = $this->getSubscriberList($superParentType, $superParentId, $entity->get('isInternal'));
                    foreach ($additionalUserList as $user) {
                        if ($user->isPortal()) {
                            continue;
                        }
                        if (in_array($user->id, $userIdMetList)) {
                            continue;
                        }
                        $userIdMetList[] = $user->id;
                        $userList[] = $user;
                    }
                }

                if ($entity->get('relatedType')) {
                    $targetType = $entity->get('relatedType');
                } else {
                    $targetType = $parentType;
                }

                $skipAclCheck = false;
                if (!$entity->isAclProcessed()) {
                    $skipAclCheck = true;
                } else {
                    $teamIdList = $entity->getLinkMultipleIdList('teams');
                    $userIdList = $entity->getLinkMultipleIdList('users');
                }

                foreach ($userList as $user) {
                    if ($skipAclCheck) {
                        $notifyUserIdList[] = $user->id;
                        continue;
                    }
                    if ($user->isAdmin()) {
                        $notifyUserIdList[] = $user->id;
                        continue;
                    }

                    if ($user->isPortal()) {
                        if ($entity->get('relatedType')) {
                            continue;
                        } else {
                            $notifyUserIdList[] = $user->id;
                        }
                        continue;
                    }

                    $level = $this->getInternalAclManager()->getLevel($user, $targetType, 'read');

                    if ($level === 'all') {
                        $notifyUserIdList[] = $user->id;
                        continue;
                    } else {
                        if ($level === 'team') {
                            if (in_array($user->id, $userIdList)) {
                                $notifyUserIdList[] = $user->id;
                                continue;
                            }

                            if (!empty($teamIdList)) {
                                $userTeamIdList = $user->getLinkMultipleIdList('teams');
                                foreach ($teamIdList as $teamId) {
                                    if (in_array($teamId, $userTeamIdList)) {
                                        $notifyUserIdList[] = $user->id;
                                        break;
                                    }
                                }
                            }
                            continue;
                        } else {
                            if ($level === 'own') {
                                if (in_array($user->id, $userIdList)) {
                                    $notifyUserIdList[] = $user->id;
                                    continue;
                                }
                            }
                        }
                    }
                }

            } else {
                $targetType = $entity->get('targetType');
                if ($targetType === 'users') {
                    $targetUserIdList = $entity->get('usersIds');
                    if (is_array($targetUserIdList)) {
                        foreach ($targetUserIdList as $userId) {
                            if ($userId === $this->getUser()->id) {
                                continue;
                            }
                            if (in_array($userId, $notifyUserIdList)) {
                                continue;
                            }
                            $notifyUserIdList[] = $userId;
                        }
                    }
                } else {
                    if ($targetType === 'teams') {
                        $targetTeamIdList = $entity->get('teamsIds');
                        if (is_array($targetTeamIdList)) {
                            foreach ($targetTeamIdList as $teamId) {
                                $team = $this->getEntityManager()->getEntity('Team', $teamId);
                                if (!$team) {
                                    continue;
                                }
                                $targetUserList = $this->getEntityManager()->getRepository('Team')->findRelated(
                                    $team, 'users', array(
                                        'whereClause' => array(
                                            'isActive' => true
                                        )
                                    )
                                );
                                foreach ($targetUserList as $user) {
                                    if ($user->id === $this->getUser()->id) {
                                        continue;
                                    }
                                    if (in_array($user->id, $notifyUserIdList)) {
                                        continue;
                                    }
                                    $notifyUserIdList[] = $user->id;
                                }
                            }
                        }
                    } else {
                        if ($targetType === 'portals') {
                            $targetPortalIdList = $entity->get('portalsIds');
                            if (is_array($targetPortalIdList)) {
                                foreach ($targetPortalIdList as $portalId) {
                                    $portal = $this->getEntityManager()->getEntity('Portal', $portalId);
                                    if (!$portal) {
                                        continue;
                                    }
                                    $targetUserList = $this->getEntityManager()->getRepository('Portal')->findRelated(
                                        $portal, 'users', array(
                                            'whereClause' => array(
                                                'isActive' => true
                                            )
                                        )
                                    );
                                    foreach ($targetUserList as $user) {
                                        if ($user->id === $this->getUser()->id) {
                                            continue;
                                        }
                                        if (in_array($user->id, $notifyUserIdList)) {
                                            continue;
                                        }
                                        $notifyUserIdList[] = $user->id;
                                    }
                                }
                            }
                        } else {
                            if ($targetType === 'all') {
                                $targetUserList = $this->getEntityManager()->getRepository('User')->find(
                                    array(
                                        'whereClause' => array(
                                            'isActive'     => true,
                                            'isPortalUser' => false
                                        )
                                    )
                                );
                                foreach ($targetUserList as $user) {
                                    if ($user->id === $this->getUser()->id) {
                                        continue;
                                    }
                                    $notifyUserIdList[] = $user->id;
                                }
                            }
                        }
                    }
                }
            }

            $notifyUserIdList = array_unique($notifyUserIdList);

            foreach ($notifyUserIdList as $i => $userId) {
                if ($entity->isUserIdNotified($userId)) {
                    unset($notifyUserIdList[$i]);
                }
            }
            $notifyUserIdList = array_values($notifyUserIdList);

            if (!empty($notifyUserIdList)) {
                $this->getNotificationService()->notifyAboutNote($notifyUserIdList, $entity);
            }
        }
    }

    protected function getSubscriberList(string $parentType, string $parentId, bool $isInternal = false): EntityCollection
    {
        $pdo = $this->getEntityManager()->getPDO();

        if (!$isInternal) {
            $sql = "
                SELECT user_id AS userId
                FROM subscription
                WHERE entity_id = " . $pdo->quote($parentId) . " AND entity_type = " . $pdo->quote($parentType) . "
            ";
        } else {
            $sql = "
                SELECT subscription.user_id AS userId
                FROM subscription
                JOIN user ON user.id = subscription.user_id
                WHERE
                    entity_id = " . $pdo->quote($parentId) . " AND entity_type = " . $pdo->quote($parentType) . " AND
                    user.is_portal_user = 0
            ";
        }

        $userList = $this->getEntityManager()->getRepository('User')->where(
            [
                'isActive' => true
            ]
        )->select(['id', 'isPortalUser', 'isAdmin'])->find(
            [
                'customWhere' => "AND user.id IN (" . $sql . ")"
            ]
        );

        return $userList;
    }

    protected function getInternalAclManager(): AclManager
    {
        return $this->getInjection('container')->get('internalAclManager');
    }

    protected function getUser(): \Espo\Entities\User
    {
        return $this->getInjection('container')->get('user');
    }

    protected function getAcl(): Acl
    {
        return $this->getInjection('container')->get('acl');
    }

    protected function getAclManager(): AclManager
    {
        return $this->getInjection('container')->get('aclManager');
    }

    protected function getNotificationService(): \Espo\Services\Notification
    {
        if (is_null($this->notificationService)) {
            $this->notificationService = $this->getInjection('container')->get('serviceFactory')->create('Notification');
        }

        return $this->notificationService;
    }

    /**
     * @inheritDoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }
}

