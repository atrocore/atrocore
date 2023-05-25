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

namespace Espo\Listeners;

use Espo\Core\DataManager;
use Espo\Core\EventManager\Event;
use Espo\Core\Utils\Util;

class Service extends AbstractListener
{
    /**
     * @param Event $event
     *
     * @return void
     */
    public function beforeUpdateEntity(Event $event)
    {
        $data = $event->getArgument('data');
        $type = $event->getArgument('entityType');

        if (property_exists($data, 'massUpdateData')) {
            $massUpdateData = json_decode(json_encode($data->massUpdateData), true);
            unset($data->massUpdateData);

            $publicData = DataManager::getPublicData('massUpdate');
            $publicData = $publicData[$type] ?? [];

            if (isset($publicData['updated']) && !isset($publicData['done'])) {
                $massUpdateData['updated'] =  $publicData['updated'];
            } else {
                $massUpdateData['updated'] = 0;
            }

            if ($massUpdateData['position'] == $massUpdateData['total'] - 1) {
                $massUpdateData['done'] = Util::generateId();
            }

            $this->updateMassUpdatePublicData($type, $massUpdateData);

            $event->setArgument('data', $data);
            $event->setArgument('massUpdateData', $massUpdateData);
        }
    }

    /**
     * @param Event $event
     *
     * @return void
     */
    public function afterUpdateEntity(Event $event)
    {
        if ($event->hasArgument('beforeUpdateEvent')) {
            $beforeUpdateEvent = $event->getArgument('beforeUpdateEvent');

            if ($beforeUpdateEvent instanceof Event && $beforeUpdateEvent->hasArgument('massUpdateData')) {
                $entity = $event->getArgument('entity');
                $massUpdateData = $beforeUpdateEvent->getArgument('massUpdateData');

                if (isset($massUpdateData['updated'])) {
                    $massUpdateData['updated']++;
                }

                $this->updateMassUpdatePublicData($entity->getEntityType(), $massUpdateData);
            }
        }
    }

    /**
     * @param string $entityType
     * @param array|null $data
     *
     * @return void
     */
    public function updateMassUpdatePublicData(string $entityType, ?array $data): void
    {
        $publicData = DataManager::getPublicData('massUpdate');
        if (empty($publicData)) {
            $publicData = [];
        }
        $publicData[$entityType] = $data;
        DataManager::pushPublicData('massUpdate', $publicData);
    }
}
