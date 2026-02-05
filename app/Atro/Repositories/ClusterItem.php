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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Base;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

class ClusterItem extends Base
{
    public function moveAllToCluster(string $clusterIdFrom, string $clusterIdTo): void
    {
        // check if 'From' cluster has no rejected items that are in 'To' cluster
        $res = $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from('rejected_cluster_item', 'rci')
            ->join('rci', 'cluster_item', 'ci', 'ci.id=rejected_cluster_item.cluster_item_id')
            ->where('rci.cluster_id=:clusterIdFrom and ci.cluster_id= :clusterIdTo and rci.deleted = :false and ci.deleted = :false')
            ->andWhere('')
            ->setParameter('clusterIdFrom', $clusterIdFrom)
            ->setParameter('clusterIdTo', $clusterIdTo)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchOne();

        if (!empty($res)) {
            return;
        }

        $this->getConnection()->createQueryBuilder()
            ->update('cluster_item')
            ->set('cluster_id', ':clusterIdTo')
            ->where('cluster_id=:clusterIdFrom and id not in (select cluster_item_id from rejected_cluster_item where cluster_id=:clusterIdTo) and deleted=:false')
            ->setParameter('clusterIdFrom', $clusterIdFrom)
            ->setParameter('clusterIdTo', $clusterIdTo)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->executeQuery();
    }

    public function moveToCluster(string $clusterItemId, string $clusterIdTo): void
    {
        $this->getConnection()->createQueryBuilder()
            ->update('cluster_item')
            ->set('cluster_id', ':clusterIdTo')
            ->where('id=:clusterItemId')
            ->setParameter('clusterItemId', $clusterItemId)
            ->setParameter('clusterIdTo', $clusterIdTo)
            ->executeQuery();
    }

    public function getRecordsWithNoClusterItems(string $stagingEntityName, int $limit = PHP_INT_MAX): array
    {
        return $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from(Util::toUnderScore(lcfirst($stagingEntityName)), 'se')
            ->where('se.deleted = :false')
            ->andWhere('se.id not in (select entity_id from cluster_item where entity_name=:stagingEntityType and deleted=:false)')
            ->setParameter('stagingEntityType', $stagingEntityName)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setFirstResult(0)
            ->setMaxResults($limit)
            ->addOrderBy('se.id', 'ASC')
            ->fetchFirstColumn();
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        if (!empty($entity->get('matchedRecordId'))) {
            $this->getEntityManager()->getRepository('MatchedRecord')->markHasNoCluster($entity->get('matchedRecordId'));
        }
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if ($entity->isNew() && $this->getMetadata()->get(['scopes', $entity->get('entityName'), 'role']) === 'changeRequest') {
            throw new BadRequest("Change request can't be assigned to cluster item.");
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
