<?php
/**
* AtroCore Software
*
* This source file is available under GNU General Public License version 3 (GPLv3).
* Full copyright and license information is available in LICENSE.txt, located in the root directory.
*
*  @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
*  @license    GPLv3 (https://www.gnu.org/licenses/)
*/

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Exceptions\Error;
use Atro\Core\Migration\Base;

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
