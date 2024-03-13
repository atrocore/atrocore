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

use Atro\Console\Cron;
use Atro\Core\Migration\Base;

class V1Dot4Dot1 extends Base
{
    public function up(): void
    {
        $this->execute("DELETE FROM `queue_item` WHERE 1");
        $this->execute("ALTER TABLE `queue_item` CHANGE `sort_order` sort_order INT AUTO_INCREMENT NOT NULL UNIQUE COLLATE utf8mb4_unicode_ci");
        $this->execute("CREATE UNIQUE INDEX UNIQ_BA4B6DE845AFA4EA ON `queue_item` (sort_order)");
        $this->execute("ALTER TABLE `queue_item` ADD priority VARCHAR(255) DEFAULT 'Normal' COLLATE utf8mb4_unicode_ci");
        file_put_contents(Cron::DAEMON_KILLER, '1');
    }

    public function down(): void
    {
        $this->execute("DROP INDEX sort_order ON `queue_item`");
        $this->execute("ALTER TABLE `queue_item` CHANGE `sort_order` sort_order INT DEFAULT '0' COLLATE utf8mb4_unicode_ci");
        $this->execute("ALTER TABLE `queue_item` DROP priority");
        file_put_contents(Cron::DAEMON_KILLER, '1');
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
