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

class V1Dot13Dot54 extends Base
{
    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE \"user\" ADD additional_languages TEXT DEFAULT NULL;");
            $this->exec("COMMENT ON COLUMN \"user\".additional_languages IS '(DC2Type:jsonArray)'");
        } else {
            $this->exec("ALTER TABLE user ADD additional_languages LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonArray)'");
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
