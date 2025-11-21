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

use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Repositories\Base;
use Atro\Core\Utils\Util;
use Atro\Entities\Matching as MatchingEntity;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
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

//    public function getMatchedRecords(MatchingEntity $matching, Entity $entity, array $statuses): array
//    {
//        $conn = $this->getEntityManager()->getConnection();
//
//        $select = ['mr.status', 'mr.score', 't.id', 't.name', 'mr.id as mr_id'];
//        foreach ($this->getMetadata()->get("entityDefs.{$matching->get('masterEntity')}.fields.name.lingualFields") ?? [] as $fieldName) {
//            $select[] = 't.'.Util::toUnderScore($fieldName);
//        }
//
//        $result = [
//            'entityName' => $matching->get('masterEntity'),
//            'matches'    => [],
//        ];
//
//        foreach ($this->getMetadata()->get("entityDefs.MatchedRecord.fields.status.options") ?? [] as $status) {
//            if (!in_array($status, $statuses)) {
//                continue;
//            }
//
//            $qb = $conn->createQueryBuilder()
//                ->select(implode(',', $select))
//                ->from('matched_record', 'mr')
//                ->leftJoin(
//                    'mr',
//                    $conn->quoteIdentifier(Util::toUnderScore($matching->get('masterEntity'))),
//                    't',
//                    'mr.master_entity_id = t.id AND t.deleted = :false'
//                )
//                ->where('mr.matching_id = :matchingId')
//                ->andWhere('mr.staging_entity = :stagingEntity')
//                ->andWhere('mr.staging_entity_id = :stagingEntityId')
//                ->andWhere('t.id IS NOT NULL')
//                ->andWhere('mr.status = :status')
//                ->andWhere('mr.deleted = :false')
//                ->setParameter('matchingId', $matching->get('id'))
//                ->setParameter('stagingEntity', $entity->getEntityName())
//                ->setParameter('stagingEntityId', $entity->id)
//                ->setParameter('status', $status)
//                ->setParameter('false', false, ParameterType::BOOLEAN)
//                ->orderBy('mr.score', 'DESC');
//
//            $this->getSelectManager()->putInnerQueryForAclCheck($qb);
//
//            if ($status === 'new') {
//                $qb->setFirstResult(0);
//                $qb->setMaxResults(5);
//            }
//
//            $result['matches'][] = [
//                'status' => $status,
//                'list'   => Util::arrayKeysToCamelCase($qb->fetchAllAssociative()),
//            ];
//        }
//
//        return $result;
//    }

//    public function getForeignMatchedRecords(MatchingEntity $matching, Entity $entity, array $statuses): array
//    {
//        $conn = $this->getEntityManager()->getConnection();
//
//        $select = ['mr.status', 'mr.score', 't.id', 't.name', 'mr.id as mr_id'];
//        foreach ($this->getMetadata()->get("entityDefs.{$matching->get('stagingEntity')}.fields.name.lingualFields") ?? [] as $fieldName) {
//            $select[] = 't.'.Util::toUnderScore($fieldName);
//        }
//
//        $result = [
//            'entityName' => $matching->get('stagingEntity'),
//            'matches'    => [],
//        ];
//
//        foreach ($this->getMetadata()->get("entityDefs.MatchedRecord.fields.status.options") ?? [] as $status) {
//            if (!in_array($status, $statuses)) {
//                continue;
//            }
//
//            $qb = $conn->createQueryBuilder()
//                ->select(implode(',', $select))
//                ->from('matched_record', 'mr')
//                ->leftJoin(
//                    'mr',
//                    $conn->quoteIdentifier(Util::toUnderScore($matching->get('stagingEntity'))),
//                    't',
//                    'mr.staging_entity_id = t.id AND t.deleted = :false'
//                )
//                ->where('mr.matching_id = :matchingId')
//                ->andWhere('mr.master_entity = :masterEntity')
//                ->andWhere('mr.master_entity_id = :masterEntityId')
//                ->andWhere('t.id IS NOT NULL')
//                ->andWhere('mr.status = :status')
//                ->setParameter('matchingId', $matching->get('id'))
//                ->setParameter('masterEntity', $entity->getEntityName())
//                ->setParameter('masterEntityId', $entity->id)
//                ->setParameter('status', $status)
//                ->setParameter('false', false, ParameterType::BOOLEAN)
//                ->orderBy('mr.score', 'DESC');
//
//            $this->getSelectManager()->putInnerQueryForAclCheck($qb);
//
//            if ($status === 'new') {
//                $qb->setFirstResult(0);
//                $qb->setMaxResults(5);
//            }
//
//            $result['matches'][] = [
//                'status' => $status,
//                'list'   => Util::arrayKeysToCamelCase($qb->fetchAllAssociative()),
//            ];
//        }
//
//        return $result;
//    }

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
            'sourceEntity'   => $matching->get('sourceEntity'),
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
