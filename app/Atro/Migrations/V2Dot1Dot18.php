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
use Atro\Core\Utils\Metadata;
use Atro\Core\Utils\Util;

class V2Dot1Dot18 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-09-30 08:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE attribute ADD conditional_required TEXT DEFAULT NULL;");
            $this->exec("ALTER TABLE attribute ADD conditional_read_only TEXT DEFAULT NULL;");
            $this->exec("ALTER TABLE attribute ADD conditional_protected TEXT DEFAULT NULL;");
            $this->exec("ALTER TABLE attribute ADD conditional_visible TEXT DEFAULT NULL;");
            $this->exec("ALTER TABLE attribute ADD conditional_disable_options TEXT DEFAULT NULL;");
            $this->exec("COMMENT ON COLUMN attribute.conditional_required IS '(DC2Type:jsonObject)';");
            $this->exec("COMMENT ON COLUMN attribute.conditional_read_only IS '(DC2Type:jsonObject)';");
            $this->exec("COMMENT ON COLUMN attribute.conditional_protected IS '(DC2Type:jsonObject)';");
            $this->exec("COMMENT ON COLUMN attribute.conditional_visible IS '(DC2Type:jsonObject)';");
            $this->exec("COMMENT ON COLUMN attribute.conditional_disable_options IS '(DC2Type:jsonObject)'");
        }else{
            $this->exec("ALTER TABLE attribute ADD is_protected TINYINT(1) DEFAULT '0' NOT NULL, ADD conditional_required LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)', ADD conditional_read_only LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)', ADD conditional_protected LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)', ADD conditional_visible LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)', ADD conditional_disable_options LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)';");
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
