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

namespace Atro\Repositories;

use Atro\Core\Templates\Repositories\Base;
use Atro\Core\Utils\Util;
use Espo\ORM\Entity;

class ClusterItem extends Base
{
    public function moveToCluster(string $clusterIdFrom, string $clusterIdTo): void
    {
        $this->getConnection()->createQueryBuilder()
            ->update('cluster_item')
            ->set('cluster_id', ':clusterIdTo')
            ->where('cluster_id=:clusterIdFrom')
            ->setParameter('clusterIdFrom', $clusterIdFrom)
            ->setParameter('clusterIdTo', $clusterIdTo)
            ->executeQuery();
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        if (!empty($entity->get('matchedRecordId'))) {
            $this->getEntityManager()->getRepository('MatchedRecord')->markHasNoCluster($entity->get('matchedRecordId'));
        }
    }

    public function hasDeletedRecordsToClear(): bool
    {
        return true;
    }

    public function clearDeletedRecords(): void
    {
        parent::clearDeletedRecords();

        $records = $this->getConnection()->createQueryBuilder()
            ->select('entity_name')
            ->distinct()
            ->from('cluster_item')
            ->fetchAllAssociative();

        foreach ($records as $record) {
            $entityName = $record['entity_name'];
            $tableName = $this->getConnection()->quoteIdentifier(Util::toUnderScore(lcfirst($entityName)));

            $this->getConnection()->createQueryBuilder()
                ->delete('cluster_item', 'ci')
                ->where("ci.entity_name=:entityName AND NOT EXISTS (SELECT 1 FROM $tableName e WHERE e.id=ci.entity_id)")
                ->setParameter('entityName', $entityName)
                ->executeQuery();
        }
    }
}
