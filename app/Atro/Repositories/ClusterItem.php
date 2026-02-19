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
use Atro\Core\Exceptions\Error;
use Atro\Core\Templates\Repositories\Base;
use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\IEntity;

class ClusterItem extends Base
{
    public function getStagingRecords(\Atro\Entities\ClusterItem $clusterItem): EntityCollection
    {
        $masterEntityName = $this->getMetadata()->get("scopes.{$clusterItem->get('entityName')}.primaryEntityId");

        // if cluster item is staging record, return it
        if (!empty($masterEntityName)) {
            $stagingRecord = $this->getEntityManager()->getRepository($clusterItem->get('entityName'))->get($clusterItem->get('entityId'));
            return new EntityCollection([$stagingRecord], $clusterItem->get('entityName'));
        } else {
            // otherwise find all stagings for master record
            foreach ($this->getMetadata()->get('scopes') ?? [] as $scope => $scopeDefs) {
                if (($scopeDefs['primaryEntityId'] ?? null) === $clusterItem->get('entityName')) {
                    return $this->getEntityManager()->getRepository($scope)->where(['masterRecordId' => $clusterItem->get('entityId')])->find();
                }
            }
        }
        throw new Error('No stagings found for cluster item');
    }

    public function moveAllToCluster(string $clusterIdFrom, string $clusterIdTo): void
    {
        // check if 'From' cluster has no rejected items that are in 'To' cluster
        $res = $this->getDbal()->createQueryBuilder()
            ->select('rci.id')
            ->from('rejected_cluster_item', 'rci')
            ->join('rci', 'cluster_item', 'ci', 'ci.id=rci.cluster_item_id')
            ->where('rci.cluster_id=:clusterIdFrom and ci.cluster_id= :clusterIdTo and rci.deleted = :false and ci.deleted = :false')
            ->setParameter('clusterIdFrom', $clusterIdFrom)
            ->setParameter('clusterIdTo', $clusterIdTo)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchOne();

        if (!empty($res)) {
            return;
        }

        $this->getDbal()->createQueryBuilder()
            ->update('cluster_item')
            ->set('cluster_id', ':clusterIdTo')
            ->where('cluster_id=:clusterIdFrom and id not in (select cluster_item_id from rejected_cluster_item where cluster_id=:clusterIdTo and deleted=:false) and deleted=:false')
            ->setParameter('clusterIdFrom', $clusterIdFrom)
            ->setParameter('clusterIdTo', $clusterIdTo)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->executeQuery();

        $this->updateMatchedScoresInClusters([$clusterIdFrom, $clusterIdTo]);
    }

    public function moveToCluster(string $clusterItemId, string $clusterIdTo): void
    {
        $this->getDbal()->createQueryBuilder()
            ->update('cluster_item')
            ->set('cluster_id', ':clusterIdTo')
            ->where('id=:clusterItemId')
            ->setParameter('clusterItemId', $clusterItemId)
            ->setParameter('clusterIdTo', $clusterIdTo)
            ->executeQuery();
    }

    public function updateMatchedScore(string $entityName, string $entityId, int $score): void
    {
        $this->getDbal()->createQueryBuilder()
            ->update('cluster_item')
            ->set('matched_score', ':score')
            ->where('entity_name=:entityName and entity_id=:entityId')
            ->andWhere('matched_score is null or matched_score < :score')
            ->setParameter('score', $score)
            ->setParameter('entityName', $entityName)
            ->setParameter('entityId', $entityId)
            ->executeStatement();
    }

    public function updateMatchedScoresInClusters(array $clusterIds): void
    {
        $this->getDbal()->createQueryBuilder()
            ->update('cluster_item', 'ci')
            ->set(
                'matched_score',
                '(SELECT MAX(mr.score) 
              FROM matched_record mr 
              WHERE (
                  (mr.source_entity = ci.entity_name AND mr.source_entity_id = ci.entity_id) 
                  OR 
                  (mr.master_entity = ci.entity_name AND mr.master_entity_id = ci.entity_id)
              )
              AND ci.cluster_id = (SELECT MAX(ci2.cluster_id) FROM cluster_item ci2 WHERE ci2.entity_name = mr.source_entity AND ci2.entity_id = mr.source_entity_id)
              AND ci.cluster_id = (SELECT MAX(ci3.cluster_id) FROM cluster_item ci3 WHERE ci3.entity_name = mr.master_entity AND ci3.entity_id = mr.master_entity_id)
              AND mr.deleted = :false 
              AND mr.has_cluster = :true
            )'
            )
            ->where('ci.cluster_id in (:clusterIds) AND ci.deleted = :false')
            ->setParameter('clusterIds', $clusterIds, Mapper::getParameterType($clusterIds))
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->executeStatement();
    }

    public function getRecordsWithNoClusterItems(string $stagingEntityName, int $limit = PHP_INT_MAX): array
    {
        return $this->getDbal()->createQueryBuilder()
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

    public function getSingleClusterItemsToConfirmAutomatically($stagingEntityName, $offset = 0, $limit = 2000): EntityCollection
    {
        return $this->limit($offset, $limit)->find([
            'callbacks' => [function (QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper) use ($stagingEntityName) {
                $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();
                $stagingTableName = $mapper->toDb($stagingEntityName);

                $qb->andWhere("$tableAlias.entity_name = :stagingEntityName and $tableAlias.matched_score is null")
                    ->andWhere("(select count(id) from cluster_item ci where ci.cluster_id=$tableAlias.cluster_id and deleted=:false) = 1")
                    ->andWhere("(select count(id) from $stagingTableName st where st.id=$tableAlias.entity_id and st.master_record_id is null and st.deleted=:false) = 1")
                    ->setParameter('stagingEntityName', $stagingEntityName);
            }]
        ]);
    }

    public function getClustersToConfirmAutomatically(string $stagingEntityName, int $offset = 0, int $limit = PHP_INT_MAX): array
    {
        $masterDataEntity = $this->getEntityManager()->getEntity('MasterDataEntity', $stagingEntityName);

        if (empty($masterDataEntity) || empty($masterDataEntity->get('confirmAutomatically'))) {
            return [];
        }

        $minimumScore = $masterDataEntity->get('minimumMatchingScore');
        $stagingTableName = Util::toUnderScore(lcfirst($stagingEntityName));

        $qb = $this->getDbal()->createQueryBuilder()
            ->select('ci.cluster_id');

        if (Converter::isPgSQL($this->getDbal())) {
            $qb->addSelect("string_agg(ci.id::text, ',') AS cluster_item_ids");
        } else {
            $qb->addSelect("GROUP_CONCAT(ci.id SEPARATOR ',') AS cluster_item_ids");
        }

        $qb->from('cluster_item', 'ci')
            ->innerJoin('ci', 'cluster', 'c', 'c.id=ci.cluster_id and c.deleted=:false')
            ->innerJoin('ci', $stagingTableName, 'se', 'se.id=ci.entity_id and se.deleted=:false')
            ->where('ci.entity_name=:stagingEntityName and ci.matched_score>=:minimumScore and se.master_record_id is null and ci.deleted=:false')
            ->setParameter('stagingEntityName', $stagingEntityName)
            ->setParameter('minimumScore', $minimumScore)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->orderBy('ci.cluster_id', 'DESC')
            ->groupBy('ci.cluster_id');


        return $qb->fetchAllAssociative();
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
