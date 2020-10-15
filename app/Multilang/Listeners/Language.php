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
 * Website: https://treolabs.com
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

use Treo\Core\EventManager\Event;
use Treo\Core\Utils\Util;
use Treo\Listeners\AbstractListener;

/**
 * Class Language
 */
class Language extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function modify(Event $event)
    {
        if (empty($this->getConfig()->get('isMultilangActive'))) {
            return false;
        }

        // get languages
        if (empty($languages = $this->getConfig()->get('inputLanguageList', []))) {
            return false;
        }

        // get data
        $data = $event->getArgument('data');

        foreach ($data as $locale => $rows) {
            foreach ($rows as $scope => $items) {
                foreach (['fields', 'tooltips'] as $type) {
                    if (isset($items[$type])) {
                        foreach ($items[$type] as $field => $value) {
                            foreach ($languages as $language) {
                                // prepare multi-lang field
                                $mField = $field . ucfirst(Util::toCamelCase(strtolower($language)));

                                if (!isset($data[$locale][$scope][$type][$mField])) {
                                    if ($type == 'fields') {
                                        $data[$locale][$scope][$type][$mField] = $value . ' › ' . $language;
                                    } else {
                                        $data[$locale][$scope][$type][$mField] = $value;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // set data
        $event->setArgument('data', $data);
    }
}
