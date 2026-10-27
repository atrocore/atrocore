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

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V2Dot7Dot4 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-10-28 12:00:00');
    }

    public function up(): void
    {
        // the unique index of attribute values no longer contains the "deleted" column
        foreach ($this->getAttributeValueTables() as $tableName) {
            $entityColumn = substr($tableName, 0, -strlen('_attribute_value')) . '_id';
            if (!$this->hasColumns($tableName, [$entityColumn, 'attribute_id', 'deleted'])) {
                continue;
            }

            $indexName = $this->generateIndexName($tableName, 'unique_relationship');

            $this->exec("DELETE FROM $tableName WHERE deleted = " . ($this->isPgSQL() ? 'true' : '1'));

            if ($this->isPgSQL()) {
                $this->exec("DROP INDEX IF EXISTS " . strtolower($indexName));
            } else {
                $this->exec("DROP INDEX $indexName ON $tableName");
            }

            $this->exec("CREATE UNIQUE INDEX $indexName ON $tableName ($entityColumn, attribute_id)");
        }
    }

    protected function getAttributeValueTables(): array
    {
        $schema = $this->isPgSQL() ? 'current_schema()' : 'DATABASE()';

        return $this->getDbal()->createQueryBuilder()
            ->select('table_name')
            ->from('information_schema.tables')
            ->where("table_schema = $schema")
            ->andWhere("table_name LIKE '%\\_attribute\\_value'")
            ->fetchFirstColumn();
    }

    protected function hasColumns(string $tableName, array $columns): bool
    {
        $schema = $this->isPgSQL() ? 'current_schema()' : 'DATABASE()';

        $existing = $this->getDbal()->createQueryBuilder()
            ->select('column_name')
            ->from('information_schema.columns')
            ->where("table_schema = $schema")
            ->andWhere('table_name = :tableName')
            ->setParameter('tableName', $tableName)
            ->fetchFirstColumn();

        return empty(array_diff($columns, array_map('strtolower', $existing)));
    }

    protected function generateIndexName(string $tableName, string $indexName): string
    {
        $res = $tableName . '_' . $indexName;
        if (strlen($res) > 55) {
            $res = md5($res);
        }

        return 'IDX_' . strtoupper($res);
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
