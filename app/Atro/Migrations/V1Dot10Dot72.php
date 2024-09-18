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

use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\Util;

class V1Dot10Dot72 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-09-18 11:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE layout_profile ADD is_default BOOLEAN DEFAULT 'false' NOT NULL;");
        } else {
            $this->exec("ALTER TABLE layout_profile ADD is_default TINYINT(1) DEFAULT '0' NOT NULL;");
        }

        try {
            $this->getConnection()->createQueryBuilder()
                ->update('layout_profile')
                ->set('is_default', ':true')
                ->where('id = :id')
                ->setParameter('id', 'default')
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->executeStatement();
        } catch (\Throwable $exception) {
        }
    }

    public function down(): void
    {
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
