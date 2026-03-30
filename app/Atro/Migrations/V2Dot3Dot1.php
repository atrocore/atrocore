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

class V2Dot3Dot1 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-03-30 12:00:00');
    }

    public function up(): void
    {
        $this->exec("DROP TABLE IF EXISTS {$this->quote('role_language')}");

        if ($this->isPgsql()) {
            $this->exec("ALTER TABLE {$this->quote('team')} ADD COLUMN {$this->quote('language_restricted')} BOOLEAN DEFAULT FALSE NOT NULL");
            $this->exec("
                CREATE TABLE {$this->quote('team_language')} (
                    {$this->quote('id')} VARCHAR(24) NOT NULL,
                    {$this->quote('deleted')} BOOLEAN DEFAULT FALSE,
                    {$this->quote('team_id')} VARCHAR(24) DEFAULT NULL,
                    {$this->quote('language_id')} VARCHAR(24) DEFAULT NULL,
                    {$this->quote('edit_action')} BOOLEAN DEFAULT FALSE NOT NULL,
                    {$this->quote('created_at')} TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                    {$this->quote('modified_at')} TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                    {$this->quote('created_by_id')} VARCHAR(24) DEFAULT NULL,
                    {$this->quote('modified_by_id')} VARCHAR(24) DEFAULT NULL,
                    PRIMARY KEY ({$this->quote('id')})
                )
            ");
            $this->exec("CREATE UNIQUE INDEX {$this->quote('UNIQ_team_language')} ON {$this->quote('team_language')} ({$this->quote('deleted')}, {$this->quote('language_id')}, {$this->quote('team_id')})");
            $this->exec("CREATE INDEX {$this->quote('IDX_team_language_team_id')} ON {$this->quote('team_language')} ({$this->quote('team_id')})");
        } else {
            $this->exec("ALTER TABLE {$this->quote('team')} ADD COLUMN {$this->quote('language_restricted')} TINYINT(1) DEFAULT 0 NOT NULL");
            $this->exec("
                CREATE TABLE {$this->quote('team_language')} (
                    {$this->quote('id')} VARCHAR(24) NOT NULL,
                    {$this->quote('deleted')} TINYINT(1) DEFAULT 0,
                    {$this->quote('team_id')} VARCHAR(24) DEFAULT NULL,
                    {$this->quote('language_id')} VARCHAR(24) DEFAULT NULL,
                    {$this->quote('edit_action')} TINYINT(1) DEFAULT 0 NOT NULL,
                    {$this->quote('created_at')} DATETIME DEFAULT NULL,
                    {$this->quote('modified_at')} DATETIME DEFAULT NULL,
                    {$this->quote('created_by_id')} VARCHAR(24) DEFAULT NULL,
                    {$this->quote('modified_by_id')} VARCHAR(24) DEFAULT NULL,
                    PRIMARY KEY ({$this->quote('id')})
                ) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB
            ");
            $this->exec("CREATE UNIQUE INDEX {$this->quote('UNIQ_team_language')} ON {$this->quote('team_language')} ({$this->quote('deleted')}, {$this->quote('language_id')}, {$this->quote('team_id')})");
            $this->exec("CREATE INDEX {$this->quote('IDX_team_language_team_id')} ON {$this->quote('team_language')} ({$this->quote('team_id')})");
        }
    }

    public function down(): void
    {
        $this->exec("DROP TABLE IF EXISTS {$this->quote('team_language')}");
        $this->exec("ALTER TABLE {$this->quote('team')} DROP COLUMN {$this->quote('language_restricted')}");
    }
}
