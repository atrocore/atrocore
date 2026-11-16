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

class V3Dot0Dot5 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-11-11 18:00:00');
    }

    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        if (!$toSchema->hasTable('connection_type_oracle')) {
            $table = $toSchema->createTable('connection_type_oracle');

            $table->addColumn('id', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('supertype_id', 'string', ['length' => 36, 'notnull' => false]);
            $table->addColumn('host', 'string', ['length' => 255, 'notnull' => false, 'default' => 'localhost']);
            $table->addColumn('db_name', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('port', 'string', ['length' => 255, 'notnull' => false, 'default' => '1521']);
            $table->addColumn('user', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('password', 'string', ['length' => 255, 'notnull' => false]);

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['supertype_id']);

            $foreignKeyName = 'FK_' . strtoupper('connection_type_oracle_supertype_id');
            $table->addForeignKeyConstraint('connection', ['supertype_id'], ['id'], ['onDelete' => 'CASCADE'], $foreignKeyName);
            $table->addIndex(['supertype_id'], $foreignKeyName);

            foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
                $this->getDbal()->executeStatement($sql);
            }
        }
    }
}
