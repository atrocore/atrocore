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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Espo\Listeners;

use Espo\Core\EventManager\Event;
use Espo\ORM\Entity;
use stdClass;

/**
 * Class AppController
 */
class AppController extends AbstractListener
{
    /**
     * After action user
     * Change language and Hide dashlets
     *
     * @param Event $event
     *
     * @throws \Espo\Core\Exceptions\Error
     */
    public function afterActionUser(Event $event)
    {
        $result = $event->getArgument('result');
        $language = $event->getArgument('request')->get('language');
        $currentLanguage = $result['language'] ?? '';

        if (!empty($result['preferences'])) {
            $this->hideDashletsWithEmptyEntity($result['preferences']);
        }

        if (!empty($result['user']) && !empty($language) && $currentLanguage !== $language) {
            /** @var \Espo\Listeners\Entity $preferences */
            $preferences = $this->getPreferences();

            // change language for user
            $preferences->set('language', $language);

            $result['language'] = $language;

            $this->saveEntity($preferences);
        }
        $event->setArgument('result', $result);
    }

    /**
     * Save entity
     *
     * @param \Espo\Listeners\Entity $entity
     */
    protected function saveEntity(Entity $entity): void
    {
        $this->getEntityManager()->saveEntity($entity);
    }

    /**
     * Hide dashlets with empty entity
     *
     * @param stdClass $preferences
     *
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function hideDashletsWithEmptyEntity(stdClass &$preferences): void
    {
        $dashletsOptions = isset($preferences->dashletsOptions) ? $preferences->dashletsOptions : null;

        if (!empty($dashletsOptions)) {
            $dashboards = isset($preferences->dashboardLayout) ? $preferences->dashboardLayout : [];
            foreach ($dashboards as $dashboard) {//iterate over dashboard
                if (is_array($dashboard->layout)) {
                    foreach ($dashboard->layout as $key => $layout) {//iterate over layout of dashboard
                        $id = $layout->id;
                        //check isset dashlet with this ID layout
                        $issetDashlet = isset($dashletsOptions->{$id}) && is_object($dashletsOptions->{$id});
                        $isEntity = !empty($dashletsOptions->{$id}->entityType)
                            && $this->isExistEntity($dashletsOptions->{$id}->entityType);
                        if ($issetDashlet && !$isEntity) {
                            //hide dashlet
                            unset($dashletsOptions->{$id});
                            if (isset($dashboard->layout[$key])) {
                                unset($dashboard->layout[$key]);
                            }
                        }
                    }
                    //reset key in array
                    $dashboard->layout = array_values($dashboard->layout);
                }
            }
        }
    }

    /**
     * @param string|null $name
     *
     * @return bool
     */
    protected function isExistEntity(?string $name): bool
    {
        $isEntity = false;
        if (!empty($name)) {
            $isEntity = class_exists($this->getEntityManager()->normalizeEntityName($name));
        }

        return $isEntity;
    }
}
