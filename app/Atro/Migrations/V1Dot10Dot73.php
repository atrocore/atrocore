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

class V1Dot10Dot73 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-09-19 09:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE preferences ALTER id TYPE VARCHAR(36)");
        $this->exec("ALTER TABLE user_followed_record ALTER entity_id TYPE VARCHAR(36)");
        $this->exec("ALTER TABLE user_followed_record ALTER user_id TYPE VARCHAR(36)");
        $this->exec("ALTER TABLE entity_team ALTER entity_id TYPE VARCHAR(36)");

        if ($this->isPgSQL()) {
            $res = $this->getConnection()->createQueryBuilder()
                ->select('table_name, column_name')
                ->from('information_schema.columns')
                ->where("data_type='character varying' AND character_maximum_length=24")
                ->fetchAllAssociative();
        } else {
            $dbName = $this->getConfig()->get('database')['dbname'];
            $res = $this->getConnection()->createQueryBuilder()
                ->select('table_name, column_name')
                ->from('nformation_schema.columns')
                ->where("data_type='varchar' AND character_maximum_length=24 AND table_schema='$dbName'")
                ->fetchAllAssociative();
        }

        foreach ($res as $row) {
            $this->exec("ALTER TABLE " . $this->getConnection()->quoteIdentifier($row['table_name']) . " ALTER {$row['column_name']} TYPE VARCHAR(36)");
        }

        $this->updateComposer('atrocore/core', '^1.10.72');
    }

    public function down(): void
    {
        throw new \Error("Downgrade is prohibited.");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
