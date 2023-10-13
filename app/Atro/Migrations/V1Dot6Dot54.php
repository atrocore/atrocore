<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot6Dot54 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE scheduled_job ADD minimum_age INT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`;");
        $this->exec(
            "INSERT INTO scheduled_job (id, `name`, job, minimum_age, `status`, scheduling) VALUES ('DeleteForever','Delete data forever','DeleteForever',90,'Active','0 0 1 * *')"
        );
        $this->exec("DELETE FROM scheduled_job WHERE id = 'TreoCleanup'");

//      Delete all phantom entities in hierarchy relationship tables
        $tables = $this->getPDO()->query('show tables')->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tables as $hierarchyTable) {
            if (!str_contains($hierarchyTable,'_hierarchy')) {
                continue;
            }

            $table = str_replace("_hierarchy","", $hierarchyTable);
            if( !in_array($table, $tables)){
                continue;
            }

            $columns = $this->getPDO()->query("SHOW COLUMNS FROM $hierarchyTable")->fetchAll(\PDO::FETCH_COLUMN);
            if (!in_array('entity_id', $columns) && !in_array('parent_id', $columns)) {
                continue ;
            }
            $this->exec(
                "DELETE FROM $hierarchyTable 
                            WHERE NOT EXISTS(SELECT p.id FROM $table  p WHERE p.id = entity_id)
                            OR NOT EXISTS(SELECT p.id FROM $table  p WHERE p.id = parent_id);
            ");
        }

        $this->updateComposer('atrocore/core', '^1.6.54');
    }

    public function down(): void
    {
        $this->exec("ALTER TABLE scheduled_job drop column minimum_age;");
        $this->exec("DELETE FROM scheduled_job WHERE id = 'DeleteForever'");
        $this->exec("INSERT INTO scheduled_job (id, `name`, job, `status`, scheduling) 
                VALUES ('TreoCleanup','Old deleted data cleanup','TreoCleanup','Active','0 0 1 * *')");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
            var_dump($e);
        }
    }
}
