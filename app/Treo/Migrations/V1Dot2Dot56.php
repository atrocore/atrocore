<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

declare(strict_types=1);

namespace Treo\Migrations;

use Espo\Core\Utils\Util;
use Treo\Core\Migration\Base;

/**
 * Migration class for version 1.2.56
 */
class V1Dot2Dot56 extends Base
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $multilangFields = "";
        $measureUnitMultilangFields = "";
        if ($this->getConfig()->get('isMultilangActive', false)) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                $locale = strtolower($locale);

                $multilangFields .= ", `name_" . $locale . "` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci";
                $multilangFields .= ", `description_" . $locale . "` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci";
                $multilangFields .= ", `html_" . $locale . "` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci";

                $measureUnitMultilangFields .= ", `name_" . $locale . "` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci";
            }
        }
        $this->execute("CREATE TABLE `info_page` (`id` VARCHAR(24) NOT NULL COLLATE utf8mb4_unicode_ci, `name` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, `description` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `html` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `css` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci {$multilangFields}, INDEX `IDX_NAME` (name, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");

        $this->execute("CREATE TABLE `measure` (`id` VARCHAR(24) NOT NULL COLLATE utf8mb4_unicode_ci, `name` VARCHAR(255) DEFAULT NULL UNIQUE COLLATE utf8mb4_unicode_ci $measureUnitMultilangFields, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, `description` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `created_at` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci, `modified_at` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci, `created_by_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `modified_by_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, INDEX `IDX_CREATED_BY_ID` (created_by_id), INDEX `IDX_MODIFIED_BY_ID` (modified_by_id), UNIQUE INDEX `UNIQ_800719255E237E06` (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->execute("CREATE TABLE `unit` (`id` VARCHAR(24) NOT NULL COLLATE utf8mb4_unicode_ci, `name` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci $measureUnitMultilangFields, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, `description` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `measure` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci, `created_at` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci, `modified_at` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci, `created_by_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `modified_by_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `measure_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, INDEX `IDX_CREATED_BY_ID` (created_by_id), INDEX `IDX_MODIFIED_BY_ID` (modified_by_id), INDEX `IDX_MEASURE_ID` (measure_id), INDEX `IDX_NAME` (name, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");

        $configData = include "data/config.php";
        if (!empty($configData['unitsOfMeasure'])) {
            foreach ($configData['unitsOfMeasure'] as $name => $records) {
                $id = Util::generateId();
                $this->execute("INSERT INTO `measure` (id, name) VALUES ('$id', '$name')");
                if (!empty($records->unitList)) {
                    foreach ($records->unitList as $item) {
                        $this->execute("INSERT INTO `unit` (id, name, measure_id) VALUES ('" . Util::generateId() . "', '$item', '$id')");
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
        $this->execute("DROP TABLE `info_page`");
        $this->execute("DROP TABLE `measure`");
        $this->execute("DROP TABLE `unit`");
    }

    /**
     * @param string $sql
     */
    protected function execute(string $sql)
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
            // ignore all
        }
    }
}
