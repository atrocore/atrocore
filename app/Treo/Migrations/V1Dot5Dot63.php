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

class V1Dot5Dot63 extends Base
{
    public function up(): void
    {
        $this->getPDO()->exec(
            "ALTER TABLE extensible_enum_option DROP code;ALTER TABLE extensible_enum_option ADD code VARCHAR(255) DEFAULT NULL UNIQUE COLLATE `utf8mb4_unicode_ci`"
        );
        $this->exec("CREATE UNIQUE INDEX UNIQ_6598AC4577153098EB3B4E33 ON extensible_enum_option (code, deleted)");
        $this->exec("DROP INDEX code ON extensible_enum_option");

        $this->getPDO()->exec("ALTER TABLE extensible_enum DROP code;ALTER TABLE extensible_enum ADD code VARCHAR(255) DEFAULT NULL UNIQUE COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE UNIQUE INDEX UNIQ_49A4DA4577153098EB3B4E33 ON extensible_enum (code, deleted)");
        $this->exec("DROP INDEX code ON extensible_enum");

        $this->exec("DROP INDEX UNIQ_8007192533E7211DEB3B4E33 ON measure");
        $this->exec("DROP INDEX UNIQ_800719255E237E06EB3B4E33 ON measure");
        $this->getPDO()->exec("ALTER TABLE measure ADD code VARCHAR(255) DEFAULT NULL UNIQUE COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE UNIQUE INDEX UNIQ_8007192577153098EB3B4E33 ON measure (code, deleted)");
        $this->getPDO()->exec("UPDATE measure SET code=name WHERE 1");
        $this->exec("DROP INDEX code ON measure");

        $this->getPDO()->exec("ALTER TABLE unit ADD code VARCHAR(255) DEFAULT NULL UNIQUE COLLATE `utf8mb4_unicode_ci`");
        $this->getPDO()->exec("CREATE UNIQUE INDEX UNIQ_DCBB0C5377153098EB3B4E33 ON unit (code, deleted)");
        $this->exec("DROP INDEX code ON unit");
    }

    public function down(): void
    {
        throw new \Error('Downgrade is prohibited!');
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
