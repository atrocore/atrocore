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

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;

class V2Dot2Dot8 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-01-21 14:00:00');
    }

    public function up(): void
    {

        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE \"user\" ADD current_selection_id VARCHAR(36) DEFAULT NULL");
            $this->exec("CREATE INDEX IDX_USER_CURRENT_SELECTION_ID ON \"user\" (current_selection_id, deleted)");
        } else {
            $this->exec("ALTER TABLE user ADD current_selection_id VARCHAR(36) DEFAULT NULL");
            $this->exec("CREATE INDEX IDX_USER_CURRENT_SELECTION_ID ON user (current_selection_id, deleted)");
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
