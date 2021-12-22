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

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Espo\Core\Templates\Repositories\Base;
use Espo\Services\QueueManagerServiceInterface;


/**
 * Class QueueItem
 */
class QueueItem extends Base
{
    /**
     * @return bool
     */
    public function isQueueEmpty(): bool
    {
        $count = $this->select(['id'])->where(['status' => ['Pending', 'Running']])->count();

        return empty($count);
    }

    /**
     * @param int $stream
     *
     * @return \Espo\Entities\QueueItem|null
     */
    public function getRunningItemForStream(int $stream): ?Entity
    {
        return $this->where(['stream' => $stream, 'status' => 'Running'])->order('sortOrder', 'ASC')->findOne();
    }

    /**
     * @return Entity
     */
    public function getPendingItemForStream(): ?Entity
    {
        if (!empty($job = $this->where(['stream' => null, 'status' => 'Pending', 'priority' => 'High'])->order('sortOrder', 'ASC')->findOne())) {
            return $job;
        }

        if (!empty($job = $this->where(['stream' => null, 'status' => 'Pending', 'priority' => 'Normal'])->order('sortOrder', 'ASC')->findOne())) {
            return $job;
        }

        return $this->where(['stream' => null, 'status' => 'Pending', 'priority' => 'Low'])->order('sortOrder', 'ASC')->findOne();
    }

    /**
     * @inheritdoc
     */
    protected function afterSave(Entity $entity, array $options = [])
    {
        // call parent
        parent::afterSave($entity, $options);

        if (!in_array($entity->get('status'), ['Pending', 'Running'])) {
            $this->notify($entity);
        }
    }

    /**
     * @param Entity $entity
     * @param array  $options
     *
     * @throws BadRequest
     */
    protected function beforeRemove(Entity $entity, array $options = [])
    {
        if ($entity->get('status') == 'Running') {
            throw new BadRequest($this->getInjection('language')->translate('jobIsRunning', 'exceptions', 'QueueItem'));
        }

        parent::beforeRemove($entity, $options);
    }

    /**
     * @param Entity $entity
     * @param array  $options
     */
    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        // delete forever
        $this->deleteFromDb($entity->get('id'));
    }

    /**
     * @inheritdoc
     */
    protected function init()
    {
        // call parent
        parent::init();

        $this->addDependency('queueManager');
        $this->addDependency('language');
        $this->addDependency('serviceFactory');
    }

    /**
     * @param Entity $entity
     */
    protected function notify(Entity $entity): void
    {
        try {
            $service = $this->getInjection('serviceFactory')->create($entity->get('serviceName'));
        } catch (\Throwable $e) {
            $GLOBALS['log']->error('Notification Error: ' . $e->getMessage());
            return;
        }

        if ($service instanceof QueueManagerServiceInterface && !empty($message = $service->getNotificationMessage($entity))) {
            $notification = $this->getEntityManager()->getEntity('Notification');
            $notification->set('type', 'Message');
            $notification->set('relatedType', 'QueueItem');
            $notification->set('relatedId', $entity->get('id'));
            $notification->set('message', $message);
            $notification->set('userId', $entity->get('createdById'));
            $this->getEntityManager()->saveEntity($notification);
        }
    }
}
