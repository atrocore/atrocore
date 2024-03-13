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

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot6Dot55 extends Base
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
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
