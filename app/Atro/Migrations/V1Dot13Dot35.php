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
        $this->getConfig()->set('actionHistory', true);
        $this->getConfig()->save();

        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE \"user\" ADD action_history BOOLEAN DEFAULT 'true' NOT NULL");
        } else {
            $this->exec("ALTER TABLE `user` ADD sync_folders TINYINT(1) DEFAULT '1' NOT NULL");
        }
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
