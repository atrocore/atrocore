<?php

namespace Espo\Hooks\Note;

use Espo\ORM\Entity;

class Notifications extends \Espo\Core\Hooks\Base
{
    protected $notificationService = null;

    public static $order = 14;

    protected function init()
    {
        $this->addDependency('serviceFactory');
        $this->addDependency('container');
    }

    protected function getServiceFactory()
    {
        return $this->getInjection('serviceFactory');
    }

    protected function getInternalAclManager()
    {
        return $this->getInjection('container')->get('internalAclManager');
    }

    protected function getPortalAclManager()
    {
        return $this->getInjection('container')->get('portalAclManager');
    }

    protected function getMentionedUserIdList($entity)
    {
        $mentionedUserList = array();
        $data = $entity->get('data');
        if (($data instanceof \stdClass) && ($data->mentions instanceof \stdClass)) {
            $mentions = get_object_vars($data->mentions);
            foreach ($mentions as $d) {
                $mentionedUserList[] = $d->id;
            }
        }
        return $mentionedUserList;
    }

    protected function getSubscriberList($parentType, $parentId, $isInternal = false)
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

        $userList = $this->getEntityManager()->getRepository('User')->where([
            'isActive' => true
        ])->select(['id', 'isPortalUser', 'isAdmin'])->find([
            'customWhere' => "AND user.id IN (".$sql.")"
        ]);

        return $userList;
    }

    public function afterSave(Entity $entity)
    {
        if ($entity->isNew()) {
            $parentType = $entity->get('parentType');
            $parentId = $entity->get('parentId');
            $superParentType = $entity->get('superParentType');
            $superParentId = $entity->get('superParentId');

            $notifyUserIdList = [];

            if ($parentType && $parentId) {
				$userList =  $this->getSubscriberList($parentType, $parentId, $entity->get('isInternal'));
                $userIdMetList = [];
                foreach ($userList as $user) {
                    $userIdMetList[] = $user->id;
                }
                if ($superParentType && $superParentId) {
                    $additionalUserList = $this->getSubscriberList($superParentType, $superParentId, $entity->get('isInternal'));
                    foreach ($additionalUserList as $user) {
                        if ($user->isPortal()) continue;
                        if (in_array($user->id, $userIdMetList)) continue;
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
                    } else if ($level === 'team') {
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
                    } else if ($level === 'own') {
                        if (in_array($user->id, $userIdList)) {
                            $notifyUserIdList[] = $user->id;
                            continue;
                        }
                    }
                }

            } else {
                $targetType = $entity->get('targetType');
                if ($targetType === 'users') {
                    $targetUserIdList = $entity->get('usersIds');
                    if (is_array($targetUserIdList)) {
                        foreach ($targetUserIdList as $userId) {
                            if ($userId === $this->getUser()->id) continue;
                            if (in_array($userId, $notifyUserIdList)) continue;
                            $notifyUserIdList[] = $userId;
                        }
                    }
                } else if ($targetType === 'teams') {
                    $targetTeamIdList = $entity->get('teamsIds');
                    if (is_array($targetTeamIdList)) {
                        foreach ($targetTeamIdList as $teamId) {
                            $team = $this->getEntityManager()->getEntity('Team', $teamId);
                            if (!$team) continue;
                            $targetUserList = $this->getEntityManager()->getRepository('Team')->findRelated($team, 'users', array(
                                'whereClause' => array(
                                    'isActive' => true
                                )
                            ));
                            foreach ($targetUserList as $user) {
                                if ($user->id === $this->getUser()->id) continue;
                                if (in_array($user->id, $notifyUserIdList)) continue;
                                $notifyUserIdList[] = $user->id;
                            }
                        }
                    }
                } else if ($targetType === 'portals') {
                    $targetPortalIdList = $entity->get('portalsIds');
                    if (is_array($targetPortalIdList)) {
                        foreach ($targetPortalIdList as $portalId) {
                            $portal = $this->getEntityManager()->getEntity('Portal', $portalId);
                            if (!$portal) continue;
                            $targetUserList = $this->getEntityManager()->getRepository('Portal')->findRelated($portal, 'users', array(
                                'whereClause' => array(
                                    'isActive' => true
                                )
                            ));
                            foreach ($targetUserList as $user) {
                                if ($user->id === $this->getUser()->id) continue;
                                if (in_array($user->id, $notifyUserIdList)) continue;
                                $notifyUserIdList[] = $user->id;
                            }
                        }
                    }
                } else if ($targetType === 'all') {
                    $targetUserList = $this->getEntityManager()->getRepository('User')->find(array(
                        'whereClause' => array(
                            'isActive' => true,
                            'isPortalUser' => false
                        )
                    ));
                    foreach ($targetUserList as $user) {
                        if ($user->id === $this->getUser()->id) continue;
                        $notifyUserIdList[] = $user->id;
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

    protected function getNotificationService()
    {
        if (empty($this->notificationService)) {
            $this->notificationService = $this->getServiceFactory()->create('Notification');
        }
        return $this->notificationService;
    }
}
