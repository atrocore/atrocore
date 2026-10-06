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
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Table;

/**
 * Entity definitions without a scope describe plain tables, not entities, so they have no soft delete any more:
 * the `deleted` column is dropped from such tables of the core, along with it in every index.
 */
class V2Dot5Dot3 extends Base
{
    private const TABLES = ['id_map', 'pseudo_transaction_job'];

    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-10-06 12:00:00');
    }

    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        foreach (self::TABLES as $tableName) {
            if (!$toSchema->hasTable($tableName) || !$toSchema->getTable($tableName)->hasColumn('deleted')) {
                continue;
            }

            // without the column such rows would come back to life - a pseudo transaction job would even be executed
            $this->getDbal()->createQueryBuilder()
                ->delete($tableName)
                ->where('deleted = :true')
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->executeStatement();

            $this->dropDeletedColumn($toSchema->getTable($tableName));
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getPDO()->exec($sql);
        }
    }

    /**
     * Rebuilds every index holding the column without it. A unique index gets a name generated from its columns,
     * a regular one keeps its name - the same as the schema rebuild creates them.
     */
    protected function dropDeletedColumn(Table $table): void
    {
        foreach ($table->getIndexes() as $index) {
            if ($index->isPrimary() || !in_array('deleted', $index->getColumns())) {
                continue;
            }

            $table->dropIndex($index->getName());

            $columns = array_values(array_diff($index->getColumns(), ['deleted']));
            if (empty($columns)) {
                continue;
            }

            if ($index->isUnique()) {
                $table->addUniqueIndex($columns);
            } else {
                $table->addIndex($columns, strtoupper($index->getName()));
            }
        }

        $table->dropColumn('deleted');
    }
}
