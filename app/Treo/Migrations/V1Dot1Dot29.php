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

namespace Treo\Migrations;

use Espo\Core\Utils\Json;
use Treo\Core\Migration\Base;

/**
 * Migration for version 1.1.29
 */
class V1Dot1Dot29 extends Base
{
    /**
     * @var string[]
     */
    protected $replacements = [
        'TreoPIM Product',
        'My TreoCore'
    ];

    /**
     * @var string
     */
    protected $needle = 'Main Dashboard';

    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $preferences = $this
            ->getPDO()
            ->query("SELECT * FROM preferences")
            ->fetchAll(\PDO::FETCH_ASSOC);

        if (!empty($preferences)) {
            $sql = "";

            foreach ($preferences as $preference) {
                $data = Json::decode($preference['data'], true);

                if (isset($data['dashboardLayout']) && is_array($data['dashboardLayout'])) {
                    foreach ($data['dashboardLayout'] as $key => $dashboard) {
                        if (in_array($dashboard['name'], $this->replacements)) {
                            $data['dashboardLayout'][$key]['name'] = $this->needle;
                            $result = Json::encode($data);

                            $sql .= "UPDATE preferences SET data = '{$result}' WHERE id = '{$preference['id']}';";
                        }
                    }
                }
            }

            if (!empty($sql)) {
                $this->getPDO()->exec($sql);
            }
        }

        if (!empty($configDashboards = $this->getConfig()->get('dashboardLayout', []))) {
            foreach ($configDashboards as $key => $dashboard) {
                if (isset($dashboard->name) && in_array($dashboard->name, $this->replacements)) {
                    $configDashboards[$key]->name = $this->needle;
                }
            }

            $this->getConfig()->set('dashboardLayout', $configDashboards);
            $this->getConfig()->save();
        }
    }
}
