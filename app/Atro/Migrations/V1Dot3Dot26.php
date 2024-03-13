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

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot3Dot26 extends Base
{
    public function up(): void
    {
        try {
            $this->getPDO()->exec("ALTER TABLE `attachment` CHANGE `type` type VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function down(): void
    {
    }
}
