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

use Espo\Core\Utils\Util;
use Treo\Core\Migration\Base;

class V1Dot6Dot0 extends Base
{
    protected array $measureUnits = [];

    public function up(): void
    {
        $this->exec("DROP INDEX UNIQ_6598AC4577153098EB3B4E33 ON extensible_enum_option");
        $this->exec("CREATE UNIQUE INDEX IDX_UNIQUE_OPTION ON extensible_enum_option (deleted, extensible_enum_id, code)");

        $this->exec("ALTER TABLE measure DROP code");
        $this->getPDO()->exec("ALTER TABLE measure ADD code VARCHAR(255) DEFAULT NULL UNIQUE COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE UNIQUE INDEX UNIQ_8007192577153098EB3B4E33 ON measure (code, deleted)");
        $this->exec("DROP INDEX code ON measure");

        $this->exec("ALTER TABLE unit DROP code");
        $this->getPDO()->exec("ALTER TABLE unit ADD code VARCHAR(255) DEFAULT NULL UNIQUE COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE UNIQUE INDEX UNIQ_DCBB0C5377153098EB3B4E33 ON unit (code, deleted)");
        $this->exec("DROP INDEX code ON unit");

        $this->exec("DROP TABLE locale_measure");
        $this->exec("ALTER TABLE measure DROP data");

        $path = 'custom/Espo/Custom/Resources/metadata/entityDefs';
        if (file_exists($path)) {
            foreach (scandir($path) as $file) {
                $filePath = "$path/$file";
                if (!is_file($filePath)) {
                    continue;
                }

                $contents = file_get_contents($filePath);

                if (strpos($contents, '"unit"') !== false) {
                    $data = json_decode($contents, true);
                    if (empty($data['fields'])) {
                        continue;
                    }
                    foreach ($data['fields'] as $field => $fieldDefs) {
                        if ($fieldDefs['type'] !== 'unit') {
                            continue;
                        }

                        $entityType = str_replace('.json', '', $file);
                        $tableName = Util::toUnderScore($entityType);
                        $columnName = Util::toUnderScore($field);

                        $this->exec("ALTER TABLE `$tableName` ADD {$columnName}_unit_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
                        foreach ($this->getMeasureUnits((string)$fieldDefs['measure']) as $unit) {
                            $name = $this->getPDO()->quote($unit['name']);
                            $this->exec("UPDATE `$tableName` SET {$columnName}_unit_id='{$unit['id']}' WHERE {$columnName}_unit={$name}");
                        }
                    }
                    $contents = str_replace(['"unit"', '"measure"'], ['"float"', '"measureId"'], $contents);
                    file_put_contents($filePath, $contents);
                }
            }
        }

        $this->getConfig()->set('mainLanguage', 'en_US');
        $this->getConfig()->save();
    }

    public function down(): void
    {
        throw new \Error('Downgrade is prohibited!');
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }

    protected function getMeasureUnits(string $measureId): array
    {
        if (!isset($this->measureUnits[$measureId])) {
            $this->measureUnits[$measureId] = $this->getPDO()
                ->query("SELECT * FROM unit WHERE deleted=0 AND measure_id='$measureId'")
                ->fetchAll(\PDO::FETCH_ASSOC);
        }

        return $this->measureUnits[$measureId];
    }
}
