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

use Treo\Core\Migration\Base;

class V1Dot6Dot8 extends Base
{
    public function up(): void
    {
        $path = 'custom/Espo/Custom/Resources/metadata/entityDefs';
        if (file_exists($path)) {
            foreach (scandir($path) as $file) {
                $filePath = "$path/$file";
                if (!is_file($filePath)) {
                    continue;
                }

                $contents = file_get_contents($filePath);

                if (strpos($contents, '"measureId"') !== false) {
                    $data = json_decode($contents, true);
                    if (empty($data['fields'])) {
                        continue;
                    }

                    $hasChanges = false;
                    foreach ($data['fields'] as $field => $fieldDefs) {
                        if (empty($fieldDefs['measureId']) || !empty($fieldDefs['measureName'])) {
                            continue;
                        }

                        $measure = $this->getPDO()
                            ->query("SELECT * FROM measure WHERE name=" . $this->getPDO()->quote($fieldDefs['measureId']) . " AND deleted=0")
                            ->fetch(\PDO::FETCH_ASSOC);

                        if (empty($measure)) {
                            continue;
                        }

                        $data['fields'][$field]['measureId'] = $measure['id'];
                        $data['fields'][$field]['measureName'] = $measure['name'];

                        $hasChanges = true;
                    }
                    if ($hasChanges) {
                        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
                    }
                }
            }
        }

        $this->updateComposer('atrocore/core', '^1.6.8');
    }

    public function down(): void
    {
        throw new \Error('Downgrade is prohibited!');
    }
}
