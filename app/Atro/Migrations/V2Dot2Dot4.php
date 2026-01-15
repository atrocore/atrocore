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
use Atro\Core\Utils\Util;

class V2Dot2Dot4 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-01-13 17:00:00');
    }

    public function up(): void
    {
        foreach ($this->getConfig()->get('matchings') ?? [] as $id => $active) {
            $matchingData = $this->getConnection()->createQueryBuilder()
                ->select('*')
                ->from('matching')
                ->where('id = :id')
                ->setParameter('id', $id)
                ->fetchAssociative();
            if (!empty($matchingData['type'])) {
                if ($matchingData['type'] === 'masterRecord') {
                    $columnName = Util::toUnderScore("matching{$matchingData['entity']}" . ucfirst(strtolower("S2M")));
                } else {
                    $columnName = Util::toUnderScore("matching{$matchingData['entity']}" . ucfirst(strtolower("D2D")));
                }
                $tableName = Util::toUnderScore(lcfirst($matchingData['entity']));

                $this->exec("ALTER TABLE $tableName DROP COLUMN $columnName");

                if ($this->isPgSQL()) {
                    $this->exec("ALTER TABLE $tableName ADD $columnName TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL");
                } else {
                    $this->exec("ALTER TABLE $tableName ADD $columnName DATETIME DEFAULT NULL");
                }
            }
        }

        $this->exec("TRUNCATE matched_record");

        $this->exec("ALTER TABLE cluster_item ADD matched_record_id VARCHAR(36) DEFAULT NULL");
        $this->exec("CREATE INDEX IDX_CLUSTER_ITEM_MATCHED_RECORD_ID ON cluster_item (matched_record_id, deleted)");

        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE matched_record ADD has_cluster BOOLEAN DEFAULT 'false' NOT NULL");
        } else {
            $this->exec("ALTER TABLE matched_record ADD has_cluster TINYINT(1) DEFAULT '0' NOT NULL");
        }
        $this->exec("CREATE INDEX IDX_MATCHED_RECORD_HAS_CLUSTER ON matched_record (has_cluster)");
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
