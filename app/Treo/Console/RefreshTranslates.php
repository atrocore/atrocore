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

namespace Treo\Console;

use Espo\Core\Utils\File\Unifier;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Util;
use Espo\ORM\EntityManager;

/**
 * Class RefreshTranslates
 */
class RefreshTranslates extends AbstractConsole
{
    /**
     * Get console command description
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Refresh translates.';
    }

    /**
     * Run action
     *
     * @param array $data
     */
    public function run(array $data): void
    {
        $language = new Language('en_US', $this->getContainer()->get('fileManager'), $this->getMetadata(), $this->getContainer()->get('eventManager'));
        $data = $language->getModulesData();

        /** @var EntityManager $em */
        $em = $this->getContainer()->get('entityManager');

        // delete old
        $em->nativeQuery('DELETE FROM label WHERE 1');

        $records = [];
        foreach ($data as $module => $moduleData) {
            foreach ($moduleData as $locale => $localeData) {
                $preparedLocaleData = [];
                $this->toSimpleArray($localeData, $preparedLocaleData);
                foreach ($preparedLocaleData as $key => $value) {
                    $records[$key]['name'] = $key;
                    $records[$key]['module'] = $module;
                    $records[$key][Util::toCamelCase(strtolower($locale))] = $value;
                }
            }
        }

        foreach ($records as $record) {
            $label = $em->getEntity('Label');
            $label->set($record);
            $em->saveEntity($label);
        }


        // render
        self::show('Translates refreshed successfully.', self::SUCCESS);
    }

    protected function toSimpleArray(array $data, array &$result, array &$parents = []): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $parents[] = $key;
                $this->toSimpleArray($value, $result, $parents);
            } else {
                $result[implode('.', array_merge($parents, [$key]))] = $value;
            }
        }

        if (!empty($parents)) {
            array_pop($parents);
        }
    }
}
