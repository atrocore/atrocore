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
        return new \DateTime('2026-01-24 12:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("CREATE TABLE rejected_cluster_item (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, cluster_id VARCHAR(36) DEFAULT NULL, cluster_item_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id));");
            $this->exec("CREATE UNIQUE INDEX IDX_REJECTED_CLUSTER_ITEM_UNIQUE_RELATION ON rejected_cluster_item (deleted, cluster_id, cluster_item_id);");
            $this->exec("CREATE INDEX IDX_REJECTED_CLUSTER_ITEM_CREATED_BY_ID ON rejected_cluster_item (created_by_id, deleted);");
            $this->exec("CREATE INDEX IDX_REJECTED_CLUSTER_ITEM_MODIFIED_BY_ID ON rejected_cluster_item (modified_by_id, deleted);");
            $this->exec("CREATE INDEX IDX_REJECTED_CLUSTER_ITEM_CLUSTER_ID ON rejected_cluster_item (cluster_id, deleted);");
            $this->exec("CREATE INDEX IDX_REJECTED_CLUSTER_ITEM_CLUSTER_ITEM_ID ON rejected_cluster_item (cluster_item_id, deleted);");
            $this->exec("CREATE INDEX IDX_REJECTED_CLUSTER_ITEM_CREATED_AT ON rejected_cluster_item (created_at, deleted);");
            $this->exec("CREATE INDEX IDX_REJECTED_CLUSTER_ITEM_MODIFIED_AT ON rejected_cluster_item (modified_at, deleted);");

            $this->exec("ALTER TABLE cluster_item ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL;");
            $this->exec("ALTER TABLE cluster_item ADD modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL;");
            $this->exec("ALTER TABLE cluster_item ADD created_by_id VARCHAR(36) DEFAULT NULL;");
            $this->exec("ALTER TABLE cluster_item ADD modified_by_id VARCHAR(36) DEFAULT NULL;");
            $this->exec("CREATE INDEX IDX_CLUSTER_ITEM_CREATED_BY_ID ON cluster_item (created_by_id, deleted);");
            $this->exec("CREATE INDEX IDX_CLUSTER_ITEM_MODIFIED_BY_ID ON cluster_item (modified_by_id, deleted);");
            $this->exec("CREATE INDEX IDX_CLUSTER_ITEM_CREATED_AT ON cluster_item (created_at, deleted);");
            $this->exec("CREATE INDEX IDX_CLUSTER_ITEM_MODIFIED_AT ON cluster_item (modified_at, deleted)");
            $this->exec("ALTER TABLE cluster_item ALTER cluster_id SET NOT NULL;");
        } else {
            $this->exec("CREATE TABLE rejected_cluster_item (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, cluster_id VARCHAR(36) DEFAULT NULL, cluster_item_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_REJECTED_CLUSTER_ITEM_UNIQUE_RELATION (deleted, cluster_id, cluster_item_id), INDEX IDX_REJECTED_CLUSTER_ITEM_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_REJECTED_CLUSTER_ITEM_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_REJECTED_CLUSTER_ITEM_CLUSTER_ID (cluster_id, deleted), INDEX IDX_REJECTED_CLUSTER_ITEM_CLUSTER_ITEM_ID (cluster_item_id, deleted), INDEX IDX_REJECTED_CLUSTER_ITEM_CREATED_AT (created_at, deleted), INDEX IDX_REJECTED_CLUSTER_ITEM_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;");
            $this->exec("ALTER TABLE cluster_item ADD created_at DATETIME DEFAULT NULL, ADD modified_at DATETIME DEFAULT NULL, ADD created_by_id VARCHAR(36) DEFAULT NULL, ADD modified_by_id VARCHAR(36) DEFAULT NULL, CHANGE cluster_id cluster_id VARCHAR(36) NOT NULL;");
            $this->exec("CREATE INDEX IDX_CLUSTER_ITEM_CREATED_BY_ID ON cluster_item (created_by_id, deleted);");
            $this->exec("CREATE INDEX IDX_CLUSTER_ITEM_MODIFIED_BY_ID ON cluster_item (modified_by_id, deleted);");
            $this->exec("CREATE INDEX IDX_CLUSTER_ITEM_CREATED_AT ON cluster_item (created_at, deleted);");
            $this->exec("CREATE INDEX IDX_CLUSTER_ITEM_MODIFIED_AT ON cluster_item (modified_at, deleted);");
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
