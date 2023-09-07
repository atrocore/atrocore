<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Listeners;

use Espo\Core\DataManager;
use Atro\Core\EventManager\Event;
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
