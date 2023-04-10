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

namespace Treo\Migrations;

use Treo\Core\Migration\Base;

class V1Dot6Dot0 extends Base
{
    public function up(): void
    {
        $this->getPDO()->exec("DROP TABLE IF EXISTS drop_down_list;CREATE TABLE drop_down_list (id VARCHAR(24) NOT NULL COLLATE `utf8mb4_unicode_ci`, name VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, deleted TINYINT(1) DEFAULT '0' COLLATE `utf8mb4_unicode_ci`, description LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, modified_at DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_by_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, modified_by_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_CREATED_BY_ID (created_by_id), INDEX IDX_MODIFIED_BY_ID (modified_by_id), INDEX IDX_NAME (name, deleted), INDEX IDX_CREATED_AT (created_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
        $this->getPDO()->exec("DROP TABLE IF EXISTS drop_down_list_option;CREATE TABLE drop_down_list_option (id VARCHAR(24) NOT NULL COLLATE `utf8mb4_unicode_ci`, name LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, deleted TINYINT(1) DEFAULT '0' COLLATE `utf8mb4_unicode_ci`, sort_order INT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, modified_at DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, drop_down_list_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_by_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, modified_by_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_DROP_DOWN_LIST_ID (drop_down_list_id), INDEX IDX_CREATED_BY_ID (created_by_id), INDEX IDX_MODIFIED_BY_ID (modified_by_id), INDEX IDX_NAME (name(500), deleted), INDEX IDX_CREATED_AT (created_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");

        if ($this->getConfig()->get('isMultilangActive', false)) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
                $this->getPDO()->exec("ALTER TABLE drop_down_list_option ADD name_" . strtolower($language) . " LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
            }
        }
    }

    public function down(): void
    {
        $this->getPDO()->exec("DROP TABLE IF EXISTS drop_down_list");
        $this->getPDO()->exec("DROP TABLE IF EXISTS drop_down_list_option");
    }
}
