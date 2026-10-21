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

class V2Dot6Dot3 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-10-21 12:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE product ADD gtin VARCHAR(255) DEFAULT NULL");

        // the column already exists where the PIM module is installed
        $this->exec("ALTER TABLE product ADD ean VARCHAR(255) DEFAULT NULL");
        $this->exec("CREATE INDEX IDX_PRODUCT_EAN ON product (ean, deleted)");
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
