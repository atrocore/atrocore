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

class V3Dot0Dot15 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-11-25 18:00:00');
    }

    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        if (!$toSchema->hasTable('action_type_erasure')) {
            $table = $toSchema->createTable('action_type_erasure');

            $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('target_entity', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('apply_to_preselected_records', 'boolean', ['notnull' => true, 'default' => false]);
            $table->addColumn('search_entity', 'string', ['length' => 255, 'notnull' => false]);

            $table->setPrimaryKey(['id']);
            $table->addForeignKeyConstraint('action', ['id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_ACTION_TYPE_ERASURE_ID');
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getDbal()->executeStatement($sql);
        }
    }
}
