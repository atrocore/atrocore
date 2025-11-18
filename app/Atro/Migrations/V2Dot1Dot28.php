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

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Atro\Core\Utils\Metadata;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\EntityManager;

class V2Dot1Dot28 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-11-18 18:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE matched_record ADD manually_added BOOLEAN DEFAULT 'true' NOT NULL");
            $this->exec("ALTER TABLE matched_record ADD golden_record BOOLEAN DEFAULT 'false' NOT NULL");
            $this->exec("ALTER TABLE matched_record ADD golden_record_hash VARCHAR(255) DEFAULT NULL");
            $this->exec("CREATE UNIQUE INDEX UNIQ_A88D469E6FB4A73AEB3B4E33 ON matched_record (golden_record_hash, deleted)");
            $this->exec("ALTER TABLE matched_record ALTER status SET DEFAULT 'new'");
            $this->exec("ALTER TABLE matched_record ALTER status SET NOT NULL");
        } else {
            $this->exec("ALTER TABLE matched_record ADD manually_added TINYINT(1) DEFAULT '1' NOT NULL");
            $this->exec("ALTER TABLE matched_record ADD golden_record TINYINT(1) DEFAULT '0' NOT NULL");
            $this->exec("ALTER TABLE matched_record ADD golden_record_hash VARCHAR(255) DEFAULT NULL");
            $this->exec("CREATE UNIQUE INDEX UNIQ_A88D469E6FB4A73AEB3B4E33 ON matched_record (golden_record_hash, deleted)");
            $this->exec("ALTER TABLE matched_record CHANGE status status VARCHAR(255) DEFAULT 'new' NOT NULL");
        }

        $this->getConnection()->createQueryBuilder()
            ->update('matched_record')
            ->set('status', ':new')
            ->where('status = :found')
            ->setParameter('new', 'new')
            ->setParameter('found', 'found')
            ->executeQuery();
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
