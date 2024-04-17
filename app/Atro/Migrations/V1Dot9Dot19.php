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

declare(strict_types = 1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Espo\Core\Exceptions\Error;

class V1Dot9Dot19 extends Base
{
    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        if (!$toSchema->hasTable('action_set_linker')) {
            $table = $toSchema->createTable('action_set_linker');
            $table->addColumn('id', 'string', ['length' => 24]);
            $table->addColumn('deleted', 'boolean', ['default' => false, 'notnull' => false]);
            $table->addColumn('created_at', 'datetime', ['notnull' => false]);
            $table->addColumn('modified_at', 'datetime', ['notnull' => false]);
            $table->addColumn('modified_by_id', 'string', ['notnull' => false, 'length' => 24]);
            $table->addColumn('created_by_id', 'string', ['notnull' => false, 'length' => 24]);
            $table->addColumn('sort_order', 'integer', ['notnull' => false]);
            $table->addColumn('is_active', 'boolean', ['default' => true]);
            $table->addColumn('action_id', 'string', ['notnull' => false, 'length' => 24]);
            $table->addColumn('set_id', 'string', ['notnull' => false, 'length' => 24]);

            $table->addUniqueIndex(['deleted', 'action_id', 'set_id'], 'IDX_ACTION_SET_LINKER_UNIQUE_RELATION');
            $table->addIndex(['created_by_id', 'deleted'], 'IDX_ACTION_SET_LINKER_CREATED_BY_ID');
            $table->addIndex(['modified_by_id', 'deleted'], 'IDX_ACTION_SET_LINKER_MODIFIED_BY_ID');
            $table->addIndex(['action_id', 'deleted'], 'IDX_ACTION_SET_LINKER_ACTION_ID');
            $table->addIndex(['set_id', 'deleted'], 'IDX_ACTION_SET_LINKER_SET_ID');
            $table->addIndex(['created_at', 'deleted'], 'IDX_ACTION_SET_LINKER_CREATED_AT');
            $table->addIndex(['modified_at', 'deleted'], 'IDX_ACTION_SET_LINKER_MODIFIED_AT');

            $table->setPrimaryKey(['id']);
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->exec($sql);
        }
    }

    public function down(): void
    {
        throw new Error("Downgrade is prohibited");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
