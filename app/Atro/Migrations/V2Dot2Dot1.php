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

class V2Dot2Dot1 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-12-30 18:00:00');
    }

    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        if ($toSchema->hasTable('attribute')) {
            $table = $toSchema->getTable('attribute');

            if (!$table->hasColumn('modified_extended_disabled')) {
                $table->addColumn('modified_extended_disabled', 'boolean', ['default' => false, 'notnull' => true]);

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