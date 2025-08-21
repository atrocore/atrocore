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

class V2Dot0Dot32 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-08-21 18:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("CREATE TABLE action_log (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status VARCHAR(255) DEFAULT NULL, status_message TEXT DEFAULT NULL, payload TEXT DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, action_id VARCHAR(36) DEFAULT NULL, incoming_webhook_id VARCHAR(36) DEFAULT NULL, scheduled_job_id VARCHAR(36) DEFAULT NULL, workflow_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE INDEX IDX_ACTION_LOG_CREATED_BY_ID ON action_log (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_ACTION_LOG_MODIFIED_BY_ID ON action_log (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_ACTION_LOG_ACTION_ID ON action_log (action_id, deleted)");
            $this->exec("CREATE INDEX IDX_ACTION_LOG_SCHEDULED_JOB_ID ON action_log (scheduled_job_id, deleted)");
            $this->exec("CREATE INDEX IDX_ACTION_LOG_WORKFLOW_ID ON action_log (workflow_id, deleted)");
            $this->exec("CREATE INDEX IDX_ACTION_LOG_NAME ON action_log (name, deleted)");
            $this->exec("CREATE INDEX IDX_ACTION_LOG_CREATED_AT ON action_log (created_at, deleted)");
            $this->exec("CREATE INDEX IDX_ACTION_LOG_MODIFIED_AT ON action_log (modified_at, deleted)");
            $this->exec("COMMENT ON COLUMN action_log.payload IS '(DC2Type:jsonObject)'");
        } else {
            $this->exec("CREATE TABLE action_log (id VARCHAR(36) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, status VARCHAR(255) DEFAULT NULL, status_message LONGTEXT DEFAULT NULL, payload LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)', type VARCHAR(255) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, action_id VARCHAR(36) DEFAULT NULL, incoming_webhook_id VARCHAR(36) DEFAULT NULL, scheduled_job_id VARCHAR(36) DEFAULT NULL, workflow_id VARCHAR(36) DEFAULT NULL, INDEX IDX_ACTION_LOG_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_ACTION_LOG_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_ACTION_LOG_ACTION_ID (action_id, deleted), INDEX IDX_ACTION_LOG_SCHEDULED_JOB_ID (scheduled_job_id, deleted), INDEX IDX_ACTION_LOG_WORKFLOW_ID (workflow_id, deleted), INDEX IDX_ACTION_LOG_NAME (name, deleted), INDEX IDX_ACTION_LOG_CREATED_AT (created_at, deleted), INDEX IDX_ACTION_LOG_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
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
