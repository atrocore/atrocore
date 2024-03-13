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

class V1Dot6Dot1 extends Base
{
    public function up(): void
    {
        $this->getPDO()->exec("ALTER TABLE extensible_enum ADD multilingual TINYINT(1) DEFAULT '1' NOT NULL COLLATE `utf8mb4_unicode_ci`");
    }

    public function down(): void
    {
        $this->getPDO()->exec("ALTER TABLE extensible_enum DROP multilingual");
    }
}
