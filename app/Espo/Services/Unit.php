<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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

use Atro\Core\Templates\Services\Base;
use Espo\Core\EventManager\Event;
use Espo\ORM\Entity;

/**
 * Class Unit
 */
class Unit extends Base
{

    public function setUnitAsDefault(Entity $unit): void
    {
        $needToSave = false;
        $measureId = $unit->get('measureId');
        foreach ($this->getMetadata()->get('entityDefs', []) as $entity => $entityDefs) {
            if (empty($entityDefs['fields'])) {
                continue;
            }
            foreach ($entityDefs['fields'] as $field => $fieldDefs) {
                if (!empty($fieldDefs['measureId']) && $fieldDefs['measureId'] == $measureId) {
                    if (!empty($fieldDefs['defaultUnit']) && $fieldDefs['defaultUnit'] == $unit->get('id')) {
                        continue;
                    }

                    $needToSave = true;
                    $this->getMetadata()->set('entityDefs', $entity, [
                        'fields' => [
                            "$field" => [
                                'defaultUnit' => $unit->get('id')
                            ]
                        ]
                    ]);
                }
            }
        }

        if ($needToSave) {
            $this->getMetadata()->save();
            $this->getInjection('dataManager')->clearCache();
        }

        $this->dispatchEvent('afterSetUnitAsDefault', new Event(['entity' => $unit, 'service' => $this]));
    }

    protected function init()
    {
        parent::init();
        $this->addDependency('dataManager');
    }
}
