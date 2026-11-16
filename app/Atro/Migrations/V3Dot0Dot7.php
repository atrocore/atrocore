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
use Atro\Core\Utils\IdGenerator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;

class V3Dot0Dot7 extends Base
{
    protected const string COMPILED_DIR = 'data/custom-code/Compiled';

    /**
     * The columns of the subtype tables: the type of the rule => the columns of its settings. A type is migrated
     * only when the matching rules have all its columns, otherwise the module of the type is not installed.
     */
    protected const array SUBTYPES = [
        'fieldEqual'              => ['field', 'attribute_id'],
        'fieldSimilar'            => ['field', 'attribute_id'],
        'fieldContains'           => ['field', 'attribute_id'],
        'set'                     => ['operator'],
        'expression'              => [],
        'fieldSimilarFuzzySearch' => ['field', 'attribute_id'],
        'fieldSimilarLevenshtein' => ['field', 'attribute_id', 'levenshtein_distance'],
    ];

    protected const array COLUMNS = [
        'field'                => ['type' => 'string', 'length' => 255],
        'attribute_id'         => ['type' => 'string', 'length' => 36, 'index' => true],
        'operator'             => ['type' => 'string', 'length' => 255],
        'levenshtein_distance' => ['type' => 'integer', 'default' => 10],
    ];

    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-11-12 09:00:00');
    }

    /**
     * The candidates of a matching are selected by its blocking rules. The matching rules of the existing matchings
     * selected the candidates before, so they are copied as blocking rules, which keeps the selected candidates the same.
     * The settings of every rule type are moved to the table of its subtype, for the matching rules and for the
     * blocking rules.
     */
    public function up(): void
    {
        $this->renameMatchedRecordColumns();

        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        if (!$toSchema->hasTable('matching') || !$toSchema->hasTable('matching_rule')) {
            return;
        }

        $matchingTable = $toSchema->getTable('matching');
        if (!$matchingTable->hasColumn('blocking_operator')) {
            $matchingTable->addColumn('blocking_operator', 'string', ['length' => 255, 'notnull' => false, 'default' => 'or']);
        }

        $subtypes = array_filter(
            self::SUBTYPES,
            fn(array $columns) => empty(array_diff($columns, array_keys($toSchema->getTable('matching_rule')->getColumns())))
        );

        $this->createBlockingRuleTables($toSchema);

        foreach (['matching_rule', 'blocking_rule'] as $supertypeTable) {
            foreach ($subtypes as $type => $columns) {
                $this->createSubtypeTable($toSchema, $supertypeTable, $type, $columns);
            }
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getDbal()->executeStatement($sql);
        }

        $this->getDbal()->createQueryBuilder()
            ->update('matching')
            ->set('blocking_operator', ':or')
            ->where('blocking_operator IS NULL')
            ->setParameter('or', 'or')
            ->executeStatement();

        $this->createSubtypeRecords('matching_rule', $subtypes);
        $this->copyMatchingRulesToBlockingRules($subtypes);
        $this->createSubtypeRecords('blocking_rule', $subtypes);

        foreach (glob(self::COMPILED_DIR . '/MatchingRule/Where*.php') ?: [] as $file) {
            unlink($file);
        }

        if ($toSchema->hasTable('matched_record')) {
            $this->mergeMirroredMatchedRecords();
        }
    }

    protected function renameMatchedRecordColumns(): void
    {
        $conn = $this->getDbal();
        $schemaManager = $conn->createSchemaManager();

        if (!$schemaManager->tablesExist(['matched_record'])) {
            return;
        }

        $columns = array_keys($schemaManager->listTableColumns('matched_record'));

        $renames = [
            'match_subject_entity'      => ['source_entity', 'original_entity'],
            'match_subject_entity_id'   => ['source_entity_id', 'original_entity_id'],
            'match_candidate_entity'    => ['master_entity', 'matched_entity'],
            'match_candidate_entity_id' => ['master_entity_id', 'matched_entity_id'],
        ];

        foreach ($renames as $new => $olds) {
            foreach ($olds as $old) {
                if (in_array($old, $columns) && !in_array($new, $columns)) {
                    $conn->executeStatement('ALTER TABLE matched_record RENAME COLUMN ' . $conn->quoteIdentifier($old) . ' TO ' . $conn->quoteIdentifier($new));
                    $columns[] = $new;
                }
            }
        }

        $indexes = $schemaManager->listTableIndexes('matched_record');
        foreach (['match_subject' => ['source', 'original'], 'match_candidate' => ['master', 'matched']] as $new => $olds) {
            foreach ($olds as $old) {
                if (isset($indexes["idx_matched_record_{$old}_entity"])) {
                    $schemaManager->dropIndex("idx_matched_record_{$old}_entity", 'matched_record');
                }
            }
            if (!isset($indexes["idx_matched_record_{$new}_entity"])) {
                $schemaManager->createIndex(new Index("idx_matched_record_{$new}_entity", ['type', "{$new}_entity", "{$new}_entity_id"]), 'matched_record');
            }
        }
    }

    protected function mergeMirroredMatchedRecords(): void
    {
        $conn = $this->getDbal();

        $matchings = $conn->createQueryBuilder()
            ->select('id, number, entity, master_entity')
            ->from('matching')
            ->where('type = :type')
            ->setParameter('type', 'duplicate')
            ->fetchAllAssociative();

        foreach ($matchings as $matching) {
            while (true) {
                $pairs = $conn->createQueryBuilder()
                    ->select('a.id AS a_id, a.match_subject_entity_id AS a_match_subject, a.match_candidate_entity_id AS a_match_candidate, a.score AS a_score, a.has_cluster AS a_has_cluster, b.id AS b_id, b.score AS b_score, b.has_cluster AS b_has_cluster')
                    ->from('matched_record', 'a')
                    ->innerJoin('a', 'matched_record', 'b', 'b.matching_id = a.matching_id AND b.match_subject_entity_id = a.match_candidate_entity_id AND b.match_candidate_entity_id = a.match_subject_entity_id AND b.deleted = a.deleted')
                    ->where('a.matching_id = :matchingId')
                    ->andWhere('a.deleted = :false')
                    ->andWhere('a.id < b.id')
                    ->setParameter('matchingId', $matching['id'])
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->setMaxResults(5000)
                    ->fetchAllAssociative();

                if (empty($pairs)) {
                    break;
                }

                foreach ($pairs as $pair) {
                    [$keepId, $removeId] = strcmp($pair['a_match_subject'], $pair['a_match_candidate']) < 0
                        ? [$pair['a_id'], $pair['b_id']]
                        : [$pair['b_id'], $pair['a_id']];

                    $conn->createQueryBuilder()
                        ->update('matched_record')
                        ->set('score', ':score')
                        ->set('has_cluster', ':hasCluster')
                        ->where('id = :id')
                        ->setParameter('score', max((int)$pair['a_score'], (int)$pair['b_score']), ParameterType::INTEGER)
                        ->setParameter('hasCluster', !empty($pair['a_has_cluster']) || !empty($pair['b_has_cluster']), ParameterType::BOOLEAN)
                        ->setParameter('id', $keepId)
                        ->executeStatement();

                    $conn->createQueryBuilder()
                        ->update('cluster_item')
                        ->set('matched_record_id', ':keepId')
                        ->where('matched_record_id = :removeId')
                        ->setParameter('keepId', $keepId)
                        ->setParameter('removeId', $removeId)
                        ->executeStatement();

                    $conn->createQueryBuilder()
                        ->delete('matched_record')
                        ->where('id = :id')
                        ->setParameter('id', $removeId)
                        ->executeStatement();
                }
            }

            $lastId = '';
            while (true) {
                $rows = $conn->createQueryBuilder()
                    ->select('id, match_subject_entity_id, match_candidate_entity_id')
                    ->from('matched_record')
                    ->where('matching_id = :matchingId')
                    ->andWhere('deleted = :false')
                    ->andWhere('id > :lastId')
                    ->orderBy('id')
                    ->setParameter('matchingId', $matching['id'])
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->setParameter('lastId', $lastId)
                    ->setMaxResults(5000)
                    ->fetchAllAssociative();

                if (empty($rows)) {
                    break;
                }

                foreach ($rows as $row) {
                    $lastId = $row['id'];

                    if (strcmp($row['match_subject_entity_id'], $row['match_candidate_entity_id']) <= 0) {
                        continue;
                    }

                    $conn->createQueryBuilder()
                        ->update('matched_record')
                        ->set('match_subject_entity_id', ':matchSubjectId')
                        ->set('match_candidate_entity_id', ':matchCandidateId')
                        ->set('hash', ':hash')
                        ->where('id = :id')
                        ->setParameter('matchSubjectId', $row['match_candidate_entity_id'])
                        ->setParameter('matchCandidateId', $row['match_subject_entity_id'])
                        ->setParameter('hash', md5(implode('_', [$matching['number'], $matching['entity'], $row['match_candidate_entity_id'], $matching['master_entity'], $row['match_subject_entity_id']])))
                        ->setParameter('id', $row['id'])
                        ->executeStatement();
                }
            }
        }
    }

    protected function createBlockingRuleTables(Schema $schema): void
    {
        if (!$schema->hasTable('blocking_rule')) {
            $table = $schema->createTable('blocking_rule');
            $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('deleted', 'boolean', ['notnull' => false, 'default' => false]);
            $table->addColumn('created_at', 'datetime', ['notnull' => false]);
            $table->addColumn('modified_at', 'datetime', ['notnull' => false]);
            $table->addColumn('type', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('matching_id', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('blocking_rule_set_id', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('created_by_id', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('modified_by_id', 'string', ['length' => 36, 'notnull' => false]);
            if ($this->isPgSQL()) {
                if (!$schema->hasSequence('blocking_rule_number_seq')) {
                    $schema->createSequence('blocking_rule_number_seq');
                }
                $table->addColumn('number', 'integer', ['notnull' => true, 'default' => "nextval('blocking_rule_number_seq')"]);
            } else {
                $table->addColumn('number', 'integer', ['notnull' => true, 'autoincrement' => true]);
                $table->addUniqueIndex(['number']);
            }
            $table->setPrimaryKey(['id']);

            foreach (['matching_id', 'blocking_rule_set_id', 'created_by_id', 'modified_by_id'] as $column) {
                $table->addIndex([$column, 'deleted'], 'IDX_BLOCKING_RULE_' . strtoupper($column));
            }
        }

        if (!$schema->hasTable('user_followed_blocking_rule')) {
            $table = $schema->createTable('user_followed_blocking_rule');
            $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('deleted', 'boolean', ['notnull' => false, 'default' => false]);
            $table->addColumn('created_at', 'datetime', ['notnull' => false]);
            $table->addColumn('modified_at', 'datetime', ['notnull' => false]);
            $table->addColumn('created_by_id', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('modified_by_id', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('blocking_rule_id', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('user_id', 'string', ['length' => 36, 'notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['deleted', 'blocking_rule_id', 'user_id'], 'IDX_USER_FOLLOWED_BLOCKING_RULE_UNIQUE_RELATION');

            foreach (['created_by_id', 'modified_by_id', 'blocking_rule_id', 'user_id', 'created_at', 'modified_at'] as $column) {
                $table->addIndex([$column, 'deleted'], 'IDX_USER_FOLLOWED_BLOCKING_RULE_' . strtoupper($column));
            }
        }
    }

    /**
     * The table of a subtype: its own id, the unique link to the supertype record that is removed together with
     * it, the columns of the subtype fields.
     */
    protected function createSubtypeTable(Schema $schema, string $supertypeTable, string $type, array $columns): void
    {
        $tableName = $this->getSubtypeTableName($supertypeTable, $type);
        if ($schema->hasTable($tableName)) {
            return;
        }

        $table = $schema->createTable($tableName);
        $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
        $table->addColumn('supertype_id', 'string', ['length' => 36, 'notnull' => false]);

        foreach ($columns as $column) {
            $options = self::COLUMNS[$column];
            $table->addColumn($column, $options['type'], array_merge(['notnull' => false], array_diff_key($options, ['type' => true, 'index' => true])));
            if (!empty($options['index'])) {
                $table->addIndex([$column], $this->generateIndexName($tableName . '_' . $column));
            }
        }

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['supertype_id']);

        $foreignKeyName = $this->generateForeignKeyName($tableName . '_supertype_id');
        $table->addForeignKeyConstraint($supertypeTable, ['supertype_id'], ['id'], ['onDelete' => 'CASCADE'], $foreignKeyName);
        $table->addIndex(['supertype_id'], $foreignKeyName);
    }

    /**
     * Every rule gets the record of its subtype with the settings stored in the columns of the rule before.
     */
    protected function createSubtypeRecords(string $supertypeTable, array $subtypes): void
    {
        $legacyColumns = array_map(
            fn($column) => $column->getName(),
            $this->getCurrentSchema()->getTable($supertypeTable)->getColumns()
        );

        $rules = $this->getDbal()->createQueryBuilder()
            ->select('*')
            ->from($supertypeTable)
            ->where('type IN (:types)')
            ->setParameter('types', array_keys($subtypes), Connection::PARAM_STR_ARRAY)
            ->fetchAllAssociative();

        foreach ($rules as $rule) {
            $values = [];
            foreach ($subtypes[$rule['type']] as $column) {
                if (in_array($column, $legacyColumns, true)) {
                    $values[$column] = $rule[$column];
                }
            }

            $this->insertSubtypeRecord($supertypeTable, $rule['type'], $rule['id'], $values);
        }
    }

    protected function insertSubtypeRecord(string $supertypeTable, string $type, string $supertypeId, array $values): void
    {
        $tableName = $this->getSubtypeTableName($supertypeTable, $type);

        $exists = $this->getDbal()->createQueryBuilder()
            ->select('id')
            ->from($tableName)
            ->where('supertype_id = :supertypeId')
            ->setParameter('supertypeId', $supertypeId)
            ->fetchOne();

        if ($exists !== false) {
            return;
        }

        $qb = $this->getDbal()->createQueryBuilder()
            ->insert($tableName)
            ->setValue('id', ':id')
            ->setValue('supertype_id', ':supertypeId')
            ->setParameter('id', IdGenerator::uuid())
            ->setParameter('supertypeId', $supertypeId);

        foreach ($values as $column => $value) {
            if ($value === null) {
                continue;
            }

            $qb->setValue($column, ":$column");
            $qb->setParameter($column, $value, self::COLUMNS[$column]['type'] === 'integer' ? ParameterType::INTEGER : ParameterType::STRING);
        }

        $qb->executeStatement();
    }

    protected function copyMatchingRulesToBlockingRules(array $subtypes): void
    {
        $matchingIds = $this->getDbal()->createQueryBuilder()
            ->select('id')
            ->from('matching')
            ->where('deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchFirstColumn();

        foreach ($matchingIds as $matchingId) {
            $hasBlockingRules = $this->getDbal()->createQueryBuilder()
                ->select('id')
                ->from('blocking_rule')
                ->where('matching_id = :matchingId')
                ->andWhere('deleted = :false')
                ->setParameter('matchingId', $matchingId)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchOne();

            if (!empty($hasBlockingRules)) {
                continue;
            }

            $rules = $this->getDbal()->createQueryBuilder()
                ->select('*')
                ->from('matching_rule')
                ->where('matching_id = :matchingId')
                ->andWhere('matching_rule_set_id IS NULL')
                ->andWhere('deleted = :false')
                ->setParameter('matchingId', $matchingId)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();

            foreach ($rules as $rule) {
                $this->copyRule($rule, $matchingId, null, $subtypes);
            }
        }
    }

    /**
     * The copied sets combine their rules by OR as the sets of the matching rules did when they selected the
     * candidates, so the selected candidates stay the same.
     */
    protected function copyRule(array $rule, ?string $matchingId, ?string $ruleSetId, array $subtypes): void
    {
        $id = IdGenerator::sortableId();
        $now = date('Y-m-d H:i:s');

        $this->getDbal()->createQueryBuilder()
            ->insert('blocking_rule')
            ->setValue('id', ':id')
            ->setValue('deleted', ':false')
            ->setValue('created_at', ':now')
            ->setValue('modified_at', ':now')
            ->setValue('type', ':type')
            ->setValue('matching_id', ':matchingId')
            ->setValue('blocking_rule_set_id', ':ruleSetId')
            ->setValue('created_by_id', ':createdById')
            ->setValue('modified_by_id', ':modifiedById')
            ->setParameter('id', $id)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('now', $now)
            ->setParameter('type', $rule['type'])
            ->setParameter('matchingId', $matchingId)
            ->setParameter('ruleSetId', $ruleSetId)
            ->setParameter('createdById', $rule['created_by_id'] ?? null)
            ->setParameter('modifiedById', $rule['modified_by_id'] ?? null)
            ->executeStatement();

        if (array_key_exists($rule['type'], $subtypes)) {
            $values = [];
            foreach ($subtypes[$rule['type']] as $column) {
                $values[$column] = $rule[$column] ?? null;
            }
            if ($rule['type'] === 'set') {
                $values['operator'] = 'or';
            }

            $this->insertSubtypeRecord('blocking_rule', $rule['type'], $id, $values);
        }

        if ($rule['type'] === 'expression') {
            $number = $this->getDbal()->createQueryBuilder()
                ->select('number')
                ->from('blocking_rule')
                ->where('id = :id')
                ->setParameter('id', $id)
                ->fetchOne();

            $source = self::COMPILED_DIR . "/MatchingRule/Where{$rule['number']}.php";
            if (!empty($number) && file_exists($source)) {
                $code = str_replace(
                    ['namespace Compiled\\MatchingRule;', "final class Where{$rule['number']} "],
                    ['namespace Compiled\\BlockingRule;', "final class Where{$number} "],
                    (string)file_get_contents($source)
                );

                if (!is_dir(self::COMPILED_DIR . '/BlockingRule')) {
                    mkdir(self::COMPILED_DIR . '/BlockingRule', 0775, true);
                }

                file_put_contents(self::COMPILED_DIR . "/BlockingRule/Where{$number}.php", $code);
            }
        }

        if ($rule['type'] === 'set') {
            $children = $this->getDbal()->createQueryBuilder()
                ->select('*')
                ->from('matching_rule')
                ->where('matching_rule_set_id = :ruleSetId')
                ->andWhere('deleted = :false')
                ->setParameter('ruleSetId', $rule['id'])
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();

            foreach ($children as $child) {
                $this->copyRule($child, null, $id, $subtypes);
            }
        }
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
