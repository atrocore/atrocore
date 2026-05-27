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
use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\Schema\Schema;

class V2Dot3Dot3 extends Base
{
    private ?Schema $currentSchema = null;

    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-05-21 10:00:00');
    }

    public function up(): void
    {
        $this->createPrefixTable();
        $this->addPrefixEnabledToAttribute();
        $this->addPrefixValueToAttributeValue();
        $this->renameUnitFieldsInLayouts();
    }

    private function createPrefixTable(): void
    {
        $fromSchema = $this->getSchema();
        $toSchema   = clone $fromSchema;

        if ($toSchema->hasTable('prefix')) {
            return;
        }

        $table = $toSchema->createTable('prefix');
        $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
        $table->addColumn('name', 'string', ['length' => 255, 'notnull' => false, 'default' => null]);
        $table->addColumn('value', 'string', ['length' => 255, 'notnull' => false, 'default' => null]);
        $table->addColumn('deleted', 'boolean', ['notnull' => false, 'default' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => false, 'default' => null]);
        $table->addColumn('modified_at', 'datetime', ['notnull' => false, 'default' => null]);
        $table->addColumn('created_by_id', 'string', ['length' => 36, 'notnull' => false, 'default' => null]);
        $table->addColumn('modified_by_id', 'string', ['length' => 36, 'notnull' => false, 'default' => null]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['name', 'deleted'], 'IDX_PREFIX_NAME');
        $table->addIndex(['created_by_id,', 'deleted'], 'IDX_PREFIX_CREATED_BY_ID');
        $table->addIndex(['modified_by_id,', 'deleted'], 'IDX_PREFIX_MODIFIED_BY_ID');

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->exec($sql);
        }
    }

    private function addPrefixEnabledToAttribute(): void
    {
        $fromSchema = $this->getSchema();
        $toSchema   = clone $fromSchema;

        if (!$toSchema->hasTable('attribute')) {
            return;
        }

        $table = $toSchema->getTable('attribute');

        if (!$table->hasColumn('prefix_enabled')) {
            $table->addColumn('prefix_enabled', 'boolean', ['notnull' => true, 'default' => false]);
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->exec($sql);
        }
    }

    private function addPrefixValueToAttributeValue(): void
    {
        $fromSchema = $this->getSchema();
        $toSchema   = clone $fromSchema;

        foreach ($toSchema->getTables() as $table) {
            if (!str_ends_with($table->getName(), '_attribute_value')) {
                continue;
            }

            if (!$table->hasColumn('prefix_value')) {
                $table->addColumn('prefix_value', 'string', ['length' => 36, 'notnull' => false, 'default' => null]);
                $table->addIndex(['prefix_value', 'deleted'], Converter::generateIndexName($table->getName(), 'prefixValue'));
            }
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->exec($sql);
        }
    }

    private function renameUnitFieldsInLayouts(): void
    {
        // layout_list_item has attribute_id, so we can check attribute-based items
        $listItems = $this->fetchRows("
            SELECT lli.id, lli.name, lli.attribute_id, l.entity
            FROM layout_list_item lli
            JOIN layout l ON l.id = lli.layout_id AND l.deleted = false
            WHERE lli.deleted = false
              AND lli.name LIKE 'unit%'
        ");

        foreach ($listItems as $item) {
            if ($this->shouldRename($item['name'], $item['entity'], $item['attribute_id'])) {
                $newName = 'combined' . substr($item['name'], 4);
                $this->updateName('layout_list_item', $item['id'], $newName);
            }
        }

        // layout_row_item has no attribute_id, entity column check only
        $rowItems = $this->fetchRows("
            SELECT lri.id, lri.name, l.entity
            FROM layout_row_item lri
            JOIN layout_section ls ON ls.id = lri.section_id AND ls.deleted = false
            JOIN layout l ON l.id = ls.layout_id AND l.deleted = false
            WHERE lri.deleted = false
              AND lri.name LIKE 'unit%'
        ");

        foreach ($rowItems as $item) {
            if ($this->shouldRename($item['name'], $item['entity'], null)) {
                $newName = 'combined' . substr($item['name'], 4);
                $this->updateName('layout_row_item', $item['id'], $newName);
            }
        }
    }

    private function shouldRename(string $name, ?string $entity, ?string $attributeId): bool
    {
        if (strlen($name) <= 4) {
            return false;
        }

        $fieldName = lcfirst(substr($name, 4)); // "unitHeight" → "height"

        if (!empty($attributeId)) {
            return $this->attributeMatchesFieldName($attributeId, $fieldName);
        }

        if (empty($entity)) {
            return false;
        }

        $tableName  = Util::toUnderScore(lcfirst($entity));
        $columnName = Util::toUnderScore($fieldName);
        $schema     = $this->getSchema();

        return $schema->hasTable($tableName)
            && $schema->getTable($tableName)->hasColumn($columnName);
    }

    private function attributeMatchesFieldName(string $attributeId, string $fieldName): bool
    {
        try {
            $stmt = $this->getPDO()->prepare("
                SELECT id FROM attribute
                WHERE id = :id
                  AND (code = :fieldName OR id = :fieldName)
                  AND deleted = false
            ");
            $stmt->execute([':id' => $attributeId, ':fieldName' => $fieldName]);
            return $stmt->fetchColumn() !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getSchema(): Schema
    {
        if ($this->currentSchema === null) {
            $this->currentSchema = $this->getCurrentSchema();
        }
        return $this->currentSchema;
    }

    private function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }

    private function fetchRows(string $sql): array
    {
        try {
            return $this->getPDO()->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function updateName(string $table, string $id, string $newName): void
    {
        try {
            $stmt = $this->getPDO()->prepare("UPDATE {$table} SET name = :name WHERE id = :id");
            $stmt->execute([':name' => $newName, ':id' => $id]);
        } catch (\Throwable $e) {
        }
    }
}
