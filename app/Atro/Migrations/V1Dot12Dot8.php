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
use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Core\Utils\Util;

class V1Dot12Dot8 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-12-26 14:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE action ADD conditions_type VARCHAR(255) DEFAULT NULL;");
            $this->exec("ALTER TABLE action ADD conditions TEXT DEFAULT NULL");
        } else {
            $this->exec("ALTER TABLE action ADD conditions_type VARCHAR(255) DEFAULT NULL, ADD conditions LONGTEXT DEFAULT NULL;");
        }
    }

    public function down(): void
    {
        $this->exec("ALTER TABLE action DROP conditions_type");
        $this->exec("ALTER TABLE action DROP conditions");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
