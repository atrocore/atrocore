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

namespace Multilang\Listeners;

use Espo\Core\Exceptions\Error;
use Treo\Core\EventManager\Event;
use Treo\Core\Utils\Util;
use Treo\Listeners\AbstractListener;

/**
 * Class FieldManagerController
 *
 * @package Multilang\Listeners
 */
class FieldManagerController extends AbstractListener
{
    /**
     * @param Event $event
     *
     * @throws Error
     */
    public function beforePutActionUpdate(Event $event)
    {
        $data = get_object_vars($event->getArgument('data'));

        if (in_array($data['type'], ['enum', 'multiEnum']) && $data['required']) {
            if (isset($data['isMultilang']) && $data['isMultilang']) {
                foreach ($data['options'] as $option) {
                    if ($option == '') {
                        throw new Error('Option must be filled');
                    }
                }

                $locales = $this->getConfig()->get('inputLanguageList', []);

                foreach ($locales as $locale) {
                    $multilangOptionField = 'options' . ucfirst(Util::toCamelCase(strtolower($locale)));

                    if (isset($data[$multilangOptionField])) {
                        foreach ($data[$multilangOptionField] as $option) {
                            if ($option == '') {
                                throw new Error('Multilang option must be filled');
                            }
                        }
                    }
                }
            }
        }
    }
}
