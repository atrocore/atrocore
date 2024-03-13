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

class V1Dot3Dot37 extends Base
{
    public function up(): void
    {
        try {
            $this->getPDO()->exec("ALTER TABLE `attachment` ADD private TINYINT(1) DEFAULT '1' NOT NULL COLLATE utf8mb4_unicode_ci");
        } catch (\Throwable $e) {
        }

        try {
            $records = $this
                ->getPDO()
                ->query("SELECT * FROM `asset` WHERE deleted=0")
                ->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($records as $record) {
                $isPrivate = !empty($record['private']);
                $this->getPDO()->exec("UPDATE `attachment` SET private=$isPrivate WHERE id='{$record['file_id']}'");
            }
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
    }
}
