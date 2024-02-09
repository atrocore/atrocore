<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;

class V1Dot8Dot32 extends Base
{
    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("CREATE TABLE ui_handler (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted BOOLEAN DEFAULT 'false', type VARCHAR(255) DEFAULT NULL, entity_type VARCHAR(255) DEFAULT NULL, fields TEXT DEFAULT NULL, conditions_type VARCHAR(255) DEFAULT NULL, conditions TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, is_active BOOLEAN DEFAULT 'false' NOT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE INDEX IDX_UI_HANDLER_CREATED_BY_ID ON ui_handler (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_UI_HANDLER_MODIFIED_BY_ID ON ui_handler (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_UI_HANDLER_CREATED_AT ON ui_handler (created_at, deleted)");
            $this->exec("COMMENT ON COLUMN ui_handler.fields IS '(DC2Type:jsonArray)'");
            $this->exec("ALTER TABLE ui_handler ADD relationships TEXT DEFAULT NULL");
            $this->exec("COMMENT ON COLUMN ui_handler.relationships IS '(DC2Type:jsonArray)'");
        } else {
            $this->exec("CREATE TABLE ui_handler (id VARCHAR(24) NOT NULL, name VARCHAR(255) DEFAULT NULL, deleted TINYINT(1) DEFAULT '0', type VARCHAR(255) DEFAULT NULL, entity_type VARCHAR(255) DEFAULT NULL, fields LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonArray)', conditions_type VARCHAR(255) DEFAULT NULL, conditions LONGTEXT DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, is_active TINYINT(1) DEFAULT '0' NOT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, INDEX IDX_UI_HANDLER_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_UI_HANDLER_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_UI_HANDLER_CREATED_AT (created_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
            $this->exec("ALTER TABLE ui_handler ADD relationships LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonArray)'");
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
        }
    }
}
