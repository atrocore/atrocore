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

namespace Espo\Services;

use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\Json;
use Espo\ORM\Entity;
use Espo\Repositories\Notification as NotificationRepository;

/**
 * Class Notification
 */
class Notification extends \Espo\Services\Record
{
    protected $actionHistoryDisabled = true;

    public function deleteOld(): bool
    {
        $days = $this->getConfig()->get('notificationsMaxDays', 21);
        if ($days === 0) {
            return true;
        }

        // delete
        while (true) {
            $toDelete = $this->getEntityManager()->getRepository('Notification')
                ->where(['createdAt<' => (new \DateTime())->modify("-$days days")->format('Y-m-d H:i:s')])
                ->limit(0, 2000)
                ->order('createdAt')
                ->find();
            if (empty($toDelete[0])) {
                break;
            }
            foreach ($toDelete as $entity) {
                $this->getEntityManager()->removeEntity($entity);
            }
        }

        // delete forever
        $daysToDeleteForever = $days + 14;
        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb
            ->delete('notification')
            ->where('created_at < :maxDate')
            ->andWhere('deleted = :true')
            ->setParameter('maxDate', (new \DateTime())->modify("-$daysToDeleteForever days")->format('Y-m-d H:i:s'))
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->executeQuery();

        return true;
    }

    public function notifyAboutMentionInPost(string $userId, string $noteId): void
    {
        $notification = $this->getEntityManager()->getEntity('Notification');
        $notification->set(
            [
                'type'        => 'MentionInPost',
                'data'        => ['noteId' => $noteId],
                'userId'      => $userId,
                'relatedId'   => $noteId,
                'relatedType' => 'Note'
            ]
        );
        $this->getEntityManager()->saveEntity($notification);
    }

    public function notifyAboutNote(array $userIdList, Entity $note): void
    {
        $userList = $this
            ->getEntityManager()
            ->getRepository('User')
            ->where(
                [
                    'isActive' => true,
                    'id'       => $userIdList
                ]
            )
            ->find();

        foreach ($userList as $user) {
            if (!$this->checkUserNoteAccess($user, $note)) {
                continue 1;
            }

            if ($note->get('createdById') === $user->get('id')) {
                continue 1;
            }

            $notification = $this->getEntityManager()->getEntity('Notification');

            $notification->set('type', 'Note');
            $notification->set('data', ['noteId' => $note->get('id')]);
            $notification->set('userId', $user->get('id'));
            $notification->set('relatedId', $note->get('id'));
            $notification->set('relatedType', 'Note');
            $notification->set('relatedParentId', $note->get('parentId'));
            $notification->set('relatedParentType', $note->get('parentType'));

            $this->getEntityManager()->saveEntity($notification);
        }
    }

    public function checkUserNoteAccess(\Espo\Entities\User $user, Entity $note)
    {
        if ($note->get('relatedType')) {
            if (!$this->getAclManager()->checkScope($user, $note->get('relatedType'))) {
                return false;
            }
        }

        if ($note->get('parentType')) {
            if (!$this->getAclManager()->checkScope($user, $note->get('parentType'))) {
                return false;
            }
        }

        return true;
    }

    public function markAllRead($userId)
    {
        $connection = $this->getEntityManager()->getConnection();

        $connection->createQueryBuilder()
            ->update('notification', 'n')
            ->set($connection->quoteIdentifier('read'), ':true')
            ->setParameter('true', true, Mapper::getParameterType(true))
            ->where('n.user_id = :userId')
            ->setParameter('userId', $userId)
            ->andWhere('n.read = :false')
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->executeQuery();

        // update count for user
        NotificationRepository::refreshNotReadCount($connection);

        return true;
    }

    public function getList($userId, array $params = array())
    {
        $searchParams = array();

        $whereClause = array(
            'userId' => $userId
        );
        if (!empty($params['after'])) {
            $whereClause['createdAt>'] = $params['after'];
        }

        $ignoreScopeList = $this->getIgnoreScopeList();
        if (!empty($ignoreScopeList)) {
            $where = [];
            $where[] = array(
                'OR' => array(
                    'relatedParentType'   => null,
                    'relatedParentType!=' => $ignoreScopeList
                )
            );
            $whereClause[] = $where;
        }

        $searchParams['whereClause'] = $whereClause;

        if (array_key_exists('offset', $params)) {
            $searchParams['offset'] = $params['offset'];
        }
        if (array_key_exists('maxSize', $params)) {
            $searchParams['limit'] = $params['maxSize'];
        }
        $searchParams['orderBy'] = 'createdAt';
        $searchParams['order'] = 'DESC';

        $collection = $this->getEntityManager()->getRepository('Notification')->find($searchParams);
        $count = $this->getEntityManager()->getRepository('Notification')->count($searchParams);

        $ids = array();
        foreach ($collection as $k => $entity) {
            $ids[] = $entity->id;
            $data = $entity->get('data');
            if (empty($data)) {
                continue;
            }
            switch ($entity->get('type')) {
                case 'Note':
                case 'MentionInPost':
                    $note = $this->getEntityManager()->getRepository('Note')->where(['id' => $data->noteId])->findOne();
                    if ($note) {
                        if ($note->get('parentId') && $note->get('parentType')) {
                            $parent = $this->getEntityManager()->getEntity($note->get('parentType'), $note->get('parentId'));
                            if ($parent) {
                                $note->set('parentName', $parent->get('name'));
                            }
                        } else {
                            if (!$note->get('isGlobal')) {
                                $targetType = $note->get('targetType');
                                if (!$targetType || $targetType === 'users') {
                                    $note->loadLinkMultipleField('users');
                                }
                                if ($targetType !== 'users') {
                                    if (!$targetType || $targetType === 'teams') {
                                        $note->loadLinkMultipleField('teams');
                                    }
                                }
                            }
                        }
                        if ($note->get('relatedId') && $note->get('relatedType')) {
                            $related = $this->getEntityManager()->getEntity($note->get('relatedType'), $note->get('relatedId'));
                            if ($related) {
                                $note->set('relatedName', $related->get('name'));
                            }
                        }
                        $this->getRecordService('Stream')->prepareForOutput($note);
                        $entity->set('noteData', $note->toArray());
                    } else {
                        unset($collection[$k]);
                        $count--;
                        $this->getEntityManager()->removeEntity($entity);
                    }
                    break;
            }
        }

        if (!empty($ids)) {
            $connection = $this->getEntityManager()->getConnection();
            $connection->createQueryBuilder()
                ->update('notification', 'n')
                ->set($connection->quoteIdentifier('read'), ':true')
                ->setParameter('true', true, Mapper::getParameterType(true))
                ->where('n.id IN (:ids)')
                ->setParameter('ids', $ids, Mapper::getParameterType($ids))
                ->executeQuery();
            NotificationRepository::refreshNotReadCount($connection);
        }

        return array(
            'total'      => $count,
            'collection' => $collection
        );
    }

    protected function getIgnoreScopeList()
    {
        $ignoreScopeList = [];
        $scopes = $this->getMetadata()->get('scopes', array());
        foreach ($scopes as $scope => $d) {
            if (empty($d['entity']) || !$d['entity']) continue;
            if (empty($d['object']) || !$d['object']) continue;
            if (!$this->getAcl()->checkScope($scope)) {
                $ignoreScopeList[] = $scope;
            }
        }
        return $ignoreScopeList;
    }
}

