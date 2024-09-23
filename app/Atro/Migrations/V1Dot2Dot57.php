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

use Atro\Core\Utils\Util;

/**
 * Migration class for version 1.2.57
 */
class V1Dot2Dot57 extends V1Dot2Dot56
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $fields = "";
        if ($this->getConfig()->get('isMultilangActive', false)) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                $fields .= ", `name_" . strtolower($locale) . "` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci";
            }
        }

        $this->execute("DROP TABLE `measure`");
        $this->execute("CREATE TABLE `measure` (`id` VARCHAR(24) NOT NULL COLLATE utf8mb4_unicode_ci, `name` VARCHAR(255) DEFAULT NULL UNIQUE COLLATE utf8mb4_unicode_ci $fields, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, `description` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `created_at` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci, `modified_at` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci, `created_by_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `modified_by_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, INDEX `IDX_CREATED_BY_ID` (created_by_id), INDEX `IDX_MODIFIED_BY_ID` (modified_by_id), UNIQUE INDEX `UNIQ_800719255E237E06` (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->execute("DROP TABLE `unit`");
        $this->execute("CREATE TABLE `unit` (`id` VARCHAR(24) NOT NULL COLLATE utf8mb4_unicode_ci, `name` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci $fields, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, `description` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `measure` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci, `created_at` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci, `modified_at` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci, `created_by_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `modified_by_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `measure_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, INDEX `IDX_CREATED_BY_ID` (created_by_id), INDEX `IDX_MODIFIED_BY_ID` (modified_by_id), INDEX `IDX_MEASURE_ID` (measure_id), INDEX `IDX_NAME` (name, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");

        $configData = include "data/config.php";
        if (!empty($configData['unitsOfMeasure'])) {
            foreach ($configData['unitsOfMeasure'] as $name => $records) {
                $id = Util::generateUniqueHash();
                $this->execute("INSERT INTO `measure` (id, name) VALUES ('$id', '$name')");
                if (!empty($records->unitList)) {
                    foreach ($records->unitList as $item) {
                        $this->execute("INSERT INTO `unit` (id, name, measure_id) VALUES ('" . Util::generateUniqueHash() . "', '$item', '$id')");
                    }
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        $this->execute("DROP TABLE `measure`");
        $this->execute("DROP TABLE `unit`");
    }
}
