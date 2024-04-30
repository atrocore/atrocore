<?php

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot10Dot7 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-04-30 00:00:00');
    }

    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $table = $toSchema->createTable('action_set_job');
        $table->addColumn('id', 'string', ['length' => 24]);
        $table->addColumn('name', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('deleted', 'boolean', ['default' => false, 'notnull' => false]);
        $table->addColumn('state', 'string', ['default' => 'Pending', 'length' => 255, 'notnull' => false]);
        $table->addColumn('state_message', 'text', ['notnull' => false]);
        $table->addColumn('start', 'datetime', ['notnull' => false]);
        $table->addColumn('end', 'datetime', ['notnull' => false]);
        $table->addColumn('sort_order', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('set_id', 'string', ['length' => 24, 'notnull' => false]);
        $table->addColumn('action_id', 'string', ['length' => 24, 'notnull' => false]);
        $table->addColumn('parent_id', 'string', ['length' => 24, 'notnull' => false]);

        $table->addUniqueIndex(['sort_order'], 'UNIQ_59D263D145AFA4EA');
        $table->addIndex(['set_id', 'deleted'], 'IDX_ACTION_SET_JOB_SET_ID');
        $table->addIndex(['action_id', 'deleted'], 'IDX_ACTION_SET_JOB_ACTION_ID');
        $table->addIndex(['parent_id', 'deleted'], 'IDX_ACTION_SET_JOB_PARENT_ID');

        $table->setPrimaryKey(['id']);

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->exec($sql);
        }

        $this->updateComposer('atrocore/core', '^1.10.7');
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
