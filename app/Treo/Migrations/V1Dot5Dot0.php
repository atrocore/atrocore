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

namespace Treo\Migrations;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\ORM\Entity;
use Espo\Core\Utils\Util;
use Treo\Core\Migration\Base;

class V1Dot5Dot0 extends Base
{
    public function up(): void
    {
        /** @var \Espo\Core\Utils\Metadata $metadata */
        $metadata = (new \Espo\Core\Application())->getContainer()->get('metadata');

        foreach ($metadata->get('entityDefs') as $entityType => $entityDefs) {
            if (empty($entityDefs['fields'])) {
                continue;
            }
            foreach ($entityDefs['fields'] as $field => $fieldDefs) {
                if (empty($fieldDefs['type']) || !empty($fieldDefs['notStorable'])) {
                    continue;
                }
                if (in_array($fieldDefs['type'], ['enum', 'multiEnum'])) {
                    if (!isset($fieldDefs['optionsIds']) || !isset($fieldDefs['options'])) {
                        continue;
                    }

                    $tableName = Util::toUnderScore(lcfirst($entityType));
                    $columnName = Util::toUnderScore(lcfirst($field));

                    if ($fieldDefs['type'] === 'enum') {
                        foreach ($fieldDefs['options'] as $k => $option) {
                            if ($option !== $fieldDefs['optionsIds'][$k]) {
                                $this->exec(
                                    "UPDATE `$tableName` SET $tableName.$columnName='{$fieldDefs['optionsIds'][$k]}' WHERE deleted=0 AND $tableName.$columnName='{$option}'"
                                );
                            }
                        }
                    }

                    if ($fieldDefs['type'] === 'multiEnum') {
                        if (!Entity::areValuesEqual('arrayObject', $fieldDefs['options'], $fieldDefs['optionsIds'])) {
                            $records = $this
                                ->getPDO()
                                ->query(
                                    "SELECT id, $tableName.$columnName FROM `$tableName` WHERE deleted=0 AND $tableName.$columnName IS NOT NULL AND $tableName.$columnName!='[]'"
                                )
                                ->fetchAll(\PDO::FETCH_ASSOC);

                            if (!empty($records)) {
                                while (!empty($records)) {
                                    $record = array_shift($records);
                                    $values = [];
                                    if (!empty($fieldData = @json_decode($record[$columnName]))) {
                                        foreach ($fieldData as $v) {
                                            $key = array_search($v, $fieldDefs['options']);
                                            if ($key !== false) {
                                                $values[] = $fieldDefs['optionsIds'][$key];
                                            }
                                        }
                                    }
                                    $values = json_encode($values);
                                    $this->exec("UPDATE `$tableName` SET $tableName.$columnName='{$values}' WHERE deleted=0 AND id='{$record['id']}'");
                                }
                            }
                        };
                    }
                }
            }
        }
    }

    public function exec(string $query): void
    {
//        echo $query . PHP_EOL;
        $this->getPDO()->exec($query);
    }

    public function down(): void
    {
        throw new BadRequest('Downgrade is prohibited.');
    }
}
