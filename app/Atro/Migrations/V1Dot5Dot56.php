<?php
/**
* AtroCore Software
*
* This source file is available under GNU General Public License version 3 (GPLv3).
* Full copyright and license information is available in LICENSE.txt, located in the root directory.
*
*  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
*  @license    GPLv3 (https://www.gnu.org/licenses/)
*/

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot5Dot56 extends Base
{
    public function up(): void
    {
        $this->getPDO()->exec("DROP TABLE IF EXISTS extensible_enum;CREATE TABLE extensible_enum (id VARCHAR(24) NOT NULL COLLATE `utf8mb4_unicode_ci`, name VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, deleted TINYINT(1) DEFAULT '0' COLLATE `utf8mb4_unicode_ci`, description LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, modified_at DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_by_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, modified_by_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_CREATED_BY_ID (created_by_id), INDEX IDX_MODIFIED_BY_ID (modified_by_id), INDEX IDX_NAME (name, deleted), INDEX IDX_CREATED_AT (created_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
        $this->getPDO()->exec("DROP TABLE IF EXISTS extensible_enum_option;CREATE TABLE extensible_enum_option (id VARCHAR(24) NOT NULL COLLATE `utf8mb4_unicode_ci`, name LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, deleted TINYINT(1) DEFAULT '0' COLLATE `utf8mb4_unicode_ci`, sort_order INT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, modified_at DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, extensible_enum_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_by_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, modified_by_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_EXTENSIBLE_ENUM_ID (extensible_enum_id), INDEX IDX_CREATED_BY_ID (created_by_id), INDEX IDX_MODIFIED_BY_ID (modified_by_id), INDEX IDX_CREATED_AT (created_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");

        if ($this->getConfig()->get('isMultilangActive', false)) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
                $this->getPDO()->exec("ALTER TABLE extensible_enum_option ADD name_" . strtolower($language) . " LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
            }
        }

        $this->getPDO()->exec("ALTER TABLE extensible_enum_option ADD color VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
    }

    public function down(): void
    {
        $this->getPDO()->exec("DROP TABLE IF EXISTS extensible_enum");
        $this->getPDO()->exec("DROP TABLE IF EXISTS extensible_enum_option");
    }
}
