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

class V2Dot2Dot1 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-01-10 17:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("CREATE TABLE cluster (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', name VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, owner_user_id VARCHAR(36) DEFAULT NULL, assigned_user_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE UNIQUE INDEX UNIQ_E5C5699477153098EB3B4E33 ON cluster (name, deleted)");
            $this->exec("CREATE INDEX IDX_CLUSTER_CREATED_BY_ID ON cluster (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_CLUSTER_MODIFIED_BY_ID ON cluster (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_CLUSTER_OWNER_USER_ID ON cluster (owner_user_id, deleted)");
            $this->exec("CREATE INDEX IDX_CLUSTER_ASSIGNED_USER_ID ON cluster (assigned_user_id, deleted)");

            $this->exec("CREATE TABLE cluster_item (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', entity_name VARCHAR(255) DEFAULT NULL, entity_id VARCHAR(255) DEFAULT NULL, cluster_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE INDEX IDX_CLUSTER_ITEM_CLUSTER_ID ON cluster_item (cluster_id, deleted)");
            $this->exec("CREATE INDEX IDX_CLUSTER_ITEM_ENTITY_NAME ON cluster_item (entity_name, deleted)");
            $this->exec("CREATE INDEX IDX_CLUSTER_ITEM_ENTITY_ID ON cluster_item (entity_id, deleted)");

            $this->exec("CREATE TABLE user_followed_cluster (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, cluster_id VARCHAR(36) DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE UNIQUE INDEX IDX_USER_FOLLOWED_CLUSTER_UNIQUE_RELATION ON user_followed_cluster (deleted, cluster_id, user_id)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_CLUSTER_CREATED_BY_ID ON user_followed_cluster (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_CLUSTER_MODIFIED_BY_ID ON user_followed_cluster (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_CLUSTER_CLUSTER_ID ON user_followed_cluster (cluster_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_CLUSTER_USER_ID ON user_followed_cluster (user_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_CLUSTER_CREATED_AT ON user_followed_cluster (created_at, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_CLUSTER_MODIFIED_AT ON user_followed_cluster (modified_at, deleted)");
        } else {
            $this->exec("CREATE TABLE cluster (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', name VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, owner_user_id VARCHAR(36) DEFAULT NULL, assigned_user_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX UNIQ_E5C5699477153098EB3B4E33 (name, deleted), INDEX IDX_CLUSTER_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_CLUSTER_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_CLUSTER_OWNER_USER_ID (owner_user_id, deleted), INDEX IDX_CLUSTER_ASSIGNED_USER_ID (assigned_user_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
            $this->exec("CREATE TABLE cluster_item (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', entity_name VARCHAR(255) DEFAULT NULL, entity_id VARCHAR(255) DEFAULT NULL, cluster_id VARCHAR(36) DEFAULT NULL, INDEX IDX_CLUSTER_ITEM_CLUSTER_ID (cluster_id, deleted), INDEX IDX_CLUSTER_ITEM_ENTITY_NAME (entity_name, deleted), INDEX IDX_CLUSTER_ITEM_ENTITY_ID (entity_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
            $this->exec("CREATE TABLE user_followed_cluster (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, cluster_id VARCHAR(36) DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_USER_FOLLOWED_CLUSTER_UNIQUE_RELATION (deleted, cluster_id, user_id), INDEX IDX_USER_FOLLOWED_CLUSTER_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_USER_FOLLOWED_CLUSTER_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_USER_FOLLOWED_CLUSTER_CLUSTER_ID (cluster_id, deleted), INDEX IDX_USER_FOLLOWED_CLUSTER_USER_ID (user_id, deleted), INDEX IDX_USER_FOLLOWED_CLUSTER_CREATED_AT (created_at, deleted), INDEX IDX_USER_FOLLOWED_CLUSTER_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
        }

        $this->exec("CREATE UNIQUE INDEX IDX_CLUSTER_ITEM_UNIQUE ON cluster_item (deleted, entity_name, entity_id, cluster_id)");
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
