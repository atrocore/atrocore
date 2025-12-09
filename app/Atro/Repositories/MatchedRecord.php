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
use Atro\Entities\Matching as MatchingEntity;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Espo\ORM\Entity;

class MatchedRecord extends Base
{
    public function createUniqHash(Entity $matchedRecord): string
    {
        $hashParts = [
            $matchedRecord->get('matchingId'),
            $matchedRecord->get('sourceEntity'),
            $matchedRecord->get('sourceEntityId'),
            $matchedRecord->get('masterEntity'),
            $matchedRecord->get('masterEntityId'),
        ];

        return md5(implode('_', $hashParts));
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if ($entity->isNew()) {
            $entity->set('hash', $this->createUniqHash($entity));
        }
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

    public function checkMatchedRecordsMax(string $matchingId, int $matchedRecordsMax): bool
    {
        $sql = "SELECT EXISTS (SELECT 1 FROM matched_record WHERE matching_id = :matchingId AND deleted = :false GROUP BY master_entity_id HAVING COUNT(DISTINCT source_entity_id) >= :max) AS exists";

        $stmt = $this->getEntityManager()->getPDO()->prepare($sql);

        $stmt->bindValue(':matchingId', $matchingId);
        $stmt->bindValue(':false', false, \PDO::PARAM_BOOL);
        $stmt->bindValue(':max', $matchedRecordsMax, \PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchColumn();
    }

    public function deleteMatchedRecordsForEntity(MatchingEntity $matching, Entity $entity): void
    {
        $this
            ->where([
                'type'           => $matching->get('type'),
                'sourceEntity'   => $entity->getEntityName(),
                'sourceEntityId' => $entity->id,
            ])
            ->removeCollection();

        if ($matching->get('type') === 'duplicate') {
            $this
                ->where([
                    'type'           => $matching->get('type'),
                    'masterEntity'   => $entity->getEntityName(),
                    'masterEntityId' => $entity->id,
                ])
                ->removeCollection();
        }
    }

    public function createMatchedRecord(
        MatchingEntity $matching,
        string $sourceId,
        string $masterId,
        int $score,
        bool $skipBidirectional = false
    ): void {
        // create new record
        $matchedRecord = $this->get();
        $matchedRecord->set([
            'type'           => $matching->get('type'),
            'sourceEntity'   => $matching->get('entity'),
            'sourceEntityId' => $sourceId,
            'masterEntity'   => $matching->get('masterEntity'),
            'masterEntityId' => $masterId,
            'score'          => $score,
            'matchingId'     => $matching->id,
        ]);

        if (!empty($exists = $this->where(['hash' => $this->createUniqHash($matchedRecord)])->findOne())) {
            // update if exists
            $matchedRecord = $exists;
            $matchedRecord->set('score', $score);
        }

        try {
            $this->getEntityManager()->saveEntity($matchedRecord);
        } catch (UniqueConstraintViolationException $e) {
        }

        if (!$skipBidirectional && $matching->get('type') === 'duplicate') {
            $this->createMatchedRecord($matching, $masterId, $sourceId, $score, true);
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('selectManagerFactory');
    }

    protected function getSelectManager(): \Atro\SelectManagers\MatchedRecord
    {
        $selectManager = $this->getInjection('selectManagerFactory')->create('MatchedRecord');
        $selectManager->isSubQuery = true;

        return $selectManager;
    }
}
