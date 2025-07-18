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

class V2Dot0Dot16 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-07-18 13:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE action ADD connection_id VARCHAR(36) DEFAULT NULL");
            $this->exec("ALTER TABLE action ADD target_entity VARCHAR(255) DEFAULT NULL");
            $this->exec("CREATE INDEX IDX_ACTION_CONNECTION_ID ON action (connection_id, deleted)");
        } else {
            $this->exec("ALTER TABLE action ADD target_entity VARCHAR(255) DEFAULT NULL");
            $this->exec("ALTER TABLE action ADD connection_id VARCHAR(36) DEFAULT NULL");
            $this->exec("CREATE INDEX IDX_ACTION_CONNECTION_ID ON action (connection_id, deleted)");
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
