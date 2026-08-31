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

class V2Dot3Dot17 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-08-25 12:00:00');
    }

    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        if ($toSchema->hasTable('scheduled_job')) {
            $table = $toSchema->getTable('scheduled_job');

            if (!$table->hasColumn('action_id')) {
                $table->addColumn('action_id', 'string', ['length' => 36, 'notnull' => false]);
                $table->addIndex(['action_id', 'deleted'], 'IDX_SCHEDULED_JOB_ACTION_ID');

                foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
                    $this->exec($sql);
                }
            }
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
