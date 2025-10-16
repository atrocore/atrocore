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
    public function deleteMatchedRecordsForEntity(MatchingEntity $matching, Entity $entity): void
    {
        $this
            ->where([
                'matchingId'      => $matching->id,
                'stagingEntity'   => $entity->getEntityName(),
                'stagingEntityId' => $entity->id,
                'status'          => 'found',
            ])
            ->removeCollection();

        if ($matching->get('type') === 'bidirectional') {
            $this
                ->where([
                    'matchingId'     => $matching->id,
                    'masterEntity'   => $entity->getEntityName(),
                    'masterEntityId' => $entity->id,
                    'status'         => 'found',
                ])
                ->removeCollection();
        }
    }

    public function createMatchedRecord(
        MatchingEntity $matching,
        string $stagingId,
        string $masterId,
        int $score,
        bool $skipBidirectional = false
    ): void {
        $hashParts = [
            $matching->id,
            $matching->get('stagingEntity'),
            $stagingId,
            $matching->get('masterEntity'),
            $masterId,
        ];

        $hash = md5(implode('_', $hashParts));

        $matchedRecord = $this
            ->where(['hash' => $hash])
            ->findOne();

        if (!empty($matchedRecord)) {
            // update if exists
            $matchedRecord->set('score', $score);
        } else {
            // create if not exists
            $matchedRecord = $this->get();
            $matchedRecord->set([
                'matchingId'      => $matching->id,
                'stagingEntity'   => $matching->get('stagingEntity'),
                'stagingEntityId' => $stagingId,
                'masterEntity'    => $matching->get('masterEntity'),
                'masterEntityId'  => $masterId,
                'score'           => $score,
                'status'          => 'found',
                'hash'            => $hash,
            ]);
        }

        try {
            $this->getEntityManager()->saveEntity($matchedRecord);
        } catch (UniqueConstraintViolationException $e) {
        }

        if (!$skipBidirectional && $matching->get('type') === 'bidirectional') {
            $this->createMatchedRecord($matching, $masterId, $stagingId, $score, true);
        }
    }
}
