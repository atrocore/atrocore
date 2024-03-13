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
        if (empty($data = $this->get('data')) || !property_exists($data, 'attachmentsIds') || empty($data->attachmentsIds)) {
            return;
        }

        $collection = $this->entityManager->getRepository('Attachment')
            ->select(['id', 'name', 'type'])
            ->where(['id' => $data->attachmentsIds])
            ->order('createdAt')
            ->find();

        $attachmentsIds = [];
        $attachmentsNames = new \stdClass();
        $attachmentsTypes = new \stdClass();
        foreach ($collection as $e) {
            $id = $e->id;
            $attachmentsIds[] = $id;
            $attachmentsNames->$id = $e->get('name');
            $attachmentsTypes->$id = $e->get('type');
        }

        $this->set('attachmentsIds', $attachmentsIds);
        $this->set('attachmentsNames', $attachmentsNames);
        $this->set('attachmentsTypes', $attachmentsTypes);
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
