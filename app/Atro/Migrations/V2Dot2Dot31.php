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

class V2Dot2Dot31 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-03-30 12:00:00');
    }

    public function up(): void
    {
        if ($this->isPgsql()) {
            $this->exec("CREATE TABLE team_language (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', edit_action BOOLEAN DEFAULT 'false' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, team_id VARCHAR(36) DEFAULT NULL, language_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id));");
            $this->exec("CREATE UNIQUE INDEX IDX_TEAM_LANGUAGE_UNIQUE ON team_language (deleted, language_id, team_id);");
            $this->exec("CREATE INDEX IDX_TEAM_LANGUAGE_TEAM_ID ON team_language (team_id, deleted);");
            $this->exec("DROP TABLE role_language;");
            $this->exec("ALTER TABLE team ADD language_restricted BOOLEAN DEFAULT 'false' NOT NULL");
        } else {
            $this->exec("CREATE TABLE team_language (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', edit_action TINYINT(1) DEFAULT '0' NOT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, team_id VARCHAR(36) DEFAULT NULL, language_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_TEAM_LANGUAGE_UNIQUE (deleted, language_id, team_id), INDEX IDX_TEAM_LANGUAGE_TEAM_ID (team_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;");
            $this->exec("DROP TABLE role_language;");
            $this->exec("ALTER TABLE team ADD language_restricted TINYINT(1) DEFAULT '0' NOT NULL;");
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
