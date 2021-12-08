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

use Treo\Core\Migration\Base;

class V1Dot3Dot40 extends Base
{
    public function up(): void
    {
        $this->execute(
            "CREATE TABLE `locale` (`id` VARCHAR(24) NOT NULL COLLATE utf8mb4_unicode_ci, `name` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, `description` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `created_at` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci, `modified_at` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci, `created_by_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `modified_by_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, INDEX `IDX_CREATED_BY_ID` (created_by_id), INDEX `IDX_MODIFIED_BY_ID` (modified_by_id), INDEX `IDX_NAME` (name, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB"
        );
        $this->execute("ALTER TABLE `locale` ADD language VARCHAR(255) DEFAULT 'en_US' COLLATE utf8mb4_unicode_ci");
        $this->execute("ALTER TABLE `locale` ADD date_format VARCHAR(255) DEFAULT 'MM/DD/YYYY' COLLATE utf8mb4_unicode_ci");
        $this->execute("ALTER TABLE `locale` ADD time_zone VARCHAR(255) DEFAULT 'UTC' COLLATE utf8mb4_unicode_ci");
        $this->execute("ALTER TABLE `locale` ADD week_start VARCHAR(255) DEFAULT '0' COLLATE utf8mb4_unicode_ci");
        $this->execute("ALTER TABLE `locale` ADD time_format VARCHAR(255) DEFAULT 'HH:mm' COLLATE utf8mb4_unicode_ci");
        $this->execute("ALTER TABLE `locale` ADD thousand_separator VARCHAR(1) DEFAULT ',' COLLATE utf8mb4_unicode_ci");
        $this->execute("ALTER TABLE `locale` ADD decimal_mark VARCHAR(1) DEFAULT '.' COLLATE utf8mb4_unicode_ci");

        $language = $this->getConfig()->get('language');
        $dateFormat = $this->getConfig()->get('dateFormat');
        $timeZone = $this->getConfig()->get('timeZone');
        $weekStart = $this->getConfig()->get('weekStart') == 1 ? 'monday' : 'sunday';
        $timeFormat = $this->getConfig()->get('timeFormat');
        $thousandSeparator = $this->getConfig()->get('thousandSeparator');
        $decimalMark = $this->getConfig()->get('decimalMark');

        $this->execute(
            "INSERT INTO `locale` (id, name, language, date_format, time_zone, week_start, time_format, thousand_separator, decimal_mark) VALUES ('1', 'Default Locale', '$language', '$dateFormat', '$timeZone', '$weekStart', '$timeFormat', '$thousandSeparator', '$decimalMark')"
        );

        $this->getConfig()->set('localeId', '1');
        $this->getConfig()->save();
    }

    public function down(): void
    {
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
