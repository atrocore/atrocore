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

namespace Espo\Services;

use Espo\Core\Templates\Services\Base;

class Locale extends Base
{
    public function linkEntity($id, $link, $foreignId)
    {
        $result = parent::linkEntity($id, $link, $foreignId);

        if ($result && $link === 'measures') {
            $measure = $this->getEntityManager()->getRepository('Measure')->get($foreignId);
            if (!empty($units = $measure->get('units')) && count($units) > 0) {
                $unitsIds = [];
                $defaultUnit = '';
                foreach ($units as $unit) {
                    $unitsIds[] = $unit->get('id');
                    if ($unit->get('isDefault')) {
                        $defaultUnit = $unit->get('id');
                    }
                }

                $measure->setDataParameter("locale_$id", $unitsIds);
                $measure->setDataParameter("locale_{$id}_default", $defaultUnit);
                $this->getEntityManager()->saveEntity($measure);
            }
        }

        return $result;
    }

    public function findLinkedEntities($id, $link, $params)
    {
        if ($link === 'measures') {
            $params['select'][] = 'data';
        }

        $result = parent::findLinkedEntities($id, $link, $params);

        if (!empty($result['total']) && $link === 'measures') {
            foreach ($result['collection'] as $measure) {
                $localeUnits = $measure->getDataParameter("locale_$id");
                if (empty($localeUnits)) {
                    $localeUnits = [];
                } else {
                    $localeUnits = array_column($this->getEntityManager()->getRepository('Unit')->select(['id'])->where(['id' => $localeUnits])->find()->toArray(), 'id');
                }
                $measure->set('localeUnits', $localeUnits);
                $measure->set('localeDefault', $measure->getDataParameter("locale_{$id}_default"));

                $units = $measure->get('units')->toArray();

                $measure->set('unitsIds', array_column($units, 'id'));
                $measure->set('unitsNames', array_column($units, 'name', 'id'));
            }
        }

        return $result;
    }
}