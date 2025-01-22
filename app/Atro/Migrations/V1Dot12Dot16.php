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

class V1Dot12Dot16 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-01-22 11:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE action_history_record ALTER action TYPE VARCHAR(8)");
            $this->exec("ALTER TABLE action_history_record ALTER ip_address TYPE VARCHAR(42)");
            $this->exec("ALTER TABLE action_history_record ALTER target_id TYPE VARCHAR(62)");
            $this->exec("ALTER TABLE action_history_record ALTER target_type TYPE VARCHAR(30)");
        } else {
            $this->exec("ALTER TABLE action_history_record CHANGE action action VARCHAR(8) DEFAULT NULL");
            $this->exec("ALTER TABLE action_history_record CHANGE ip_address ip_address VARCHAR(42) DEFAULT NULL");
            $this->exec("ALTER TABLE action_history_record CHANGE target_id target_id VARCHAR(62) DEFAULT NULL");
            $this->exec("ALTER TABLE action_history_record CHANGE target_type target_type VARCHAR(30) DEFAULT NULL");
        }
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
