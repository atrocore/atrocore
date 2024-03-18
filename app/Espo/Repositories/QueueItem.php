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

declare(strict_types=1);

namespace Espo\Repositories;

use  Atro\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Espo\Core\Templates\Repositories\Base;
use Espo\Services\QueueManagerServiceInterface;
use Atro\Core\QueueManager;

class QueueItem extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        // update sort order
        if ($entity->isNew()) {
            $sortOrder = time() - (new \DateTime('2023-01-01'))->getTimestamp();
            if (!empty($entity->get('parentId')) && !empty($parent = $this->get($entity->get('parentId')))) {
                $sortOrder = $parent->get('sortOrder') . '.' . $sortOrder;
            }
            $entity->set('sortOrder', $sortOrder);
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        /**
         * Create file
         */
        if ($entity->get('status') === 'Pending') {
            $sortOrder = $this->get($entity->get('id'))->get('sortOrder');
            $priority = $entity->get('priority');

            $filePath = $this->getFilePath($sortOrder, $priority, $entity->get('id'));
            if (!empty($filePath)) {
                file_put_contents($filePath, $entity->get('id'));
            }
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
            if (empty($item) && file_exists(QueueManager::FILE_PATH)) {
                unlink(QueueManager::FILE_PATH);
            }
        }
    }

    public function getFilePath(float $sortOrder, string $priority, string $itemId): ?string
    {
        $filesInDir = 4000;
        $dirName = (int)($sortOrder / $filesInDir);

        $fileName = str_pad((string)($sortOrder % $filesInDir), 4, '0', STR_PAD_LEFT);
        $parts = explode('.', (string)$sortOrder);
        if (isset($parts[1])) {
            $fileName .= '_' . str_pad((string)((int)$parts[1]), 4, '0', STR_PAD_LEFT);
        }

        switch ($priority) {
            case 'Highest':
                $dirPath = QueueManager::QUEUE_DIR_PATH . '/0';
                break;
            case 'High':
                $dirPath = QueueManager::QUEUE_DIR_PATH . '/000001';
                break;
            case 'Low':
                $dirPath = QueueManager::QUEUE_DIR_PATH . '/88888888888888';
                break;
            case 'Lowest':
                $dirPath = QueueManager::QUEUE_DIR_PATH . '/99999999999999';
                break;
            default:
                $dirName = str_pad((string)$dirName, 6, '0', STR_PAD_LEFT);
                if (in_array($dirName, ['000000', '000001'])) {
                    $dirName = '000002';
                }
                $dirPath = QueueManager::QUEUE_DIR_PATH . '/' . $dirName;
        }

        // create new dir if not exist
        while (!file_exists($dirPath)) {
            mkdir($dirPath, 0777, true);
        }

        if (file_exists($dirPath) && count(scandir($dirPath)) > $filesInDir) {
            return null;
        }

        return $dirPath . '/' . $fileName . '(' . $itemId . ')' . '.txt';
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

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        if ($entity->get('status') == 'Running') {
            throw new BadRequest($this->getInjection('language')->translate('jobIsRunning', 'exceptions', 'QueueItem'));
        }

        parent::beforeRemove($entity, $options);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $fileName = $this->getFilePath($entity->get('sortOrder'), $entity->get('priority'), $entity->get('id'));
        if (!empty($fileName) && file_exists($fileName)) {
            unlink($fileName);
        }

        $this->deleteFromDb($entity->get('id'));
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('queueManager');
        $this->addDependency('language');
        $this->addDependency('serviceFactory');
    }

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
