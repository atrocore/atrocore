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

class V1Dot8Dot8 extends Base
{
    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("CREATE TABLE action (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', is_active BOOLEAN DEFAULT 'false' NOT NULL, type VARCHAR(255) DEFAULT NULL, data TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE INDEX IDX_ACTION_CREATED_BY_ID ON action (created_by_id)");
            $this->exec("CREATE INDEX IDX_ACTION_CREATED_BY_ID_DELETED ON action (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_ACTION_MODIFIED_BY_ID ON action (modified_by_id)");
            $this->exec("CREATE INDEX IDX_ACTION_MODIFIED_BY_ID_DELETED ON action (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_ACTION_CREATED_AT ON action (created_at, deleted)");
            $this->exec("COMMENT ON COLUMN action.data IS '(DC2Type:jsonObject)'");
        }else{
            $this->exec("CREATE TABLE action (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', is_active TINYINT(1) DEFAULT '0' NOT NULL, type VARCHAR(255) DEFAULT NULL, data LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, INDEX IDX_ACTION_CREATED_BY_ID (created_by_id), INDEX IDX_ACTION_CREATED_BY_ID_DELETED (created_by_id, deleted), INDEX IDX_ACTION_MODIFIED_BY_ID (modified_by_id), INDEX IDX_ACTION_MODIFIED_BY_ID_DELETED (modified_by_id, deleted), INDEX IDX_ACTION_CREATED_AT (created_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
        }

        $this->exec("ALTER TABLE action ADD source_entity VARCHAR(255) DEFAULT NULL");
    }

    public function down(): void
    {
        $this->exec("DROP TABLE action");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
