<?php
/**
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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;

class V3Dot0Dot0 extends Base
{
    protected const int MAX_INDEX_LENGTH = 3072;

    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-10-30 12:00:00');
    }

    public function up(): void
    {
        $this->convertToUtf8mb4();
        $this->migrateConnection();
        $this->migrateStorage();
        $this->migrateAction();
        $this->migrateAttribute();
    }

    /**
     * MySQL stores the texts in utf8mb4. The tables and the columns created in utf8mb3 are converted to it, a binary
     * collation stays binary. The types of the columns stay the same. MySQL does not change a column of a foreign
     * key, so the foreign keys of the converted tables are removed and created again. A table with an index that is
     * too long for utf8mb4 stays as it is, together with the tables linked to it by foreign keys.
     */
    protected function convertToUtf8mb4(): void
    {
        if ($this->isPgSQL()) {
            return;
        }

        $schema = $this->getCurrentSchema();

        $tableNames = [];
        foreach ($schema->getTables() as $table) {
            $tableNames[] = $table->getName();
        }
        $tableNames = array_values(array_intersect($this->getUtf8mb3TableNames(), $tableNames));

        $tableNames = $this->excludeLinkedTableNames(
            $schema,
            $tableNames,
            array_values(array_intersect($this->getTableNamesWithTooLongIndex(), $tableNames))
        );

        if (empty($tableNames)) {
            return;
        }

        $foreignKeys = $this->getForeignKeysOfTables($schema, $tableNames);

        $conn = $this->getDbal();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            $fromSchema = $this->getCurrentSchema();
            $toSchema = clone $fromSchema;
            foreach ($foreignKeys as $row) {
                $toSchema->getTable($row['table'])->removeForeignKey($row['foreignKey']->getName());
            }
            $this->executeSchemaDiff($fromSchema, $toSchema);

            $fromSchema = $this->getCurrentSchema();
            $toSchema = clone $fromSchema;
            foreach ($tableNames as $tableName) {
                foreach ($toSchema->getTable($tableName)->getColumns() as $column) {
                    if (!$column->hasPlatformOption('collation')) {
                        continue;
                    }

                    $collation = (string)$column->getPlatformOption('collation');
                    if (!str_starts_with($collation, 'utf8_') && !str_starts_with($collation, 'utf8mb3_')) {
                        continue;
                    }

                    $column->setPlatformOption('charset', 'utf8mb4');
                    $column->setPlatformOption('collation', str_ends_with($collation, '_bin') ? 'utf8mb4_bin' : 'utf8mb4_unicode_ci');
                }
            }
            $this->executeSchemaDiff($fromSchema, $toSchema);

            foreach ($tableNames as $tableName) {
                $conn->executeStatement('ALTER TABLE ' . $conn->quoteIdentifier($tableName) . ' DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            }

            $fromSchema = $this->getCurrentSchema();
            $toSchema = clone $fromSchema;
            foreach ($foreignKeys as $row) {
                $foreignKey = $row['foreignKey'];
                $toSchema->getTable($row['table'])->addForeignKeyConstraint(
                    $foreignKey->getForeignTableName(),
                    $foreignKey->getLocalColumns(),
                    $foreignKey->getForeignColumns(),
                    $foreignKey->getOptions(),
                    $foreignKey->getName()
                );
            }
            $this->executeSchemaDiff($fromSchema, $toSchema);
        } finally {
            $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    /**
     * The tables that stay in utf8mb3 keep the tables linked to them by foreign keys in utf8mb3 too, otherwise their
     * foreign keys could not be created again.
     */
    protected function excludeLinkedTableNames(Schema $schema, array $tableNames, array $keptTableNames): array
    {
        $tableNames = array_values(array_diff($tableNames, $keptTableNames));

        do {
            $count = count($tableNames);
            foreach ($this->getForeignKeysOfTables($schema, $tableNames) as $row) {
                $linked = [$row['table'], $row['foreignKey']->getForeignTableName()];
                if (!empty(array_intersect($linked, $keptTableNames))) {
                    $keptTableNames = array_unique(array_merge($keptTableNames, $linked));
                    $tableNames = array_values(array_diff($tableNames, $linked));
                }
            }
        } while ($count !== count($tableNames));

        return $tableNames;
    }

    /**
     * The foreign keys that a table of the list has or that refer to a table of the list.
     */
    protected function getForeignKeysOfTables(Schema $schema, array $tableNames): array
    {
        $result = [];
        foreach ($schema->getTables() as $table) {
            foreach ($table->getForeignKeys() as $foreignKey) {
                if (in_array($table->getName(), $tableNames, true) || in_array($foreignKey->getForeignTableName(), $tableNames, true)) {
                    $result[] = ['table' => $table->getName(), 'foreignKey' => $foreignKey];
                }
            }
        }

        return $result;
    }

    protected function executeSchemaDiff(Schema $fromSchema, Schema $toSchema): void
    {
        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getDbal()->executeStatement($sql);
        }
    }

    /**
     * The tables in utf8mb3 and the tables with columns in utf8mb3.
     */
    protected function getUtf8mb3TableNames(): array
    {
        $tableNames = $this->getDbal()->fetchFirstColumn(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' AND (table_collation LIKE 'utf8\_%' OR table_collation LIKE 'utf8mb3\_%')"
        );

        $columnTableNames = $this->getDbal()->fetchFirstColumn(
            "SELECT DISTINCT table_name FROM information_schema.columns WHERE table_schema = DATABASE() AND character_set_name IN ('utf8', 'utf8mb3')"
        );

        return array_values(array_unique(array_merge($tableNames, $columnTableNames)));
    }

    /**
     * An index of InnoDB is at most 3072 bytes long, a character of utf8mb4 takes 4 bytes.
     */
    protected function getTableNamesWithTooLongIndex(): array
    {
        return $this->getDbal()->fetchFirstColumn(
            "SELECT DISTINCT t.table_name FROM (
                SELECT s.table_name, s.index_name, SUM(CASE WHEN c.character_maximum_length IS NULL THEN 8 ELSE COALESCE(s.sub_part, c.character_maximum_length) * 4 END) AS length
                FROM information_schema.statistics s
                JOIN information_schema.columns c ON c.table_schema = s.table_schema AND c.table_name = s.table_name AND c.column_name = s.column_name
                WHERE s.table_schema = DATABASE() AND s.index_type = 'BTREE'
                GROUP BY s.table_name, s.index_name
            ) t WHERE t.length > " . self::MAX_INDEX_LENGTH
        );
    }

    /**
     * Every connection type is a subtype of Connection now: the settings are moved from the "data" column to the
     * table of the subtype. The types of the modules are migrated by the migrations of the modules. The "data"
     * column is kept, the modules read it.
     */
    protected function migrateConnection(): void
    {
        $subtypes = $this->getConnectionSubtypes();

        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        foreach ($subtypes as $type => $columns) {
            $this->createSubtypeTable($toSchema, 'connection', $type, $columns);
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getDbal()->executeStatement($sql);
        }

        if (!$fromSchema->hasTable('connection') || !$fromSchema->getTable('connection')->hasColumn('data')) {
            return;
        }

        $records = $this->getDbal()->createQueryBuilder()
            ->select('id, type, data')
            ->from('connection')
            ->fetchAllAssociative();

        foreach ($records as $record) {
            if (empty($columns = $subtypes[$record['type']] ?? null)) {
                continue;
            }

            $data = @json_decode((string)$record['data'], true);

            $this->insertSubtypeRecord('connection', $record['type'], $record['id'], $columns, is_array($data) ? $data : []);
        }
    }

    /**
     * Every storage type is a subtype of Storage now: the settings of the local storage are moved from the columns
     * of the storage table to the table of the subtype. The types of the modules are migrated by the modules.
     */
    protected function migrateStorage(): void
    {
        $subtypes = [
            'local' => [
                'path'        => ['path', 'type' => 'string', 'length' => 255, 'default' => 'upload/files'],
                'syncFolders' => ['sync_folders', 'type' => 'boolean', 'notnull' => true, 'default' => false],
            ],
        ];

        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        foreach ($subtypes as $type => $columns) {
            $this->createSubtypeTable($toSchema, 'storage', $type, $columns);
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getDbal()->executeStatement($sql);
        }

        if (!$fromSchema->hasTable('storage')) {
            return;
        }

        $storageTable = $fromSchema->getTable('storage');
        foreach ($subtypes as $type => $columns) {
            $sourceColumns = [];
            foreach ($columns as $field => $options) {
                if ($storageTable->hasColumn($options[0])) {
                    $sourceColumns[$field] = $options[0];
                }
            }

            $records = $this->getDbal()->createQueryBuilder()
                ->select(implode(', ', array_merge(['id'], array_values($sourceColumns))))
                ->from('storage')
                ->where('type = :type')
                ->setParameter('type', $type)
                ->fetchAllAssociative();

            foreach ($records as $record) {
                $data = [];
                foreach ($sourceColumns as $field => $column) {
                    $data[$field] = $record[$column];
                }

                $this->insertSubtypeRecord('storage', $type, $record['id'], $columns, $data);
            }
        }
    }

    /**
     * Every action type is a subtype of Action now: the settings of the type are moved from the columns of the action
     * table and from its "data" field to the table of the subtype. A custom action gets the type "custom", its handler
     * is the former custom type. The types of the modules are migrated by the modules.
     */
    protected function migrateAction(): void
    {
        $subtypes = [
            'webhook' => [
                'webhookUrl' => ['webhook_url', 'type' => 'string', 'length' => 255, 'source' => 'data'],
            ],
            'email' => [
                'targetEntity' => ['target_entity', 'type' => 'string', 'length' => 255, 'source' => 'column'],
                'searchEntity' => ['search_entity', 'type' => 'string', 'length' => 255, 'source' => 'column'],
                'showEmailPreview' => ['show_email_preview', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'data'],
                'mode' => ['mode', 'type' => 'string', 'length' => 255, 'source' => 'data'],
                'connectionId' => ['connection_id', 'type' => 'string', 'length' => 36, 'index' => true, 'source' => 'column'],
                'emailTemplateId' => ['email_template_id', 'type' => 'string', 'length' => 36, 'source' => 'column'],
                'emailTo' => ['email_to', 'type' => 'jsonArray', 'source' => 'data'],
                'emailCc' => ['email_cc', 'type' => 'jsonArray', 'source' => 'data'],
                'emailBcc' => ['email_bcc', 'type' => 'jsonArray', 'source' => 'data'],
                'emailScript' => ['email_script', 'type' => 'text', 'comment' => 'default={{% set emailTo = [\'email1@somedomain.com\', \'email2@somedomain.com\'] %}
{% set emailCc = [] %}
{% set emailBcc = [] %}
{% set emailTemplateId = \'some-id\' %}}', 'source' => 'data'],
            ],
            'create' => [
                'targetEntity' => ['target_entity', 'type' => 'string', 'length' => 255, 'source' => 'column'],
                'searchEntity' => ['search_entity', 'type' => 'string', 'length' => 255, 'source' => 'column'],
                'updateType' => ['update_type', 'type' => 'string', 'length' => 255, 'source' => 'data'],
                'updateFields' => ['update_fields', 'type' => 'jsonObject', 'source' => 'data'],
                'updateScript' => ['update_script', 'type' => 'text', 'source' => 'data'],
            ],
            'update' => [
                'targetEntity' => ['target_entity', 'type' => 'string', 'length' => 255, 'source' => 'column'],
                'applyToPreselectedRecords' => ['apply_to_preselected_records', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'data'],
                'searchEntity' => ['search_entity', 'type' => 'string', 'length' => 255, 'source' => 'column'],
                'updateType' => ['update_type', 'type' => 'string', 'length' => 255, 'source' => 'data'],
                'updateFields' => ['update_fields', 'type' => 'jsonObject', 'source' => 'data'],
                'updateScript' => ['update_script', 'type' => 'text', 'source' => 'data'],
            ],
            'createOrUpdate' => [
                'targetEntity' => ['target_entity', 'type' => 'string', 'length' => 255, 'source' => 'column'],
                'searchEntity' => ['search_entity', 'type' => 'string', 'length' => 255, 'source' => 'column'],
                'updateType' => ['update_type', 'type' => 'string', 'length' => 255, 'source' => 'data'],
                'updateFields' => ['update_fields', 'type' => 'jsonObject', 'source' => 'data'],
                'updateScript' => ['update_script', 'type' => 'text', 'source' => 'data'],
            ],
            'delete' => [
                'targetEntity' => ['target_entity', 'type' => 'string', 'length' => 255, 'source' => 'column'],
                'applyToPreselectedRecords' => ['apply_to_preselected_records', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'data'],
                'deletePermanently' => ['delete_permanently', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'data'],
                'searchEntity' => ['search_entity', 'type' => 'string', 'length' => 255, 'source' => 'column'],
            ],
            'set' => [
            ],
            'custom' => [
                'handler' => ['handler', 'type' => 'string', 'length' => 255, 'source' => 'handler'],
                'searchEntity' => ['search_entity', 'type' => 'string', 'length' => 255, 'source' => 'column'],
                'applyToPreselectedRecords' => ['apply_to_preselected_records', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'data'],
            ],
        ];

        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        foreach ($subtypes as $type => $columns) {
            $this->createSubtypeTable($toSchema, 'action', $type, $columns);
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getDbal()->executeStatement($sql);
        }

        if (!$fromSchema->hasTable('action')) {
            return;
        }

        $records = $this->getDbal()->createQueryBuilder()
            ->select('*')
            ->from($this->getDbal()->quoteIdentifier('action'))
            ->fetchAllAssociative();

        foreach ($records as $record) {
            $type = (string)$record['type'];
            $handler = null;
            if (str_starts_with($type, 'custom') && $type !== 'custom') {
                $handler = substr($type, strlen('custom'));
                $type = 'custom';

                $this->getDbal()->createQueryBuilder()
                    ->update($this->getDbal()->quoteIdentifier('action'))
                    ->set('type', ':type')
                    ->where('id = :id')
                    ->setParameter('type', $type)
                    ->setParameter('id', $record['id'])
                    ->executeStatement();
            }

            if (empty($columns = $subtypes[$type] ?? null)) {
                continue;
            }

            $this->insertSubtypeRecord('action', $type, $record['id'], $columns, $this->getRecordSettings($record, $columns, $handler));
        }
    }

    /**
     * Every attribute type is a subtype of Attribute now: the settings of the type are moved from the columns of the
     * attribute table and from its "data" field to the table of the subtype. The types of the modules are migrated by
     * the modules.
     */
    protected function migrateAttribute(): void
    {
        $subtypes = [
            'varchar' => [
                'isMultilang' => ['is_multilang', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'column'],
                'notNull' => ['not_null', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'column'],
                'maxLength' => ['max_length', 'type' => 'integer', 'source' => 'data'],
                'countBytesInsteadOfCharacters' => ['count_bytes_instead_of_characters', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'data'],
                'trim' => ['trim', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'column'],
                'pattern' => ['pattern', 'type' => 'string', 'length' => 255, 'source' => 'column'],
                'measureId' => ['measure_id', 'type' => 'string', 'length' => 36, 'index' => true, 'source' => 'column'],
                'defaultUnit' => ['default_unit', 'type' => 'string', 'length' => 255, 'source' => 'column'],
                'prefixEnabled' => ['prefix_enabled', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'column'],
            ],
            'text' => [
                'isMultilang' => ['is_multilang', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'column'],
                'notNull' => ['not_null', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'column'],
                'maxLength' => ['max_length', 'type' => 'integer', 'source' => 'data'],
                'countBytesInsteadOfCharacters' => ['count_bytes_instead_of_characters', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'data'],
                'useDisabledTextareaInViewMode' => ['use_disabled_textarea_in_view_mode', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'column'],
            ],
            'wysiwyg' => [
                'isMultilang' => ['is_multilang', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'column'],
                'notNull' => ['not_null', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'column'],
                'maxLength' => ['max_length', 'type' => 'integer', 'source' => 'data'],
                'countBytesInsteadOfCharacters' => ['count_bytes_instead_of_characters', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'data'],
                'htmlSanitizerId' => ['html_sanitizer_id', 'type' => 'string', 'length' => 36, 'source' => 'column'],
            ],
            'markdown' => [
                'isMultilang' => ['is_multilang', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'column'],
                'notNull' => ['not_null', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'column'],
                'maxLength' => ['max_length', 'type' => 'integer', 'source' => 'data'],
                'countBytesInsteadOfCharacters' => ['count_bytes_instead_of_characters', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'data'],
            ],
            'url' => [
                'isMultilang' => ['is_multilang', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'column'],
                'notNull' => ['not_null', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'column'],
                'maxLength' => ['max_length', 'type' => 'integer', 'source' => 'data'],
                'countBytesInsteadOfCharacters' => ['count_bytes_instead_of_characters', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'data'],
            ],
            'int' => [
                'notNull' => ['not_null', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'column'],
                'min' => ['min', 'type' => 'float', 'source' => 'data'],
                'max' => ['max', 'type' => 'float', 'source' => 'data'],
                'measureId' => ['measure_id', 'type' => 'string', 'length' => 36, 'index' => true, 'source' => 'column'],
                'defaultUnit' => ['default_unit', 'type' => 'string', 'length' => 255, 'source' => 'column'],
                'prefixEnabled' => ['prefix_enabled', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'column'],
            ],
            'float' => [
                'notNull' => ['not_null', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'column'],
                'min' => ['min', 'type' => 'float', 'source' => 'data'],
                'max' => ['max', 'type' => 'float', 'source' => 'data'],
                'measureId' => ['measure_id', 'type' => 'string', 'length' => 36, 'index' => true, 'source' => 'column'],
                'defaultUnit' => ['default_unit', 'type' => 'string', 'length' => 255, 'source' => 'column'],
                'prefixEnabled' => ['prefix_enabled', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'column'],
                'amountOfDigitsAfterComma' => ['amount_of_digits_after_comma', 'type' => 'integer', 'source' => 'column'],
            ],
            'rangeInt' => [
                'measureId' => ['measure_id', 'type' => 'string', 'length' => 36, 'index' => true, 'source' => 'column'],
                'defaultUnit' => ['default_unit', 'type' => 'string', 'length' => 255, 'source' => 'column'],
            ],
            'rangeFloat' => [
                'measureId' => ['measure_id', 'type' => 'string', 'length' => 36, 'index' => true, 'source' => 'column'],
                'defaultUnit' => ['default_unit', 'type' => 'string', 'length' => 255, 'source' => 'column'],
                'amountOfDigitsAfterComma' => ['amount_of_digits_after_comma', 'type' => 'integer', 'source' => 'column'],
            ],
            'bool' => [
                'isMultilang' => ['is_multilang', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'column'],
                'notNull' => ['not_null', 'type' => 'boolean', 'notnull' => true, 'default' => true, 'source' => 'column'],
            ],
            'date' => [
                'defaultDate' => ['default_date', 'type' => 'string', 'length' => 255, 'source' => 'column'],
            ],
            'datetime' => [
                'defaultDate' => ['default_date', 'type' => 'string', 'length' => 255, 'source' => 'column'],
            ],
            'enum' => [
                'options' => ['options', 'type' => 'jsonArray', 'source' => 'data'],
                'optionColors' => ['option_colors', 'type' => 'jsonArray', 'source' => 'data'],
                'conditionalDisableOptions' => ['conditional_disable_options', 'type' => 'jsonArray', 'source' => 'column'],
            ],
            'multiEnum' => [
                'options' => ['options', 'type' => 'jsonArray', 'source' => 'data'],
                'optionColors' => ['option_colors', 'type' => 'jsonArray', 'source' => 'data'],
                'conditionalDisableOptions' => ['conditional_disable_options', 'type' => 'jsonArray', 'source' => 'column'],
            ],
            'link' => [
                'entityType' => ['entity_type', 'type' => 'string', 'length' => 255, 'source' => 'data'],
                'entityField' => ['entity_field', 'type' => 'string', 'length' => 255, 'source' => 'data'],
                'dropdown' => ['dropdown', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'data'],
                'selectPageSize' => ['select_page_size', 'type' => 'integer', 'source' => 'data'],
                'conditionalDisableOptions' => ['conditional_disable_options', 'type' => 'jsonArray', 'source' => 'column'],
            ],
            'linkMultiple' => [
                'entityType' => ['entity_type', 'type' => 'string', 'length' => 255, 'source' => 'data'],
                'entityField' => ['entity_field', 'type' => 'string', 'length' => 255, 'source' => 'data'],
                'dropdown' => ['dropdown', 'type' => 'boolean', 'notnull' => true, 'default' => false, 'source' => 'data'],
                'selectPageSize' => ['select_page_size', 'type' => 'integer', 'source' => 'data'],
                'conditionalDisableOptions' => ['conditional_disable_options', 'type' => 'jsonArray', 'source' => 'column'],
            ],
            'file' => [
                'fileTypeId' => ['file_type_id', 'type' => 'string', 'length' => 36, 'index' => true, 'source' => 'column'],
            ],
            'array' => [
            ],
            'composite' => [
            ],
        ];

        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        foreach ($subtypes as $type => $columns) {
            $this->createSubtypeTable($toSchema, 'attribute', $type, $columns);
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getDbal()->executeStatement($sql);
        }

        if (!$fromSchema->hasTable('attribute')) {
            return;
        }

        $records = $this->getDbal()->createQueryBuilder()
            ->select('*')
            ->from($this->getDbal()->quoteIdentifier('attribute'))
            ->where('type IN (:types)')
            ->setParameter('types', array_keys($subtypes), Connection::PARAM_STR_ARRAY)
            ->fetchAllAssociative();

        foreach ($records as $record) {
            $columns = $subtypes[$record['type']];

            $settings = $this->getRecordSettings($record, $columns, null);
            foreach ($columns as $field => $options) {
                // a JSON column keeps the encoded value
                if ($options['source'] === 'column' && in_array($options['type'], ['jsonArray', 'jsonObject']) && is_string($settings[$field])) {
                    $settings[$field] = @json_decode($settings[$field], true);
                }
            }

            $this->insertSubtypeRecord('attribute', $record['type'], $record['id'], $columns, $settings);
        }
    }

    /**
     * The settings of a record of a supertype: a setting was stored in a column of the supertype table or in its
     * "data" field.
     */
    protected function getRecordSettings(array $record, array $columns, ?string $handler): array
    {
        $data = @json_decode((string)($record['data'] ?? ''), true);
        $virtualFields = is_array($data['field'] ?? null) ? $data['field'] : [];

        $settings = [];
        foreach ($columns as $field => $options) {
            switch ($options['source']) {
                case 'column':
                    $settings[$field] = $record[$options[0]] ?? null;
                    break;
                case 'data':
                    $settings[$field] = $virtualFields[$field] ?? null;
                    break;
                case 'handler':
                    $settings[$field] = $handler;
                    break;
            }
        }

        return $settings;
    }

    /**
     * The settings of the connection types: the field, its column and the column options.
     */
    protected function getConnectionSubtypes(): array
    {
        return [
            'atrocore' => [
                'atrocoreUrl' => ['atrocore_url', 'type' => 'string', 'length' => 255],
                'atrocoreToken' => ['atrocore_token', 'type' => 'string', 'length' => 2048],
                'verifySsl' => ['verify_ssl', 'type' => 'boolean', 'notnull' => true, 'default' => true],
                'httpBasicEnabled' => ['http_basic_enabled', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                'httpBasicUsername' => ['http_basic_username', 'type' => 'string', 'length' => 255],
                'httpBasicPassword' => ['http_basic_password', 'type' => 'string', 'length' => 255],
            ],
            'pdoSql' => [
                'user' => ['user', 'type' => 'string', 'length' => 255],
                'password' => ['password', 'type' => 'string', 'length' => 255],
                'dsn' => ['dsn', 'type' => 'string', 'length' => 255],
                'server' => ['server', 'type' => 'string', 'length' => 255],
            ],
            'mysql' => [
                'host' => ['host', 'type' => 'string', 'length' => 255, 'default' => 'localhost'],
                'dbName' => ['db_name', 'type' => 'string', 'length' => 255],
                'port' => ['port', 'type' => 'string', 'length' => 255],
                'user' => ['user', 'type' => 'string', 'length' => 255],
                'password' => ['password', 'type' => 'string', 'length' => 255],
            ],
            'psql' => [
                'host' => ['host', 'type' => 'string', 'length' => 255, 'default' => 'localhost'],
                'dbName' => ['db_name', 'type' => 'string', 'length' => 255],
                'port' => ['port', 'type' => 'string', 'length' => 255],
                'user' => ['user', 'type' => 'string', 'length' => 255],
                'password' => ['password', 'type' => 'string', 'length' => 255],
            ],
            'msql' => [
                'host' => ['host', 'type' => 'string', 'length' => 255, 'default' => 'localhost'],
                'dbName' => ['db_name', 'type' => 'string', 'length' => 255],
                'port' => ['port', 'type' => 'string', 'length' => 255],
                'user' => ['user', 'type' => 'string', 'length' => 255],
                'password' => ['password', 'type' => 'string', 'length' => 255],
                'additionalParameters' => ['additional_parameters', 'type' => 'text'],
            ],
            'ftp' => [
                'host' => ['host', 'type' => 'string', 'length' => 255, 'default' => 'localhost'],
                'port' => ['port', 'type' => 'string', 'length' => 255],
                'user' => ['user', 'type' => 'string', 'length' => 255],
                'password' => ['password', 'type' => 'string', 'length' => 255],
                'ftpSsl' => ['ftp_ssl', 'type' => 'boolean', 'notnull' => true, 'default' => false],
            ],
            'sftp' => [
                'host' => ['host', 'type' => 'string', 'length' => 255, 'default' => 'localhost'],
                'port' => ['port', 'type' => 'string', 'length' => 255],
                'user' => ['user', 'type' => 'string', 'length' => 255],
                'password' => ['password', 'type' => 'string', 'length' => 255],
            ],
            'oauth2' => [
                'user' => ['user', 'type' => 'string', 'length' => 255],
                'password' => ['password', 'type' => 'string', 'length' => 255],
                'oauthGrantType' => ['oauth_grant_type', 'type' => 'string', 'length' => 255, 'default' => 'client_credentials'],
                'oauthUrl' => ['oauth_url', 'type' => 'string', 'length' => 255],
                'oauthClientId' => ['oauth_client_id', 'type' => 'string', 'length' => 255],
                'oauthClientSecret' => ['oauth_client_secret', 'type' => 'string', 'length' => 255],
                'verifySsl' => ['verify_ssl', 'type' => 'boolean', 'notnull' => true, 'default' => true],
                'httpBasicEnabled' => ['http_basic_enabled', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                'httpBasicUsername' => ['http_basic_username', 'type' => 'string', 'length' => 255],
                'httpBasicPassword' => ['http_basic_password', 'type' => 'string', 'length' => 255],
            ],
            'oauth1' => [
                'oauthConsumerKey' => ['oauth_consumer_key', 'type' => 'string', 'length' => 255],
                'oauthConsumerSecret' => ['oauth_consumer_secret', 'type' => 'string', 'length' => 255],
                'requestTokenUrl' => ['request_token_url', 'type' => 'string', 'length' => 255],
                'accessTokenUrl' => ['access_token_url', 'type' => 'string', 'length' => 255],
                'oauthVerifier' => ['oauth_verifier', 'type' => 'string', 'length' => 255],
                'oauthToken' => ['oauth_token', 'type' => 'string', 'length' => 2048],
                'oauthTokenSecret' => ['oauth_token_secret', 'type' => 'string', 'length' => 2048],
                'storeUrl' => ['store_url', 'type' => 'string', 'length' => 255],
                'apiTestUrl' => ['api_test_url', 'type' => 'string', 'length' => 255, 'default' => 'https://staging.davidsonsinc.com/rest/V1/products/011418204'],
                'verifySsl' => ['verify_ssl', 'type' => 'boolean', 'notnull' => true, 'default' => true],
            ],
            'otto' => [
                'oauthGrantType' => ['oauth_grant_type', 'type' => 'string', 'length' => 255, 'default' => 'client_credentials'],
                'oauthUrl' => ['oauth_url', 'type' => 'string', 'length' => 255],
                'oauthClientId' => ['oauth_client_id', 'type' => 'string', 'length' => 255],
                'oauthClientSecret' => ['oauth_client_secret', 'type' => 'string', 'length' => 255],
                'oauthScope' => ['oauth_scope', 'type' => 'string', 'length' => 255, 'default' => 'partnerId installation developer'],
                'vendorOauthUrl' => ['vendor_oauth_url', 'type' => 'string', 'length' => 255],
                'vendorOauthScopes' => ['vendor_oauth_scopes', 'type' => 'jsonArray'],
                'verifySsl' => ['verify_ssl', 'type' => 'boolean', 'notnull' => true, 'default' => true],
            ],
            'cookie' => [
                'payload' => ['payload', 'type' => 'text'],
                'loginUrl' => ['login_url', 'type' => 'string', 'length' => 255],
                'user' => ['user', 'type' => 'string', 'length' => 255],
                'password' => ['password', 'type' => 'string', 'length' => 255],
                'verifySsl' => ['verify_ssl', 'type' => 'boolean', 'notnull' => true, 'default' => true],
                'httpBasicEnabled' => ['http_basic_enabled', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                'httpBasicUsername' => ['http_basic_username', 'type' => 'string', 'length' => 255],
                'httpBasicPassword' => ['http_basic_password', 'type' => 'string', 'length' => 255],
            ],
            'smtp' => [
                'smtpSecurity' => ['smtp_security', 'type' => 'string', 'length' => 255],
                'smtpServer' => ['smtp_server', 'type' => 'string', 'length' => 255],
                'smtpPassword' => ['smtp_password', 'type' => 'string', 'length' => 255],
                'smtpPort' => ['smtp_port', 'type' => 'integer'],
                'smtpUsername' => ['smtp_username', 'type' => 'string', 'length' => 255],
                'outboundEmailFromName' => ['outbound_email_from_name', 'type' => 'string', 'length' => 255],
                'outboundEmailFromAddress' => ['outbound_email_from_address', 'type' => 'string', 'length' => 255],
                'smtpAuthType' => ['smtp_auth_type', 'type' => 'string', 'length' => 255, 'default' => 'basic'],
                'smtpClientId' => ['smtp_client_id', 'type' => 'string', 'length' => 255],
                'smtpClientSecret' => ['smtp_client_secret', 'type' => 'string', 'length' => 255],
                'smtpOauthScope' => ['smtp_oauth_scope', 'type' => 'string', 'length' => 255],
                'smtpOauthAuthorizeUrl' => ['smtp_oauth_authorize_url', 'type' => 'string', 'length' => 255],
                'smtpOauthTokenUrl' => ['smtp_oauth_token_url', 'type' => 'string', 'length' => 255],
                'smtpAccessData' => ['smtp_access_data', 'type' => 'text'],
            ],
            'vertica' => [
                'host' => ['host', 'type' => 'string', 'length' => 255, 'default' => 'localhost'],
                'dbName' => ['db_name', 'type' => 'string', 'length' => 255],
                'port' => ['port', 'type' => 'string', 'length' => 255],
                'user' => ['user', 'type' => 'string', 'length' => 255],
                'password' => ['password', 'type' => 'string', 'length' => 255],
            ],
            'tokenAuthApi' => [
                'payload' => ['payload', 'type' => 'text'],
                'loginUrl' => ['login_url', 'type' => 'string', 'length' => 255],
                'user' => ['user', 'type' => 'string', 'length' => 255],
                'password' => ['password', 'type' => 'string', 'length' => 255],
                'headers' => ['headers', 'type' => 'text'],
                'verifySsl' => ['verify_ssl', 'type' => 'boolean', 'notnull' => true, 'default' => true],
                'httpBasicEnabled' => ['http_basic_enabled', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                'httpBasicUsername' => ['http_basic_username', 'type' => 'string', 'length' => 255],
                'httpBasicPassword' => ['http_basic_password', 'type' => 'string', 'length' => 255],
            ],
            'apiKey' => [
                'apiKeyName' => ['api_key_name', 'type' => 'string', 'length' => 255, 'default' => 'X-API-Key'],
                'apiKeyValue' => ['api_key_value', 'type' => 'string', 'length' => 2048],
                'verifySsl' => ['verify_ssl', 'type' => 'boolean', 'notnull' => true, 'default' => true],
                'httpBasicEnabled' => ['http_basic_enabled', 'type' => 'boolean', 'notnull' => true, 'default' => false],
                'httpBasicUsername' => ['http_basic_username', 'type' => 'string', 'length' => 255],
                'httpBasicPassword' => ['http_basic_password', 'type' => 'string', 'length' => 255],
            ],
        ];
    }

    /**
     * The table of a subtype: the id of the supertype record, which links the subtype record to it and removes it
     * together with it, the columns of the subtype fields.
     */
    protected function createSubtypeTable(Schema $schema, string $supertypeTable, string $type, array $columns): void
    {
        $tableName = $this->getSubtypeTableName($supertypeTable, $type);
        if ($schema->hasTable($tableName)) {
            return;
        }

        $table = $schema->createTable($tableName);
        $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);

        $indexes = [];
        foreach ($columns as $options) {
            $column = array_shift($options);
            $type = $options['type'];
            if (!empty($options['index'])) {
                $indexes[] = $column;
            }
            unset($options['type'], $options['index'], $options['source']);
            $table->addColumn($column, $type, array_merge(['notnull' => false], $options));
        }

        $table->setPrimaryKey(['id']);

        $table->addForeignKeyConstraint($supertypeTable, ['id'], ['id'], ['onDelete' => 'CASCADE'], $this->generateForeignKeyName($tableName . '_id'));

        foreach ($indexes as $column) {
            $table->addIndex([$column], $this->generateIndexName($tableName . '_' . $column));
        }
    }

    protected function insertSubtypeRecord(string $supertypeTable, string $type, string $supertypeId, array $columns, array $data): void
    {
        $tableName = $this->getSubtypeTableName($supertypeTable, $type);
        $conn = $this->getDbal();

        $exists = $conn->createQueryBuilder()
            ->select('id')
            ->from($tableName)
            ->where('id = :supertypeId')
            ->setParameter('supertypeId', $supertypeId)
            ->fetchOne();

        if ($exists !== false) {
            return;
        }

        $qb = $conn->createQueryBuilder()
            ->insert($tableName)
            ->setValue('id', ':id')
            ->setParameter('id', $supertypeId);

        foreach ($columns as $field => $options) {
            if (!array_key_exists($field, $data) || $data[$field] === null) {
                continue;
            }

            $value = $data[$field];
            $parameterType = ParameterType::STRING;

            switch ($options['type']) {
                case 'boolean':
                    $value = !empty($value);
                    $parameterType = ParameterType::BOOLEAN;
                    break;
                case 'integer':
                    if ($value === '') {
                        continue 2;
                    }
                    $value = (int)$value;
                    $parameterType = ParameterType::INTEGER;
                    break;
                case 'jsonArray':
                    $value = json_encode($value);
                    break;
                default:
                    $value = is_scalar($value) ? (string)$value : json_encode($value);
            }

            $qb->setValue($conn->quoteIdentifier($options[0]), ":$field");
            $qb->setParameter($field, $value, $parameterType);
        }

        $qb->executeStatement();
    }

    protected function getSubtypeTableName(string $supertypeTable, string $type): string
    {
        return $supertypeTable . '_type_' . strtolower(preg_replace('/([A-Z])/', '_$1', $type));
    }

    protected function generateIndexName(string $name): string
    {
        if (strlen($name) > 55) {
            $name = md5($name);
        }

        return 'IDX_' . strtoupper($name);
    }

    protected function generateForeignKeyName(string $name): string
    {
        if (strlen($name) > 55) {
            $name = md5($name);
        }

        return 'FK_' . strtoupper($name);
    }
}
