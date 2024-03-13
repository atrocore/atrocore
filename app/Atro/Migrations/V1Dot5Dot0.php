<?php
/**
* AtroCore Software
*
* This source file is available under GNU General Public License version 3 (GPLv3).
* Full copyright and license information is available in LICENSE.txt, located in the root directory.
*
*  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
*  @license    GPLv3 (https://www.gnu.org/licenses/)
*/

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Exceptions\BadRequest;
use Espo\Core\ORM\Entity;
use Espo\Core\Utils\Util;
use Atro\Core\Migration\Base;

class V1Dot5Dot0 extends Base
{
    public function up(): void
    {
        /** @var \Espo\Core\Utils\Metadata $metadata */
        $metadata = (new \Atro\Core\Application())->getContainer()->get('metadata');

        foreach ($metadata->get('scopes') as $scope => $scopeDefs) {
            if (!empty($scopeDefs['notStorable'])) {
                continue;
            }

            $entityType = $scope;
            $entityDefs = $metadata->get(['entityDefs', $entityType]);

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
