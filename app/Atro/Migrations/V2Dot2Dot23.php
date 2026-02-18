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
use Atro\Core\Utils\Util;
use Doctrine\DBAL\ParameterType;

class V2Dot2Dot23 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-02-19 10:00:00');
    }

    public function up(): void
    {
        $entityNames = $this->getDbal()->createQueryBuilder()
            ->select('entity_name')
            ->distinct()
            ->from('cluster_item')
            ->fetchFirstColumn();

        foreach ($entityNames as $entityName) {
            $tableName = $this->getDbal()->quoteIdentifier(Util::toUnderScore(lcfirst($entityName)));

            $this->getDbal()->createQueryBuilder()
                ->delete('cluster_item', 'ci')
                ->where("ci.entity_name=:entityName AND NOT EXISTS (SELECT 1 FROM $tableName e WHERE e.id=ci.entity_id and deleted=:false)")
                ->setParameter('entityName', $entityName)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->executeQuery();
        }
    }
}
