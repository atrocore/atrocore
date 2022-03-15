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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Treo\Migrations;

use Treo\Core\Migration\Base;

class V1Dot3Dot37 extends Base
{
    public function up(): void
    {
        try {
            $this->getPDO()->exec("ALTER TABLE `attachment` ADD private TINYINT(1) DEFAULT '1' NOT NULL COLLATE utf8mb4_unicode_ci");
        } catch (\Throwable $e) {
        }

        try {
            $records = $this
                ->getPDO()
                ->query("SELECT * FROM `asset` WHERE deleted=0")
                ->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($records as $record) {
                $isPrivate = !empty($record['private']);
                $this->getPDO()->exec("UPDATE `attachment` SET private=$isPrivate WHERE id='{$record['file_id']}'");
            }
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
    }
}
