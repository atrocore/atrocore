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

class V3Dot0Dot11 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-11-23 12:00:00');
    }

    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        if ($toSchema->hasTable('address')) {
            $table = $toSchema->getTable('address');

            foreach (['state', 'region', 'province'] as $column) {
                if (!$table->hasColumn($column)) {
                    $table->addColumn($column, 'string', ['length' => 255, 'notnull' => false]);
                }
            }
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getDbal()->executeStatement($sql);
        }
    }
}
