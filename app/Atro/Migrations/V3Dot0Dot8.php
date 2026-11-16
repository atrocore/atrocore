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

class V3Dot0Dot8 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-11-13 09:00:00');
    }

    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        if (!$toSchema->hasTable('matching_rule')) {
            return;
        }

        if (!$toSchema->hasTable('matching_rule_type_fields_similar')) {
            $table = $toSchema->createTable('matching_rule_type_fields_similar');
            $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('supertype_id', 'string', ['length' => 36, 'notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['supertype_id']);
            $table->addForeignKeyConstraint('matching_rule', ['supertype_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_MATCHING_RULE_TYPE_FIELDS_SIMILAR_SUPERTYPE_ID');
            $table->addIndex(['supertype_id'], 'FK_MATCHING_RULE_TYPE_FIELDS_SIMILAR_SUPERTYPE_ID');
        }

        if (!$toSchema->hasTable('matching_rule_type_attributes_similar')) {
            $table = $toSchema->createTable('matching_rule_type_attributes_similar');
            $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('supertype_id', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('ignore_unavailable_attributes', 'boolean', ['default' => false, 'notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['supertype_id']);
            $table->addForeignKeyConstraint('matching_rule', ['supertype_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_MATCHING_RULE_TYPE_ATTRIBUTES_SIMILAR_SUPERTYPE_ID');
            $table->addIndex(['supertype_id'], 'FK_MATCHING_RULE_TYPE_ATTRIBUTES_SIMILAR_SUPERTYPE_ID');
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getDbal()->executeStatement($sql);
        }
    }
}
