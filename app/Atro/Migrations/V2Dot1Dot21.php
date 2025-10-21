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
use Atro\ORM\DB\RDB\Mapper;

class V2Dot1Dot21 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-10-21 08:00:00');
    }

    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        /** @var Metadata $metadata */
        $metadata = (new \Atro\Core\Application())->getContainer()->get('metadata');

        foreach ($metadata->get('entityDefs') ?? [] as $scope => $entityDefs) {
            $tableName = Util::toUnderScore(lcfirst($scope));
            if ($toSchema->hasTable($tableName)) {
                $table = $toSchema->getTable($tableName);
                foreach ($entityDefs['fields'] as $field => $fieldDefs) {
                    if (in_array($fieldDefs['type'] ?? null, ['rangeInt', 'rangeFloat']) && !empty($fieldDefs['measureId'])) {
                        $column = Util::toUnderScore(lcfirst($field) . 'UnitId');
                        if (!$table->hasColumn($column)) {
                            $table->addColumn($column, 'string', ['length' => 36, 'notnull' => false]);
                            $table->addIndex([$column, 'deleted'], strtoupper('idx_' . $tableName . '_' . $column));
                        }
                    }
                }
            }
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
