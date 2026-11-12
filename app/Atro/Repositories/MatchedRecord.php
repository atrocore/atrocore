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

use Atro\Core\Exceptions\Error;
use Atro\Core\Templates\Repositories\Base;
use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Atro\Core\Utils\IdGenerator;
use Atro\Entities\Matching as MatchingEntity;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

class MatchedRecord extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        throw new Error('MatchedRecord cannot be saved directly.');
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        throw new Error('MatchedRecord cannot be removed directly.');
    }

    public function markHasCluster(string $id): void
    {
        $this->getConnection()->createQueryBuilder()
            ->update('matched_record')
            ->set('has_cluster', ':true')
            ->where('id=:id')
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->setParameter('id', $id)
            ->executeQuery();
    }

    public function markHasNoCluster(string $entityName, string $entityId): void
    {
        $this->getDbal()->createQueryBuilder()
            ->update('matched_record')
            ->set('has_cluster', ':false')
            ->where('(match_subject_entity=:entityName AND match_subject_entity_id=:entityId) OR (match_candidate_entity=:entityName AND match_candidate_entity_id=:entityId)')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('entityName', $entityName)
            ->setParameter('entityId', $entityId)
            ->executeQuery();
    }

    public function getForMasterEntity(string $masterEntity, int $limit = PHP_INT_MAX): array
    {
        $entitiesNames = [$masterEntity];
        foreach ($this->getMetadata()->get("scopes") ?? [] as $scope => $scopeDefs) {
            if (!empty($scopeDefs['primaryEntityId']) && $scopeDefs['primaryEntityId'] === $masterEntity) {
                $entitiesNames[] = $scope;
            }
        }

        return $this->getDbal()->createQueryBuilder()
            ->select(
                'mr.id, mr.type, mr.score, mr.match_subject_entity, mr.match_subject_entity_id, ci.cluster_id as match_subject_cluster_id, mr.match_candidate_entity, mr.match_candidate_entity_id, ci1.cluster_id as match_candidate_cluster_id'
            )
            ->from('matched_record', 'mr')
            ->leftJoin('mr', 'cluster_item', 'ci', 'ci.entity_name = mr.match_subject_entity AND ci.entity_id = mr.match_subject_entity_id AND ci.deleted=:false AND ci.cluster_id IS NOT NULL')
            ->leftJoin(
                'mr', 'cluster_item', 'ci1', 'ci1.entity_name = mr.match_candidate_entity AND ci1.entity_id = mr.match_candidate_entity_id AND ci1.deleted=:false AND ci1.cluster_id IS NOT NULL'
            )
            ->where('mr.match_candidate_entity IN (:entitiesNames) OR mr.match_subject_entity IN (:entitiesNames)')
            ->andWhere('mr.deleted = :false')
            ->andWhere('mr.has_cluster = :false')
            ->setFirstResult(0)
            ->setMaxResults($limit)
            ->addOrderBy('mr.match_subject_entity', 'ASC')
            ->addOrderBy('mr.match_subject_entity_id', 'ASC')
            ->setParameter('entitiesNames', $entitiesNames, Connection::PARAM_STR_ARRAY)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();
    }

    public function getForEntityRecord(string $entityName, string $entityId, string $rejectedClusterItemId, array $rejectedClusterIds): array
    {
        $res = $this->getDbal()->createQueryBuilder()
            ->select('mr.id, mr.type, mr.match_subject_entity, mr.match_subject_entity_id, ci.cluster_id as match_subject_cluster_id, mr.match_candidate_entity, mr.match_candidate_entity_id, ci1.cluster_id as match_candidate_cluster_id')
            ->from('matched_record', 'mr')
            ->leftJoin('mr', 'cluster_item', 'ci', 'ci.id <> :itemId and ci.entity_name = mr.match_subject_entity AND ci.entity_id = mr.match_subject_entity_id AND ci.deleted=:false')
            ->leftJoin(
                'mr', 'cluster_item', 'ci1', 'ci1.id <> :itemId and ci1.entity_name = mr.match_candidate_entity AND ci1.entity_id = mr.match_candidate_entity_id AND ci1.deleted=:false'
            )
            ->where('(mr.match_subject_entity=:entityName AND mr.match_subject_entity_id=:entityId) or (mr.match_candidate_entity=:entityName AND mr.match_candidate_entity_id=:entityId)')
            ->andWhere('(ci.cluster_id is not null or ci1.cluster_id is not null) and (ci.cluster_id is null or ci.cluster_id not in (:clusterIds)) and (ci1.cluster_id is null or ci1.cluster_id not in (:clusterIds)) and mr.deleted = :false')
            ->setParameter('entityName', $entityName)
            ->setParameter('entityId', $entityId)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('clusterIds', $rejectedClusterIds, Connection::PARAM_STR_ARRAY)
            ->setParameter('itemId', $rejectedClusterItemId)
            ->addOrderBy('mr.score', 'DESC')
            ->fetchAssociative();

        if (empty($res)) {
            return [];
        }

        return $res;
    }

    public function afterRemoveRecord(string $entityName, string $entityId): void
    {
        $toRemove = $this->getMetadata()->get("scopes.$entityName.matchDuplicates") || $this->getMetadata()->get("scopes.$entityName.matchMasterRecords");
        if (!$toRemove) {
            foreach ($this->getMetadata()->get("scopes") ?? [] as $scope => $scopeDefs) {
                if (!empty($scopeDefs['primaryEntityId']) && $scopeDefs['primaryEntityId'] === $entityName) {
                    $toRemove = true;
                    break;
                }
            }
        }

        if ($toRemove) {
            $this->getConnection()->createQueryBuilder()
                ->delete('matched_record')
                ->where('(match_subject_entity=:entityName AND match_subject_entity_id=:entityId) OR (match_candidate_entity=:entityName AND match_candidate_entity_id=:entityId)')
                ->setParameter('entityName', $entityName)
                ->setParameter('entityId', $entityId)
                ->executeQuery();
        }
    }

    public function createMatchedRecord(
        MatchingEntity $matching,
        string         $matchSubjectId,
        string         $matchCandidateId,
        int            $score,
        string         $matchedAt
    ): void
    {
        if ($matching->get('type') === 'duplicate' && strcmp($matchSubjectId, $matchCandidateId) > 0) {
            [$matchSubjectId, $matchCandidateId] = [$matchCandidateId, $matchSubjectId];
        }

        $sql
            = "INSERT INTO matched_record (id, type, match_subject_entity, match_subject_entity_id, match_candidate_entity, match_candidate_entity_id, score, matching_id, hash, created_at, modified_at, created_by_id, modified_by_id) VALUES (:id, :type, :matchSubjectEntity, :matchSubjectEntityId, :matchCandidateEntity, :matchCandidateEntityId, :score, :matchingId, :hash, :createdAt, :modifiedAt, :createdById, :modifiedById)";
        if (Converter::isPgSQL($this->getDbal())) {
            $sql .= " ON CONFLICT (deleted, hash) DO UPDATE SET has_cluster = CASE WHEN matched_record.score != EXCLUDED.score THEN :false ELSE matched_record.has_cluster END, score = EXCLUDED.score, modified_at = EXCLUDED.modified_at, modified_by_id = EXCLUDED.modified_by_id RETURNING xmax";
        } else {
            $sql .= " ON DUPLICATE KEY UPDATE has_cluster = CASE WHEN score != VALUES(score) THEN :false ELSE has_cluster END, score = VALUES(score), modified_at = VALUES(modified_at), modified_by_id = VALUES(modified_by_id)";
        }

        $userId = $this->getEntityManager()->getUser()->id;
        $hash = md5(implode('_', [$matching->get('number'), $matching->get('entity'), $matchSubjectId, $matching->get('masterEntity'), $matchCandidateId]));

        $stmt = $this->getEntityManager()->getPDO()->prepare($sql);

        $stmt->bindValue(':id', IdGenerator::uuid());
        $stmt->bindValue(':type', $matching->get('type'));
        $stmt->bindValue(':matchSubjectEntity', $matching->get('entity'));
        $stmt->bindValue(':matchSubjectEntityId', $matchSubjectId);
        $stmt->bindValue(':matchCandidateEntity', $matching->get('masterEntity'));
        $stmt->bindValue(':matchCandidateEntityId', $matchCandidateId);
        $stmt->bindValue(':score', $score);
        $stmt->bindValue(':matchingId', $matching->id);
        $stmt->bindValue(':hash', $hash);
        $stmt->bindValue(':createdAt', $matchedAt);
        $stmt->bindValue(':modifiedAt', $matchedAt);
        $stmt->bindValue(':createdById', $userId);
        $stmt->bindValue(':modifiedById', $userId);
        $stmt->bindValue(':false', false, ParameterType::BOOLEAN);

        $stmt->execute();
    }

    public function removeOldMatches(MatchingEntity $matching, string $matchedAt, string $entityName, string $entityId): void
    {
        $entityCondition = 'match_subject_entity=:entityName AND match_subject_entity_id=:entityId';
        if ($matching->get('type') === 'duplicate') {
            $entityCondition = '(' . $entityCondition . ') OR (match_candidate_entity=:entityName AND match_candidate_entity_id=:entityId)';
        }

        $this->getDbal()->createQueryBuilder()
            ->delete('matched_record')
            ->where('matching_id=:matchingId')
            ->andWhere('modified_at<:matchedAt')
            ->andWhere($entityCondition)
            ->setParameter('matchingId', $matching->id)
            ->setParameter('matchedAt', $matchedAt)
            ->setParameter('entityName', $entityName)
            ->setParameter('entityId', $entityId)
            ->executeQuery();
    }
}
