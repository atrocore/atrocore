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
use Atro\Core\Utils\Util;
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

    public function markHasNoCluster(string $id): void
    {
        $this->getConnection()->createQueryBuilder()
            ->update('matched_record')
            ->set('has_cluster', ':false')
            ->where('id=:id')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('id', $id)
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

        return $this->getConnection()->createQueryBuilder()
            ->select('mr.id, mr.type, mr.source_entity, mr.source_entity_id, ci.cluster_id as source_cluster_id, mr.master_entity, mr.master_entity_id, ci1.cluster_id as master_cluster_id')
            ->from('matched_record', 'mr')
            ->leftJoin('mr', 'cluster_item', 'ci', 'ci.entity_name = mr.source_entity AND ci.entity_id = mr.source_entity_id AND ci.deleted=:false AND ci.cluster_id IS NOT NULL')
            ->leftJoin('mr', 'cluster_item', 'ci1', 'ci1.entity_name = mr.master_entity AND ci1.entity_id = mr.master_entity_id AND ci1.deleted=:false AND ci1.cluster_id IS NOT NULL')
            ->where('mr.master_entity IN (:entitiesNames) OR mr.source_entity IN (:entitiesNames)')
            ->andWhere('mr.deleted = :false')
            ->andWhere('mr.has_cluster = :false')
            ->setFirstResult(0)
            ->setMaxResults($limit)
            ->addOrderBy('mr.source_entity', 'ASC')
            ->addOrderBy('mr.source_entity_id', 'ASC')
            ->setParameter('entitiesNames', $entitiesNames, Connection::PARAM_STR_ARRAY)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();
    }

    public function afterRemoveRecord(string $entityName, string $entityId): void
    {
        $toRemove = $this->getMetadata()->get("scopes.$entityName.matchDuplicates") || $this->getMetadata()->get("scopes.$entityName.matchMasterRecords");
        if (!$toRemove) {
            foreach ($this->getMetadata()->get("scopes") ?? [] as $scope => $scopeDefs) {
                if (!empty($scopeDefs['masterEntity']) && $scopeDefs['masterEntity'] === $entityName) {
                    $toRemove = true;
                    break;
                }
            }
        }

        if ($toRemove) {
            $this->getConnection()->createQueryBuilder()
                ->delete('matched_record')
                ->where('(source_entity=:entityName AND source_entity_id=:entityId) OR (master_entity=:entityName AND master_entity_id=:entityId)')
                ->setParameter('entityName', $entityName)
                ->setParameter('entityId', $entityId)
                ->executeQuery();
        }
    }

    public function createMatchedRecord(
        MatchingEntity $matching,
        string $sourceId,
        string $masterId,
        int $score,
        string $matchedAt,
        bool $skipBidirectional = false
    ): void {
        $sql
            = "INSERT INTO matched_record (id, type, source_entity, source_entity_id, master_entity, master_entity_id, score, matching_id, hash, created_at, modified_at, created_by_id, modified_by_id) VALUES (:id, :type, :sourceEntity, :sourceEntityId, :masterEntity, :masterEntityId, :score, :matchingId, :hash, :createdAt, :modifiedAt, :createdById, :modifiedById)";
        if (Converter::isPgSQL($this->getConnection())) {
            $sql .= " ON CONFLICT (deleted, hash) DO UPDATE SET score = EXCLUDED.score, modified_at = EXCLUDED.modified_at, modified_by_id = EXCLUDED.modified_by_id RETURNING xmax";
        } else {
            $sql .= " ON DUPLICATE KEY UPDATE score = VALUES(score), modified_at = VALUES(modified_at), modified_by_id = VALUES(modified_by_id)";
        }

        $userId = $this->getEntityManager()->getUser()->id;
        $hash = md5(implode('_', [$matching->id, $matching->get('entity'), $sourceId, $matching->get('masterEntity'), $masterId]));

        $stmt = $this->getEntityManager()->getPDO()->prepare($sql);

        $stmt->bindValue(':id', Util::generateId());
        $stmt->bindValue(':type', $matching->get('type'));
        $stmt->bindValue(':sourceEntity', $matching->get('entity'));
        $stmt->bindValue(':sourceEntityId', $sourceId);
        $stmt->bindValue(':masterEntity', $matching->get('masterEntity'));
        $stmt->bindValue(':masterEntityId', $masterId);
        $stmt->bindValue(':score', $score);
        $stmt->bindValue(':matchingId', $matching->id);
        $stmt->bindValue(':hash', $hash);
        $stmt->bindValue(':createdAt', $matchedAt);
        $stmt->bindValue(':modifiedAt', $matchedAt);
        $stmt->bindValue(':createdById', $userId);
        $stmt->bindValue(':modifiedById', $userId);

        $stmt->execute();

        if (!$skipBidirectional && $matching->get('type') === 'duplicate') {
            $this->createMatchedRecord($matching, $masterId, $sourceId, $score, $matchedAt, true);
        }
    }

    public function removeOldMatches(MatchingEntity $matching, string $matchedAt): void
    {
        $this->getConnection()->createQueryBuilder()
            ->delete('matched_record')
            ->where('matching_id=:matchingId AND modified_at<:matchedAt')
            ->setParameter('matchingId', $matching->id)
            ->setParameter('matchedAt', $matchedAt)
            ->executeQuery();
    }
}
