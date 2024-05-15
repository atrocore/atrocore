<?php
/**
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

class V1Dot10Dot15 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-05-16 17:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE ui_handler ADD trigger_action TEXT DEFAULT NULL");
            $this->exec("ALTER TABLE ui_handler ADD trigger_fields TEXT DEFAULT NULL");
            $this->exec("COMMENT ON COLUMN ui_handler.trigger_action IS '(DC2Type:jsonArray)'");
            $this->exec("COMMENT ON COLUMN ui_handler.trigger_fields IS '(DC2Type:jsonArray)'");
        } else {
            $this->exec("ALTER TABLE ui_handler ADD trigger_action LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonArray)'");
            $this->exec("ALTER TABLE ui_handler ADD trigger_fields LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonArray)'");
        }
    }

    public function down(): void
    {
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
