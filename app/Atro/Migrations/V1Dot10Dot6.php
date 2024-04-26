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

namespace Atro\Migrations;

use Atro\Core\Exceptions\Error;
use Atro\Core\Migration\Base;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\Util;

class V1Dot10Dot6 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-04-26 00:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE scheduled_job ADD storage_id VARCHAR(24) DEFAULT NULL");
            $this->exec("CREATE INDEX IDX_SCHEDULED_JOB_STORAGE_ID ON scheduled_job (storage_id, deleted)");
        } else {
            $this->exec("ALTER TABLE scheduled_job ADD storage_id VARCHAR(24) DEFAULT NULL");
            $this->exec("CREATE INDEX IDX_SCHEDULED_JOB_STORAGE_ID ON scheduled_job (storage_id, deleted)");
        }
    }

    public function down(): void
    {
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
