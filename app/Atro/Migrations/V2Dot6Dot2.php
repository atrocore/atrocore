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

class V2Dot6Dot2 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-10-16 12:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE data_pipeline RENAME COLUMN merging_script TO transformation_script");
        } else {
            $this->exec("ALTER TABLE data_pipeline CHANGE merging_script transformation_script LONGTEXT DEFAULT NULL");
        }

        $this->exec("ALTER TABLE data_pipeline ADD execute_transformation_as VARCHAR(255) DEFAULT 'system'");
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
