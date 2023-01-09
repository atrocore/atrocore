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

/**
 * Class Measure
 */
class Measure extends Base
{
    public function getUnitsOfMeasure()
    {
        $cacheName = 'measures_' . $this->getUser()->get('id');

        $result = $this->getMetadata()->getDataManager()->getCacheData($cacheName, false);
        if (!empty($result)) {
            return $result;
        }

        $data = $this->findEntities(['maxSize' => \PHP_INT_MAX]);

        $result = [];
        if (!empty($data['total'])) {
            $inputLanguageList = $this->getConfig()->get('inputLanguageList', []);
            foreach ($data['collection'] as $measure) {
                if (empty($units = $measure->get('units')) || count($units) == 0) {
                    continue 1;
                }

                $result[$measure->get('name')]['unitListData'] = [];
                foreach ($units as $unit) {
                    $result[$measure->get('name')]['unitList'][] = $unit->get('name');
                    $result[$measure->get('name')]['unitListData'][$unit->get('id')] = [
                        'id'          => $unit->get('id'),
                        'name'        => $unit->get('name'),
                        'isDefault'   => $unit->get('isDefault'),
                        'multiplier'  => $unit->get('multiplier'),
                        'convertToId' => $unit->get('convertToId'),
                    ];
                }

                foreach ($inputLanguageList as $locale) {
                    $result[$measure->get('name')]['unitListTranslates'][$locale] = array_column($units->toArray(), 'name' . ucfirst(Util::toCamelCase(strtolower($locale))));
                }
            }

            $this->getMetadata()->getDataManager()->setCacheData($cacheName, $result);
        }

        return Json::decode(Json::encode($result));
    }
}
