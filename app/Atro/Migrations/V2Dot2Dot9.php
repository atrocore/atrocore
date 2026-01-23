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

class V2Dot2Dot9 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-01-23 12:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("CREATE TABLE rejected_cluster_item (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', cluster_item_id VARCHAR(36) DEFAULT NULL, cluster_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id));");
            $this->exec("CREATE UNIQUE INDEX IDX_REJECTED_CLUSTER_ITEM_UNIQUE_RELATION ON rejected_cluster_item (deleted, cluster_item_id, cluster_id);");
            $this->exec("CREATE INDEX IDX_REJECTED_CLUSTER_ITEM_CLUSTER_ITEM_ID ON rejected_cluster_item (cluster_item_id, deleted);");
            $this->exec("CREATE INDEX IDX_REJECTED_CLUSTER_ITEM_CLUSTER_ID ON rejected_cluster_item (cluster_id, deleted)");
        } else {
            $this->exec("CREATE TABLE rejected_cluster_item (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', cluster_item_id VARCHAR(36) DEFAULT NULL, cluster_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_REJECTED_CLUSTER_ITEM_UNIQUE_RELATION (deleted, cluster_item_id, cluster_id), INDEX IDX_REJECTED_CLUSTER_ITEM_CLUSTER_ITEM_ID (cluster_item_id, deleted), INDEX IDX_REJECTED_CLUSTER_ITEM_CLUSTER_ID (cluster_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;");
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
