<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Atro\Core\Utils\Metadata;
use Atro\Core\Utils\Util;

class V2Dot1Dot35 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-12-08 18:00:00');
    }

    public function up(): void
    {
        /** @var Metadata $metadata */
        $metadata = (new \Atro\Core\Application())->getContainer()->get('metadata');

        $entityDefsPath = 'data/metadata/entityDefs';

        if (!file_exists($entityDefsPath)) {
            return;
        }

        foreach (array_diff(scandir($entityDefsPath), ['.', '..']) as $file) {
            $entity = explode('.', $file, 2)[0];


            $data = json_decode(@file_get_contents($entityDefsPath . '/' . $file), true);
            $changed = false;
            foreach ($data['fields'] ?? [] as $field => $fieldDefs) {
                if ($metadata->get(['entityDefs', $entity, 'fields', $field, 'type']) === 'linkMultiple') {
                    if (!empty($fieldDefs['required'])) {
                        unset($data['fields'][$field]['required']);
                        $changed = true;
                    }
                    if (!empty($fieldDefs['conditionalProperties']['required'])) {
                        unset($data['fields'][$field]['conditionalProperties']['required']);
                        if (empty($data['fields'][$field]['conditionalProperties'])){
                            unset($data['fields'][$field]['conditionalProperties']);
                        }
                        $changed = true;
                    }
                }
            }

            if ($changed) {
                file_put_contents($entityDefsPath . '/' . $file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        }

        // add default unit value for fields with measure
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        try {
            foreach ($metadata->get(['entityDefs']) as $entityName => $entityDefs) {
                $tableName = Util::toUnderScore($entityName);
                if ($toSchema->hasTable($tableName)) {
                    $table = $toSchema->getTable($tableName);
                    foreach ($entityDefs['fields'] as $fieldName => $fieldDefs) {
                        $columnName = Util::toUnderScore($fieldName . 'UnitId');
                        if (!empty($fieldDefs['measureId']) && !empty($fieldDefs['defaultUnit']) &&
                            in_array($fieldDefs['type'] ?? null, ['varchar', 'int', 'float', 'rangeInt', 'rangeFloat']) &&
                            $table->hasColumn($columnName) && empty($table->getColumn($columnName)->getDefault())) {
                            $table->getColumn($columnName)->setDefault($fieldDefs['defaultUnit']);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
        }


        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->exec($sql);
        }
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
