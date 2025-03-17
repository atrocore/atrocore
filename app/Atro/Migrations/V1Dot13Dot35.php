<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot13Dot35 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-03-17 15:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE \"user\" ADD disable_action_history BOOLEAN DEFAULT 'false' NOT NULL");
            $this->exec("ALTER TABLE action_history_record ADD controller_name VARCHAR(255) DEFAULT NULL");
        } else {
            $this->exec("ALTER TABLE `user` ADD disable_action_history TINYINT(1) DEFAULT '0' NOT NULL");
            $this->exec("ALTER TABLE action_history_record ADD controller_name VARCHAR(255) DEFAULT NULL");
        }

        $this->exec("truncate action_history_record");
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
