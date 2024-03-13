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

class V1Dot3Dot35 extends Base
{
    public function up(): void
    {
        $this->getPDO()->exec("DROP TABLE IF EXISTS `connection`; CREATE TABLE `connection` (`id` VARCHAR(24) NOT NULL COLLATE utf8mb4_unicode_ci, `name` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, `type` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `host` VARCHAR(255) DEFAULT 'localhost' COLLATE utf8mb4_unicode_ci, `db_name` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `port` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `user` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `password` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `created_at` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci, `modified_at` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci, `created_by_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `modified_by_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, INDEX `IDX_CREATED_BY_ID` (created_by_id), INDEX `IDX_MODIFIED_BY_ID` (modified_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
    }

    public function down(): void
    {
        $this->getPDO()->exec("DROP TABLE IF EXISTS `connection`");
    }
}
