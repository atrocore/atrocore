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

class V1Dot4Dot131 extends Base
{
    public function up(): void
    {
        $this->execute("ALTER TABLE user ADD type VARCHAR(255) DEFAULT 'Token' COLLATE `utf8mb4_unicode_ci`");
        $this->execute("UPDATE user SET type='Token' WHERE 1");
    }

    public function down(): void
    {
        $this->execute("ALTER TABLE user DROP type");
    }

    protected function execute(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
