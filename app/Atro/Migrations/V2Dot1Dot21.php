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

class V2Dot1Dot21 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-10-21 08:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("CREATE TABLE selection (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', description TEXT DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE TABLE selection_record (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', entity_type VARCHAR(255) DEFAULT NULL, entity_id VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE TABLE selection_selection_record (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, selection_id VARCHAR(36) DEFAULT NULL, selection_record_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE UNIQUE INDEX IDX_SELECTION_SELECTION_RECORD_UNIQUE_RELATION ON selection_selection_record (deleted, selection_id, selection_record_id)");
            $this->exec("CREATE INDEX IDX_SELECTION_SELECTION_RECORD_CREATED_BY_ID ON selection_selection_record (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_SELECTION_SELECTION_RECORD_MODIFIED_BY_ID ON selection_selection_record (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_SELECTION_SELECTION_RECORD_SELECTION_ID ON selection_selection_record (selection_id, deleted)");
            $this->exec("CREATE INDEX IDX_SELECTION_SELECTION_RECORD_SELECTION_RECORD_ID ON selection_selection_record (selection_record_id, deleted)");
            $this->exec("CREATE INDEX IDX_SELECTION_SELECTION_RECORD_CREATED_AT ON selection_selection_record (created_at, deleted)");
            $this->exec("CREATE INDEX IDX_SELECTION_SELECTION_RECORD_MODIFIED_AT ON selection_selection_record (modified_at, deleted)");
            $this->exec("CREATE TABLE user_followed_selection (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, selection_id VARCHAR(36) DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE UNIQUE INDEX IDX_USER_FOLLOWED_SELECTION_UNIQUE_RELATION ON user_followed_selection (deleted, selection_id, user_id)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_SELECTION_CREATED_BY_ID ON user_followed_selection (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_SELECTION_MODIFIED_BY_ID ON user_followed_selection (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_SELECTION_SELECTION_ID ON user_followed_selection (selection_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_SELECTION_USER_ID ON user_followed_selection (user_id, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_SELECTION_CREATED_AT ON user_followed_selection (created_at, deleted)");
            $this->exec("CREATE INDEX IDX_USER_FOLLOWED_SELECTION_MODIFIED_AT ON user_followed_selection (modified_at, deleted)");
        } else {
            $this->exec("CREATE TABLE selection (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', description LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;");
            $this->exec("CREATE TABLE selection_record (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', entity_type VARCHAR(255) DEFAULT NULL, entity_id VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
            $this->exec("CREATE TABLE selection_selection_record (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, selection_id VARCHAR(36) DEFAULT NULL, selection_record_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_SELECTION_SELECTION_RECORD_UNIQUE_RELATION (deleted, selection_id, selection_record_id), INDEX IDX_SELECTION_SELECTION_RECORD_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_SELECTION_SELECTION_RECORD_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_SELECTION_SELECTION_RECORD_SELECTION_ID (selection_id, deleted), INDEX IDX_SELECTION_SELECTION_RECORD_SELECTION_RECORD_ID (selection_record_id, deleted), INDEX IDX_SELECTION_SELECTION_RECORD_CREATED_AT (created_at, deleted), INDEX IDX_SELECTION_SELECTION_RECORD_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
            $this->exec("CREATE TABLE user_followed_selection (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, selection_id VARCHAR(36) DEFAULT NULL, user_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_USER_FOLLOWED_SELECTION_UNIQUE_RELATION (deleted, selection_id, user_id), INDEX IDX_USER_FOLLOWED_SELECTION_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_USER_FOLLOWED_SELECTION_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_USER_FOLLOWED_SELECTION_SELECTION_ID (selection_id, deleted), INDEX IDX_USER_FOLLOWED_SELECTION_USER_ID (user_id, deleted), INDEX IDX_USER_FOLLOWED_SELECTION_CREATED_AT (created_at, deleted), INDEX IDX_USER_FOLLOWED_SELECTION_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
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
