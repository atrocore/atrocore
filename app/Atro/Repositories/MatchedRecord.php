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
use Espo\ORM\Entity;

class MatchedRecord extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        throw new Error('MatchedRecord cannot be saved directly.');
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
}
