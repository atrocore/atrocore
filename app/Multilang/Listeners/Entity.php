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

namespace Multilang\Listeners;

use Espo\ORM\Entity as OrmEntity;
use Treo\Core\EventManager\Event;
use Treo\Listeners\AbstractListener;

/**
 * Class Entity
 */
class Entity extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function beforeSave(Event $event)
    {
        /** @var OrmEntity $entity */
        $entity = $event->getArgument('entity');

        // get fields
        $fields = $this->getContainer()->get('metadata')->get(['entityDefs', $entity->getEntityType(), 'fields'], []);

        foreach ($fields as $field => $data) {
            if ($data['type'] == 'enum' && !empty($data['isMultilang']) && $entity->isAttributeChanged($field)) {
                // find key
                $key = array_search($entity->get($field), $data['options']);
                foreach ($fields as $mField => $mData) {
                    if (isset($mData['multilangField']) && $mData['multilangField'] == $field) {
                        if ($entity->get($field) == '') {
                            $value = $entity->get($field);
                        } elseif (isset($mData['options'][$key])) {
                            $value = $mData['options'][$key];
                        }

                        if (isset($value)) {
                            $entity->set($mField, $value);
                        }
                    }
                }
            }

            if ($data['type'] == 'multiEnum' && !empty($data['isMultilang']) && $entity->isAttributeChanged($field)) {
                $keys = [];
                foreach ($entity->get($field) as $value) {
                    $keys[] = array_search($value, $data['options']);
                }
                foreach ($fields as $mField => $mData) {
                    if (isset($mData['multilangField']) && $mData['multilangField'] == $field) {
                        $values = [];
                        foreach ($keys as $key) {
                            $values[] = $mData['options'][$key];
                        }
                        $entity->set($mField, $values);
                    }
                }
            }
        }
    }
}
