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
use Doctrine\DBAL\ParameterType;

class V2Dot2Dot7 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-01-21 14:00:00');
    }

    public function up(): void
    {
        $this->exec("TRUNCATE TABLE cluster");
        $this->exec("TRUNCATE TABLE cluster_item");

        $this->exec("DROP INDEX IDX_CLUSTER_ITEM_UNIQUE ON cluster_item");
        $this->exec("CREATE UNIQUE INDEX IDX_CLUSTER_ITEM_UNIQUE ON cluster_item (deleted, entity_name, entity_id)");

        $this->getConnection()->createQueryBuilder()
            ->update('matched_record')
            ->set('has_cluster', ':false')
            ->where('has_cluster = :true')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('true', true, ParameterType::BOOLEAN)
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
