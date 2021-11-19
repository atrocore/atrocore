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

use Espo\Core\Templates\Services\Base;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Util;
use Espo\Repositories\Measure as Repository;

/**
 * Class Measure
 */
class Measure extends Base
{
    public function getUnitsOfMeasure()
    {
        if (!file_exists(Repository::CACHE_DIR)) {
            Util::createDir(Repository::CACHE_DIR);
        }

        $cacheFile = sprintf(Repository::CACHE_FILE, $this->getUser()->get('id'));
        if (!file_exists($cacheFile)) {
            $data = $this->findEntities(['maxSize' => \PHP_INT_MAX]);

            $result = [];
            if (!empty($data['total'])) {
                $inputLanguageList = $this->getConfig()->get('inputLanguageList', []);
                foreach ($data['collection'] as $measure) {
                    $units = $measure->get('units')->toArray();
                    $result[$measure->get('name')]['unitList'] = array_column($units, 'name');
                    foreach ($inputLanguageList as $locale) {
                        $result[$measure->get('name')]['unitListTranslates'][$locale] = array_column($units, 'name' . ucfirst(Util::toCamelCase(strtolower($locale))));
                    }
                }
            }
            $result = Json::encode($result);
            file_put_contents($cacheFile, $result);
        } else {
            $result = file_get_contents($cacheFile);
        }

        return Json::decode($result);
    }
}
