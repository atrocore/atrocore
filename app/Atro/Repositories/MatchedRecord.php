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

    public function createMatchedRecord(
        MatchingEntity $matching,
        string $sourceId,
        string $masterId,
        int $score,
        string $matchedAt,
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
            'matchedAt'      => $matchedAt
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
            $this->createMatchedRecord($matching, $masterId, $sourceId, $score, $matchedAt, true);
        }
    }
}
