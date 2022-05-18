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

use Espo\Core\Exceptions\Error;
use Treo\Core\Migration\Base;

class V1Dot4Dot41 extends Base
{
    public function up(): void
    {
        $this->execute("ALTER TABLE `connection` ADD `data` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci");

        try {
            $connections = $this->getPDO()->query("SELECT * FROM `connection` WHERE deleted=0")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $connections = [];
        }

        foreach ($connections as $connection) {
            $data = [
                'db_name'  => $connection['db_name'],
                'host'     => $connection['host'],
                'password' => $connection['password'],
                'port'     => $connection['port'],
                'user'     => $connection['user'],
            ];
            $this->execute("UPDATE `connection` SET `data`='" . json_encode($data) . "' WHERE id='{$connection['id']}'");
        }

        $this->execute("ALTER TABLE `connection` DROP db_name");
        $this->execute("ALTER TABLE `connection` DROP `host`");
        $this->execute("ALTER TABLE `connection` DROP `password`");
        $this->execute("ALTER TABLE `connection` DROP `port`");
        $this->execute("ALTER TABLE `connection` DROP `user`");
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited!');
    }

    protected function execute(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
