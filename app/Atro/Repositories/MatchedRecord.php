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

class MatchedRecord extends Base
{
    public function createMatchedRecord(MatchingEntity $matching, string $stagingEntityId, string $masterEntityId, int $score): void
    {
        $items = [
            [
                'stagingEntityId' => $stagingEntityId,
                'masterEntityId'  => $masterEntityId
            ]
        ];

        // for duplicates
        if ($matching->get('stagingEntity') === $matching->get('masterEntity')) {
            $items[] = [
                'stagingEntityId' => $masterEntityId,
                'masterEntityId'  => $stagingEntityId
            ];
        }

        foreach ($items as $item) {
            $hashParts = [
                $matching->id,
                $matching->get('stagingEntity'),
                $item['stagingEntityId'],
                $matching->get('masterEntity'),
                $item['masterEntityId']
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
                    'stagingEntityId' => $item['stagingEntityId'],
                    'stagingEntityId' => $item['stagingEntityId'],
                    'masterEntity'    => $matching->get('masterEntity'),
                    'masterEntityId'  => $item['masterEntityId'],
                    'score'           => $score,
                    'status'          => 'found',
                    'hash'            => $hash,
                ]);
            }

            try {
                $this->getEntityManager()->saveEntity($matchedRecord);
            } catch (UniqueConstraintViolationException $e) {
            }
        }
    }
}
