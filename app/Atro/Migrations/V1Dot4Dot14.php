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

class V1Dot4Dot14 extends Base
{
    public function up(): void
    {
        $this->execute("CREATE TABLE `pseudo_transaction_job` (`id` VARCHAR(24) NOT NULL COLLATE utf8mb4_unicode_ci, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, `sort_order` INT AUTO_INCREMENT NOT NULL UNIQUE COLLATE utf8mb4_unicode_ci, `entity_type` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `entity_id` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `action` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `input_data` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `created_by_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `parent_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, INDEX `IDX_CREATED_BY_ID` (created_by_id), INDEX `IDX_PARENT_ID` (parent_id), INDEX `IDX_ENTITY_TYPE` (entity_type, deleted), INDEX `IDX_ENTITY_ID` (entity_id, deleted), UNIQUE INDEX `UNIQ_433845D45AFA4EA` (sort_order), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
    }

    public function down(): void
    {
        $this->execute("DROP table `pseudo_transaction_job`");
    }

    protected function execute(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
            // ignore all
        }
    }
}
