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

namespace Espo\Services;

use Espo\Core\DataManager;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;

class MassRestore extends QueueManagerBase
{
    public function run(array $data = []): bool
    {
        if (empty($data['entityType'])) {
            return false;
        }

        $entityType = $data['entityType'];

        $ids = [];
        if (array_key_exists('ids', $data) && !empty($data['ids']) && is_array($data['ids'])) {
            $ids = $data['ids'];
        }

        if (array_key_exists('where', $data)) {
            $selectManager = $this->getContainer()->get('selectManagerFactory')->create($entityType);
            $selectParams = $selectManager->getSelectParams(['where' => $data['where']], true, true);

            $this->getEntityManager()->getRepository($entityType)->handleSelectParams($selectParams);

            $result = $this->getEntityManager()
                ->getRepository($entityType)
                ->getMapper()
                ->select(
                    $this->getEntityManager()->getRepository($entityType)->get(),
                    array_merge($selectParams, ['select' => ['id']])
                );

            $ids = array_column($result,'id');
        }

        if (empty($ids)) {
            return false;
        }

        $restored = 0;
        $total = count($ids);

        self::updatePublicData($entityType, ['restored' => $restored, 'total' => $total]);

        $service = $this->getContainer()->get('serviceFactory')->create($data['entityType']);
        $start = time();
        foreach ($ids as $id) {
            try {
                $service->restoreEntity($id);
                $restored++;
                if ((time() - $start) > 3) {
                    self::updatePublicData($entityType, ['restored' => $restored, 'total' => $total]);
                    $start = time();
                }
            } catch (\Throwable $e) {
                $message = "Restore {$data['entityType']} '$id' failed: {$e->getTraceAsString()}";
                $GLOBALS['log']->error($message);
                $this->notify($message);
            }
        }

        self::updatePublicData($entityType, ['restored' => $restored, 'total' => $total, 'done' => Util::generateId()]);
        sleep(2);

        return true;
    }

    public function getNotificationMessage(Entity $queueItem): string
    {
        return '';
    }

    public static function updatePublicData(string $entityType, ?array $data): void
    {
        $publicData = DataManager::getPublicData('massRestore');
        if (empty($publicData)) {
            $publicData = [];
        }
        $publicData[$entityType] = $data;
        DataManager::pushPublicData('massRestore', $publicData);
    }

    protected function notify(string $message): void
    {
        $notification = $this->getEntityManager()->getEntity('Notification');
        $notification->set('type', 'Message');
        $notification->set('message', $message);
        $notification->set('userId', $this->getUser()->get('id'));
        $this->getEntityManager()->saveEntity($notification);
    }
}
