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
use Espo\Core\QueueManager;

/**
 * Class QueueItem
 */
class QueueItem extends Base
{
    public function deleteOldRecords(): void
    {
        $date = (new \DateTime())->modify('-30 days')->format('Y-m-d H:i:s');
        $this->getPDO()->exec("DELETE FROM `queue_item` WHERE modified_at<'$date'");
    }

    public function getRunningItemForStream(int $stream): ?Entity
    {
        return $this->where(['stream' => $stream, 'status' => 'Running'])->findOne();
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        /**
         * Create file
         */
        if ($entity->get('status') === 'Pending') {
            $filesInDir = 7000;
            $sortOrder = $this->get($entity->get('id'))->get('sortOrder');
            $dirName = (int)($sortOrder / $filesInDir);

            // delete old empty dirs
            if (file_exists(QueueManager::QUEUE_DIR_PATH)) {
                foreach (scandir(QueueManager::QUEUE_DIR_PATH) as $v) {
                    if (in_array($v, ['.', '..'])) {
                        continue;
                    }

                    if ((int)$v >= $dirName) {
                        break;
                    }

                    $files = scandir(QueueManager::QUEUE_DIR_PATH . '/' . $v);
                    if (!array_key_exists(2, $files)) {
                        rmdir(QueueManager::QUEUE_DIR_PATH . '/' . $v);
                    }
                }
            }

            $dirPath = QueueManager::QUEUE_DIR_PATH . '/' . $dirName;

            // create new dir if not exist
            while (!file_exists($dirPath)) {
                mkdir($dirPath, 0777, true);
                sleep(1);
            }

            $fileName = str_pad((string)($sortOrder % $filesInDir), 4, '0', STR_PAD_LEFT);
            switch ($entity->get('priority')) {
                case 'High':
                    $fileName = '0.' . $fileName;
                    break;
                case 'Normal':
                    $fileName = str_pad((string)$fileName, 8, '0', STR_PAD_LEFT);
                    break;
                case 'Low':
                    $fileName = (int)$fileName + $filesInDir;
                    break;
            }

            file_put_contents($dirPath . '/' . $fileName . '.txt', $entity->get('id'));
            file_put_contents(QueueManager::FILE_PATH, '1');
        }

        if (!in_array($entity->get('status'), ['Pending', 'Running'])) {
            $this->notify($entity);
        }

        if ($entity->get('status') === 'Canceled' && !empty($entity->get('pid'))) {
            exec("kill -9 {$entity->get('pid')}");
        }

        $this->preparePublicDataForMassDelete($entity);

        if (in_array($entity->get('status'), ['Success', 'Failed', 'Canceled'])) {
            $item = $this->where(['status' => ['Pending', 'Running']])->findOne();
            if (empty($item)) {
                unlink(QueueManager::FILE_PATH);
            }
        }
    }

    protected function preparePublicDataForMassDelete(Entity $entity): void
    {
        if ($entity->get('serviceName') !== 'MassDelete' || in_array($entity->get('status'), ['Pending', 'Running']) || empty($entity->get('data'))) {
            return;
        }

        $data = json_decode(json_encode($entity->get('data')), true);
        if (!empty($data['entityType'])) {
            \Espo\Services\MassDelete::updatePublicData($data['entityType'], null);
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
