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

class V2Dot1Dot16 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-09-25 17:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE extensible_enum ADD owner_user_id VARCHAR(36) DEFAULT NULL");
            $this->exec("ALTER TABLE extensible_enum ADD assigned_user_id VARCHAR(36) DEFAULT NULL");
            $this->exec("CREATE INDEX IDX_EXTENSIBLE_ENUM_OWNER_USER_ID ON extensible_enum (owner_user_id, deleted)");
            $this->exec("CREATE INDEX IDX_EXTENSIBLE_ENUM_ASSIGNED_USER_ID ON extensible_enum (assigned_user_id, deleted)");
            $this->exec("ALTER TABLE extensible_enum_option ADD owner_user_id VARCHAR(36) DEFAULT NULL");
            $this->exec("ALTER TABLE extensible_enum_option ADD assigned_user_id VARCHAR(36) DEFAULT NULL");
            $this->exec("CREATE INDEX IDX_EXTENSIBLE_ENUM_OPTION_OWNER_USER_ID ON extensible_enum_option (owner_user_id, deleted)");
            $this->exec("CREATE INDEX IDX_EXTENSIBLE_ENUM_OPTION_ASSIGNED_USER_ID ON extensible_enum_option (assigned_user_id, deleted)");
        } else {
            $this->exec("ALTER TABLE extensible_enum ADD owner_user_id VARCHAR(36) DEFAULT NULL");
            $this->exec("ALTER TABLE extensible_enum ADD assigned_user_id VARCHAR(36) DEFAULT NULL");
            $this->exec("CREATE INDEX IDX_EXTENSIBLE_ENUM_OWNER_USER_ID ON extensible_enum (owner_user_id, deleted)");
            $this->exec("CREATE INDEX IDX_EXTENSIBLE_ENUM_ASSIGNED_USER_ID ON extensible_enum (assigned_user_id, deleted)");
            $this->exec("ALTER TABLE extensible_enum_option ADD owner_user_id VARCHAR(36) DEFAULT NULL");
            $this->exec("ALTER TABLE extensible_enum_option ADD assigned_user_id VARCHAR(36) DEFAULT NULL");
            $this->exec("CREATE INDEX IDX_EXTENSIBLE_ENUM_OPTION_OWNER_USER_ID ON extensible_enum_option (owner_user_id, deleted)");
            $this->exec("CREATE INDEX IDX_EXTENSIBLE_ENUM_OPTION_ASSIGNED_USER_ID ON extensible_enum_option (assigned_user_id, deleted)");
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
