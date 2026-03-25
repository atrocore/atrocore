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
                if (($scopeDefs['primaryEntityId'] ?? null) === $clusterItem->get('entityName') && $scopeDefs['role'] !== 'changeRequest') {
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

        // Query items BEFORE the UPDATE so we know what is being moved
        $items = $this->getDbal()->createQueryBuilder()
            ->select('ci.entity_name', 'ci.entity_id')
            ->from('cluster_item', 'ci')
            ->where('ci.cluster_id = :clusterIdFrom')
            ->andWhere('ci.deleted = :false')
            ->andWhere('ci.id NOT IN (SELECT rci2.cluster_item_id FROM rejected_cluster_item rci2 WHERE rci2.cluster_id = :clusterIdTo AND rci2.deleted = :false)')
            ->setParameter('clusterIdFrom', $clusterIdFrom)
            ->setParameter('clusterIdTo', $clusterIdTo)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        $this->getDbal()->createQueryBuilder()
            ->update('cluster_item')
            ->set('cluster_id', ':clusterIdTo')
            ->where('cluster_id=:clusterIdFrom and id not in (select cluster_item_id from rejected_cluster_item where cluster_id=:clusterIdTo and deleted=:false) and deleted=:false')
            ->setParameter('clusterIdFrom', $clusterIdFrom)
            ->setParameter('clusterIdTo', $clusterIdTo)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->executeQuery();

        if (!empty($items)) {
            $this->createMoveNotes($clusterIdFrom, $clusterIdTo, $items);
        }

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
        if (Converter::isPgSQL($this->getDbal())) {
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
        } else {
            // MySQL does not allow referencing the UPDATE target table in subqueries within the SET clause (error 1093).
            // Wrapping the logic in a derived table JOIN forces MySQL to materialise it first.
            $placeholders = implode(',', array_fill(0, count($clusterIds), '?'));
            $this->getDbal()->executeStatement(
                "UPDATE cluster_item ci
                INNER JOIN (
                    SELECT ci_inner.id, MAX(mr.score) AS max_score
                    FROM cluster_item ci_inner
                    LEFT JOIN matched_record mr
                        ON mr.deleted = 0
                        AND mr.has_cluster = 1
                        AND (
                            (mr.source_entity = ci_inner.entity_name AND mr.source_entity_id = ci_inner.entity_id)
                            OR
                            (mr.master_entity = ci_inner.entity_name AND mr.master_entity_id = ci_inner.entity_id)
                        )
                        AND ci_inner.cluster_id = (SELECT MAX(ci2.cluster_id) FROM cluster_item ci2 WHERE ci2.entity_name = mr.source_entity AND ci2.entity_id = mr.source_entity_id)
                        AND ci_inner.cluster_id = (SELECT MAX(ci3.cluster_id) FROM cluster_item ci3 WHERE ci3.entity_name = mr.master_entity AND ci3.entity_id = mr.master_entity_id)
                    WHERE ci_inner.cluster_id IN ($placeholders) AND ci_inner.deleted = 0
                    GROUP BY ci_inner.id
                ) AS scores ON scores.id = ci.id
                SET ci.matched_score = scores.max_score",
                $clusterIds
            );
        }
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
        $masterEntityName = $this->getMetadata()->get("scopes.$stagingEntityName.primaryEntityId");
        $masterTableName = Util::toUnderScore(lcfirst((string)$masterEntityName));

        return $this->limit($offset, $limit)->find([
            'callbacks' => [function (QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper) use ($stagingEntityName, $masterTableName) {
                $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();
                $stagingTableName = $mapper->toDb($stagingEntityName);

                $qb->andWhere("$tableAlias.entity_name = :stagingEntityName and $tableAlias.matched_score is null")
                    ->andWhere("(select count(id) from cluster_item ci where ci.cluster_id=$tableAlias.cluster_id and deleted=:false) = 1")
                    ->andWhere("(select count(id) from $stagingTableName st where st.id=$tableAlias.entity_id and (st.master_record_id is null or (st.master_record_id is not null and not exists (select 1 from $masterTableName me where me.id = st.master_record_id and me.deleted=:false))) and st.deleted=:false) = 1")
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
        $masterEntityName = $this->getMetadata()->get("scopes.$stagingEntityName.primaryEntityId");
        $masterTableName = Util::toUnderScore(lcfirst((string)$masterEntityName));

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
            ->where('ci.entity_name=:stagingEntityName and ci.matched_score>=:minimumScore and ci.deleted=:false')
            ->andWhere('se.master_record_id is null or (se.master_record_id is not null and not exists (select 1 from ' . $masterTableName . ' me where me.id = se.master_record_id and me.deleted=:false))')
            ->setParameter('stagingEntityName', $stagingEntityName)
            ->setParameter('minimumScore', $minimumScore)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->orderBy('ci.cluster_id', 'DESC')
            ->groupBy('ci.cluster_id');

        return $qb->fetchAllAssociative();
    }

    public function getClusterItemsWithInvalidMatchedRecords(int $offset = 0, int $limit = PHP_INT_MAX): EntityCollection
    {
        return $this->limit($offset, $limit)->find([
            'skipBelongsToJoins' => true,
            'callbacks' => [function (QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper) {
                $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();

                // Search for cluster items with deleted matched record ids and check if it has another matched record in the cluster
                $qb->leftJoin($tableAlias, 'matched_record', 'mr', "mr.id = $tableAlias.matched_record_id and mr.deleted = :false")
                    ->leftJoin($tableAlias, 'matched_record', 'mr2', "
                (
                    (mr2.source_entity = $tableAlias.entity_name AND mr2.source_entity_id = $tableAlias.entity_id) 
                    OR 
                    (mr2.master_entity = $tableAlias.entity_name AND mr2.master_entity_id = $tableAlias.entity_id)
                )
                AND $tableAlias.cluster_id = (SELECT MAX(ci2.cluster_id) FROM cluster_item ci2 WHERE ci2.entity_name = mr2.source_entity AND ci2.entity_id = mr2.source_entity_id)
                AND $tableAlias.cluster_id = (SELECT MAX(ci3.cluster_id) FROM cluster_item ci3 WHERE ci3.entity_name = mr2.master_entity AND ci3.entity_id = mr2.master_entity_id)
                AND mr2.deleted = :false 
                AND mr2.has_cluster = :true")
                    ->addSelect("MIN(mr2.id) AS other_matched_record_id")
                    ->andWhere("$tableAlias.matched_record_id IS NOT NULL")
                    ->andWhere("mr.id IS NULL")
                    ->groupBy("$tableAlias.id")
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->setParameter('true', true, ParameterType::BOOLEAN);
            }]
        ]);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->isNew()) {
            $this->createClusterActivityNote($entity->get('clusterId'), 'linked', $entity->get('entityName'), $entity->get('entityId'));
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        if (!empty($entity->get('matchedRecordId'))) {
            $this->getEntityManager()->getRepository('MatchedRecord')->markHasNoCluster($entity->get('matchedRecordId'));
        }

        $this->createClusterActivityNote($entity->get('clusterId'), 'unlinked', $entity->get('entityName'), $entity->get('entityId'));
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if ($entity->isNew() && $this->getMetadata()->get(['scopes', $entity->get('entityName'), 'role']) === 'changeRequest') {
            throw new BadRequest("Change request can't be assigned to cluster item.");
        }
    }

    public function afterRemoveRecord(string $entityName, string $entityId): void
    {
        $toRemove = $this->getMetadata()->get("scopes.$entityName.matchDuplicates")
            || $this->getMetadata()->get("scopes.$entityName.matchMasterRecords")
            || !empty($this->getMetadata()->get("scopes.$entityName.primaryEntityId")); // staging entity
        if (!$toRemove) {
            foreach ($this->getMetadata()->get("scopes") ?? [] as $scope => $scopeDefs) {
                if (!empty($scopeDefs['primaryEntityId']) && $scopeDefs['primaryEntityId'] === $entityName) {
                    $toRemove = true;
                    break;
                }
            }
        }

        if ($toRemove) {
            $affectedClusterIds = $this->getDbal()->createQueryBuilder()
                ->select('cluster_id')
                ->from('cluster_item')
                ->where('entity_name=:entityName AND entity_id=:entityId AND deleted=:false')
                ->setParameter('entityName', $entityName)
                ->setParameter('entityId', $entityId)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchFirstColumn();

            $this->getDbal()->createQueryBuilder()
                ->delete('cluster_item')
                ->where('entity_name=:entityName AND entity_id=:entityId')
                ->setParameter('entityName', $entityName)
                ->setParameter('entityId', $entityId)
                ->executeQuery();

            foreach ($affectedClusterIds as $clusterId) {
                $this->createClusterActivityNote($clusterId, 'recordDeleted', $entityName, $entityId);
            }
        }
    }

    private function createMoveNotes(string $clusterIdFrom, string $clusterIdTo, array $items): void
    {
        // Group by entity type for batched name lookups
        $byEntityName = [];
        foreach ($items as $item) {
            $byEntityName[$item['entity_name']][] = $item['entity_id'];
        }

        // Fetch names in one query per entity type
        $namesByTypeAndId = [];
        foreach ($byEntityName as $entityName => $entityIds) {
            $nameField = $this->getMetadata()->get(['scopes', $entityName, 'nameField']) ?? 'name';
            $tableName = $this->getDbal()->quoteIdentifier(Util::toUnderScore(lcfirst($entityName)));
            $quotedNameField = $this->getDbal()->quoteIdentifier($nameField);

            $rows = $this->getDbal()->createQueryBuilder()
                ->select('id', $quotedNameField . ' AS display_name')
                ->from($tableName)
                ->where('id IN (:ids) AND deleted = :false')
                ->setParameter('ids', $entityIds, Mapper::getParameterType($entityIds))
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();

            foreach ($rows as $row) {
                $namesByTypeAndId[$entityName][$row['id']] = $row['display_name'];
            }
        }

        $stagingRecords = [];
        $masterRecords  = [];

        foreach ($items as $item) {
            $entityName = $item['entity_name'];
            $entityId   = $item['entity_id'];

            $name = (!empty($namesByTypeAndId[$entityName][$entityId])) ? $namesByTypeAndId[$entityName][$entityId] : $entityId;

            $record = ['id' => $entityId, 'name' => $name, 'entityName' => $entityName];

            if (!empty($this->getMetadata()->get(['scopes', $entityName, 'primaryEntityId']))) {
                $stagingRecords[] = $record;
            } else {
                $masterRecords[] = $record;
            }
        }

        $clusterRows = $this->getDbal()->createQueryBuilder()
            ->select('id', 'number')
            ->from('cluster')
            ->where('id IN (:ids) AND deleted = :false')
            ->setParameter('ids', [$clusterIdFrom, $clusterIdTo], Mapper::getParameterType([$clusterIdFrom, $clusterIdTo]))
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        $numberById = array_column($clusterRows, 'number', 'id');
        $fromNumber = $numberById[$clusterIdFrom] ?? null;
        $toNumber   = $numberById[$clusterIdTo]   ?? null;

        $this->createClusterActivityNote($clusterIdTo, 'movedToCluster', '', '', [
            'stagingRecords' => $stagingRecords,
            'masterRecords'  => $masterRecords,
            'clusterNumber'  => $fromNumber,
            'clusterId'      => $clusterIdFrom,
        ]);

        $this->createClusterActivityNote($clusterIdFrom, 'movedFromCluster', '', '', [
            'stagingRecords' => $stagingRecords,
            'masterRecords'  => $masterRecords,
            'clusterNumber'  => $toNumber,
            'clusterId'      => $clusterIdTo,
        ]);
    }

    public function createClusterActivityNote(string $clusterId, string $action, string $relatedType = '', string $relatedId = '', array $extraData = []): void
    {
        if ($relatedType !== '') {
            $extraData['entityRole'] = $this->getMetadata()->get(['scopes', $relatedType, 'primaryEntityId']) ? 'staging' : 'master';
        }

        $this->getEntityManager()->getRepository('Selection')->createActivityNote(
            $clusterId, 'Cluster', 'ClusterActivity', $action, $relatedType, $relatedId, $extraData
        );
    }

    public function hasDeletedRecordsToClear(): bool
    {
        return true;
    }

    public function clearDeletedRecords(): void
    {
        parent::clearDeletedRecords();

        $entityNames = $this->getDbal()->createQueryBuilder()
            ->select('entity_name')
            ->distinct()
            ->from('cluster_item')
            ->fetchFirstColumn();

        foreach ($entityNames as $entityName) {
            $tableName = $this->getDbal()->quoteIdentifier(Util::toUnderScore(lcfirst($entityName)));

            $this->getDbal()->createQueryBuilder()
                ->delete('cluster_item')
                ->where("cluster_item.entity_name=:entityName AND NOT EXISTS (SELECT 1 FROM $tableName e WHERE e.id=cluster_item.entity_id and deleted=:false)")
                ->setParameter('entityName', $entityName)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->executeQuery();
        }
    }
}
