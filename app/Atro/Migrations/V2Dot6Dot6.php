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

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V2Dot6Dot6 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-10-23 12:00:00');
    }

    public function up(): void
    {
        // the vat_id column already exists where the Accounting module is installed
        $this->exec("ALTER TABLE account ADD vat_id VARCHAR(255) DEFAULT NULL");
        $this->exec("ALTER TABLE account ADD web_url VARCHAR(255) DEFAULT NULL");

        // brand_file created by the PIM module has no unique relation index
        $this->exec("CREATE UNIQUE INDEX IDX_BRAND_FILE_UNIQUE_RELATION ON brand_file (deleted, brand_id, file_id)");
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
