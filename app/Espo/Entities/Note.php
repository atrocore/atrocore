<?php

namespace Espo\Entities;

class Note extends \Espo\Core\ORM\Entity
{
    private $aclIsProcessed = false;

    public function setAclIsProcessed()
    {
        $this->aclIsProcessed = true;
    }

    public function isAclProcessed()
    {
        return $this->aclIsProcessed;
    }

    public function loadAttachments()
    {
        $data = $this->get('data');
        if (!empty($data) && !empty($data->attachmentsIds) && is_array($data->attachmentsIds)) {
            $attachmentsIds = $data->attachmentsIds;
            $collection = $this->entityManager->getRepository('Attachment')->select(['id', 'name', 'type'])->order('createdAt')->where([
                'id' => $attachmentsIds
            ])->find();
        } else {
            $this->loadLinkMultipleField('attachments');
            return;
        }

        $ids = array();
        $names = new \stdClass();
        $types = new \stdClass();
        foreach ($collection as $e) {
            $id = $e->id;
            $ids[] = $id;
            $names->$id = $e->get('name');
            $types->$id = $e->get('type');
        }
        $this->set('attachmentsIds', $ids);
        $this->set('attachmentsNames', $names);
        $this->set('attachmentsTypes', $types);
    }

    public function addNotifiedUserId($userId)
    {
        $userIdList = $this->get('notifiedUserIdList');
        if (!is_array($userIdList)) {
            $userIdList = [];
        }
        if (!in_array($userId, $userIdList)) {
            $userIdList[] = $userId;
        }
        $this->set('notifiedUserIdList', $userIdList);
    }

    public function isUserIdNotified($userId)
    {
        $userIdList = $this->get('notifiedUserIdList');
        if (!is_array($userIdList)) {
            $userIdList = [];
        }
        return in_array($userId, $userIdList);
    }
}
