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

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot4Dot126 extends Base
{
    public function up(): void
    {
        $this->execute("CREATE TABLE sharing (id VARCHAR(24) NOT NULL COLLATE `utf8mb4_unicode_ci`, `name` VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, deleted TINYINT(1) DEFAULT '0' COLLATE `utf8mb4_unicode_ci`, active TINYINT(1) DEFAULT '0' NOT NULL COLLATE `utf8mb4_unicode_ci`, entity_type VARCHAR(255) DEFAULT 'Asset' COLLATE `utf8mb4_unicode_ci`, entity_id VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, type VARCHAR(255) DEFAULT 'download' COLLATE `utf8mb4_unicode_ci`, valid_till DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, allowed_usage INT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, modified_at DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_by_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, modified_by_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_CREATED_BY_ID (created_by_id), INDEX IDX_MODIFIED_BY_ID (modified_by_id), INDEX IDX_NAME (name, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
        $this->execute("ALTER TABLE sharing ADD `description` LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->execute("ALTER TABLE sharing CHANGE `active` `active` TINYINT(1) DEFAULT '1' NOT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->execute("ALTER TABLE sharing ADD used INT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
    }

    public function down(): void
    {
        $this->execute("DROP TABLE `sharing`");
    }

    protected function execute(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
