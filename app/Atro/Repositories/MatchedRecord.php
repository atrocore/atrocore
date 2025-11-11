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
use Atro\Core\Utils\Util;
use Atro\Entities\Matching as MatchingEntity;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

class MatchedRecord extends Base
{
    public function getMatchedRecords(MatchingEntity $matching, Entity $entity, array $statuses): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $select = ['mr.status', 'mr.score', 't.id', 't.name', 'mr.id as mr_id'];
        foreach ($this->getMetadata()->get("entityDefs.{$matching->get('masterEntity')}.fields.name.lingualFields") ?? [] as $fieldName) {
            $select[] = 't.'.Util::toUnderScore($fieldName);
        }

        $result = [
            'entityName' => $matching->get('masterEntity'),
            'matches'    => [],
        ];


        foreach (['confirmed', 'found', 'rejected'] as $status) {
            if (!in_array($status, $statuses)) {
                continue;
            }

            $qb = $conn->createQueryBuilder()
                ->select(implode(',', $select))
                ->from('matched_record', 'mr')
                ->leftJoin(
                    'mr',
                    $conn->quoteIdentifier(Util::toUnderScore($matching->get('masterEntity'))),
                    't',
                    'mr.master_entity_id = t.id AND t.deleted = :false'
                )
                ->where('mr.matching_id = :matchingId')
                ->andWhere('mr.staging_entity = :stagingEntity')
                ->andWhere('mr.staging_entity_id = :stagingEntityId')
                ->andWhere('t.id IS NOT NULL')
                ->andWhere('mr.status = :status')
                ->andWhere('mr.deleted = :false')
                ->setParameter('matchingId', $matching->get('id'))
                ->setParameter('stagingEntity', $entity->getEntityName())
                ->setParameter('stagingEntityId', $entity->id)
                ->setParameter('status', $status)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->orderBy('mr.score', 'DESC');

            $this->getSelectManager()->putInnerQueryForAclCheck($qb);

            if ($status === 'found') {
                $qb->setFirstResult(0);
                $qb->setMaxResults(5);
            }

            $result['matches'][] = [
                'status' => $status,
                'list'   => Util::arrayKeysToCamelCase($qb->fetchAllAssociative()),
            ];
        }

        return $result;
    }

    public function getForeignMatchedRecords(MatchingEntity $matching, Entity $entity, array $statuses): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $select = ['mr.status', 'mr.score', 't.id', 't.name', 'mr.id as mr_id'];
        foreach ($this->getMetadata()->get("entityDefs.{$matching->get('stagingEntity')}.fields.name.lingualFields") ?? [] as $fieldName) {
            $select[] = 't.'.Util::toUnderScore($fieldName);
        }

        $result = [
            'entityName' => $matching->get('stagingEntity'),
            'matches'    => [],
        ];

        foreach (['confirmed', 'found', 'rejected'] as $status) {
            if (!in_array($status, $statuses)) {
                continue;
            }

            $qb = $conn->createQueryBuilder()
                ->select(implode(',', $select))
                ->from('matched_record', 'mr')
                ->leftJoin(
                    'mr',
                    $conn->quoteIdentifier(Util::toUnderScore($matching->get('stagingEntity'))),
                    't',
                    'mr.staging_entity_id = t.id AND t.deleted = :false'
                )
                ->where('mr.matching_id = :matchingId')
                ->andWhere('mr.master_entity = :masterEntity')
                ->andWhere('mr.master_entity_id = :masterEntityId')
                ->andWhere('t.id IS NOT NULL')
                ->andWhere('mr.status = :status')
                ->setParameter('matchingId', $matching->get('id'))
                ->setParameter('masterEntity', $entity->getEntityName())
                ->setParameter('masterEntityId', $entity->id)
                ->setParameter('status', $status)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->orderBy('mr.score', 'DESC');

            $this->getSelectManager()->putInnerQueryForAclCheck($qb);

            if ($status === 'found') {
                $qb->setFirstResult(0);
                $qb->setMaxResults(5);
            }

            $result['matches'][] = [
                'status' => $status,
                'list'   => Util::arrayKeysToCamelCase($qb->fetchAllAssociative()),
            ];
        }

        return $result;
    }

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
