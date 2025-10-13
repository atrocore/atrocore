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

class V2Dot1Dot19 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-10-13 18:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("CREATE TABLE matched_record (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', staging_entity VARCHAR(255) DEFAULT NULL, staging_entity_id VARCHAR(255) DEFAULT NULL, master_entity VARCHAR(255) DEFAULT NULL, master_entity_id VARCHAR(255) DEFAULT NULL, score INT DEFAULT NULL, status VARCHAR(255) DEFAULT NULL, hash VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, matching_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE UNIQUE INDEX UNIQ_A88D469ED1B862B8EB3B4E33 ON matched_record (hash, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHED_RECORD_CREATED_BY_ID ON matched_record (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHED_RECORD_MODIFIED_BY_ID ON matched_record (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_MATCHED_RECORD_MATCHING_ID ON matched_record (matching_id)");
            $this->exec("CREATE INDEX IDX_MATCHED_RECORD_STAGING_ENTITY ON matched_record (matching_id, staging_entity, staging_entity_id)");
            $this->exec("CREATE INDEX IDX_MATCHED_RECORD_MASTER_ENTITY ON matched_record (matching_id, master_entity)");
        } else {
            $this->exec("CREATE TABLE matched_record (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', staging_entity VARCHAR(255) DEFAULT NULL, staging_entity_id VARCHAR(255) DEFAULT NULL, master_entity VARCHAR(255) DEFAULT NULL, master_entity_id VARCHAR(255) DEFAULT NULL, score INT DEFAULT NULL, status VARCHAR(255) DEFAULT NULL, hash VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, matching_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX UNIQ_A88D469ED1B862B8EB3B4E33 (hash, deleted), INDEX IDX_MATCHED_RECORD_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_MATCHED_RECORD_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_MATCHED_RECORD_MATCHING_ID (matching_id), INDEX IDX_MATCHED_RECORD_STAGING_ENTITY (matching_id, staging_entity, staging_entity_id), INDEX IDX_MATCHED_RECORD_MASTER_ENTITY (matching_id, master_entity), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
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
