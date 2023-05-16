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

use Espo\Core\EventManager\Event;

class I18nController extends AbstractListener
{
    public function afterActionRead(Event $event): void
    {
        if (!empty($event->getArgument('request')->get('skipModifications'))) {
            return;
        }

        $result = $event->getArgument('result');

        foreach ($this->getMetadata()->get('entityDefs', []) as $entityType => $entityDefs) {
            if (empty($entityDefs['fields'])) {
                continue 1;
            }
            foreach ($entityDefs['fields'] as $field => $fieldDefs) {
                if (empty($fieldDefs['type'])) {
                    continue;
                }

                /**
                 * Prepare range labels for From and To
                 */
                if (in_array($fieldDefs['type'], ['rangeInt', 'rangeFloat'])) {
                    $fieldLabel = $this->getLanguage()->translate($field, 'fields', $entityType);
                    $fromLabel = $this->getLanguage()->translate('From');
                    $toLabel = $this->getLanguage()->translate('To');

                    $result[$entityType]['fields'][$field . 'From'] = $fieldLabel . ' ' . $fromLabel;
                    $result[$entityType]['fields'][$field . 'To'] = $fieldLabel . ' ' . $toLabel;

                    if (!empty($fieldDefs['unitField'])) {
                        $fieldType = $fieldDefs['type'] === 'rangeInt' ? 'int' : 'float';
                        $result[$entityType]['fields'][$field . 'From'] .= ' ' . $this->getLanguage()->translate($fieldType . 'Part');
                        $result[$entityType]['fields'][$field . 'To'] .= ' ' . $this->getLanguage()->translate($fieldType . 'Part');
                    }
                }

                if (!empty($fieldDefs['unitField'])) {
                    $mainField = $fieldDefs['mainField'] ?? $field;
                    $fieldLabel = $this->getLanguage()->translate($mainField, 'fields', $entityType);
                    $fieldType = $this->getMetadata()->get(['entityDefs', $entityType, 'fields', $mainField, 'type']);

                    $result[$entityType]['fields'][$mainField . 'UnitId'] = $fieldLabel . ' ' . $this->getLanguage()->translate('unitPart');
                    if (!in_array($fieldDefs['type'], ['rangeInt', 'rangeFloat'])) {
                        $result[$entityType]['fields'][$mainField] = $fieldLabel . ' ' . $this->getLanguage()->translate($fieldType . 'Part');
                        $result[$entityType]['fields']['unit' . ucfirst($mainField)] = $fieldLabel;
                    }
                }
            }
        }

        $event->setArgument('result', $result);
    }
}
