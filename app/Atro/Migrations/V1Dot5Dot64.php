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

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot5Dot64 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE extensible_enum_option DROP code");
        $this->getPDO()->exec("ALTER TABLE extensible_enum_option ADD code VARCHAR(255) DEFAULT NULL UNIQUE COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE UNIQUE INDEX UNIQ_6598AC4577153098EB3B4E33 ON extensible_enum_option (code, deleted)");
        $this->exec("DROP INDEX code ON extensible_enum_option");

        $this->exec("ALTER TABLE extensible_enum DROP code");
        $this->getPDO()->exec("ALTER TABLE extensible_enum ADD code VARCHAR(255) DEFAULT NULL UNIQUE COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE UNIQUE INDEX UNIQ_49A4DA4577153098EB3B4E33 ON extensible_enum (code, deleted)");
        $this->exec("DROP INDEX code ON extensible_enum");

        $this->rebuildByCronJob();
    }

    public function down(): void
    {
        throw new \Error('Downgrade is prohibited!');
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
