<?php

namespace Espo\Services;

use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\NotFound;

use Espo\ORM\Entity;

use Espo\Core\Utils\Json;

class Notification extends \Espo\Services\Record
{
    protected $actionHistoryDisabled = true;

    public function notifyAboutMentionInPost($userId, $noteId)
    {
        $notification = $this->getEntityManager()->getEntity('Notification');
        $notification->set(array(
            'type' => 'MentionInPost',
            'data' => array('noteId' => $noteId),
            'userId' => $userId,
            'relatedId' => $noteId,
            'relatedType' => 'Note'
        ));
        $this->getEntityManager()->saveEntity($notification);
    }

    public function notifyAboutNote(array $userIdList, \Espo\Entities\Note $note)
    {
        $data = array('noteId' => $note->id);
        $encodedData = Json::encode($data);

        $now = date('Y-m-d H:i:s');
        $pdo = $this->getEntityManager()->getPDO();

        $query = $this->getEntityManager()->getQuery();

        $sql = "INSERT INTO `notification` (`id`, `data`, `type`, `user_id`, `created_at`, `related_id`, `related_type`, `related_parent_id`, `related_parent_type`) VALUES ";
        $arr = [];

        $userList = $this->getEntityManager()->getRepository('User')->where(array(
            'isActive' => true,
            'id' => $userIdList
        ))->find();
        foreach ($userList as $user) {
            $userId = $user->id;
            if (!$this->checkUserNoteAccess($user, $note)) continue;
            if ($note->get('createdById') === $user->id) continue;
            $id = \Espo\Core\Utils\Util::generateId();
            $arr[] = "(".$query->quote($id).", ".$query->quote($encodedData).", ".$query->quote('Note').", ".$query->quote($userId).", ".$query->quote($now).", ".$query->quote($note->id).", ".$query->quote('Note').", ".$query->quote($note->get('parentId')).", ".$query->quote($note->get('parentType')).")";
        }

        if (empty($arr)) {
            return;
        }

        $sql .= implode(", ", $arr);
        $pdo->query($sql);
    }

    public function checkUserNoteAccess(\Espo\Entities\User $user, \Espo\Entities\Note $note)
    {
        if ($user->get('isPortalUser')) {
            if ($note->get('relatedType')) {
                if ($note->get('relatedType') === 'Email' && $note->get('parentType') === 'Case') {
                    return true;
                }
                return false;
            }
            return true;
        }

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

    public function getNotReadCount($userId)
    {
        $whereClause = array(
            'userId' => $userId,
            'read' => 0
        );

        $ignoreScopeList = $this->getIgnoreScopeList();
        if (!empty($ignoreScopeList)) {
            $where = [];
            $where[] = array(
                'OR' => array(
                    'relatedParentType' => null,
                    'relatedParentType!=' => $ignoreScopeList
                )
            );
            $whereClause[] = $where;
        }

        return $this->getEntityManager()->getRepository('Notification')->where($whereClause)->count();
    }

    public function markAllRead($userId)
    {
        $pdo = $this->getEntityManager()->getPDO();
        $sql = "UPDATE notification SET `read` = 1 WHERE user_id = ".$pdo->quote($userId)." AND `read` = 0";
        $pdo->prepare($sql)->execute();
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
                    'relatedParentType' => null,
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
                    $note = $this->getEntityManager()->getEntity('Note', $data->noteId);
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
                                    } else if ($targetType === 'portals') {
                                        $note->loadLinkMultipleField('portals');
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
                        $note->loadLinkMultipleField('attachments');
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
            $pdo = $this->getEntityManager()->getPDO();
            $idQuotedList = [];
            foreach ($ids as $id) {
                $idQuotedList[] = $pdo->quote($id);
            }

            $sql = "UPDATE notification SET `read` = 1 WHERE id IN (" . implode(', ', $idQuotedList) .")";

            $s = $pdo->prepare($sql);
            $s->execute();
        }


        return array(
            'total' => $count,
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

