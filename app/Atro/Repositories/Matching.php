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

declare(strict_types=1);

namespace Atro\Repositories;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\MatchingManager;
use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Core\Utils\Util;
use Atro\Entities\Matching as MatchingEntity;
use Doctrine\DBAL\ParameterType;
use Espo\Core\ORM\Entity;
use Espo\ORM\Entity as OrmEntity;
use Espo\ORM\EntityCollection;

class Matching extends ReferenceData
{
    public static function prepareFieldName(string $code): string
    {
        return Util::toCamelCase("matching_" . Util::toUnderScore(lcfirst($code)));
    }

    protected function beforeSave(OrmEntity $entity, array $options = []): void
    {
        if ($entity->isAttributeChanged('entity') && $entity->get('type') === 'duplicate') {
            $entity->set('stagingEntity', $entity->get('entity'));
            $entity->set('masterEntity', $entity->get('entity'));
        }

        if ($entity->isAttributeChanged('name') && $entity->get('type') === 'duplicate') {
            $entity->set('foreignName', $entity->get('name'));
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(OrmEntity $entity, array $options = []): void
    {
        parent::afterSave($entity, $options);

        if ($entity->isAttributeChanged('code')) {
            $this->rebuild();
        }

        $this->unmarkAllMatchingSearched($entity);
    }

    public function validateCode(OrmEntity $entity): void
    {
        parent::validateCode($entity);

        if (!preg_match('/^[A-Za-z0-9_]*$/', $entity->get('code'))) {
            throw new BadRequest($this->translate('notValidCode', 'exceptions', 'Matching'));
        }
    }

    public function findRelated(OrmEntity $entity, string $link, array $selectParams): EntityCollection
    {
        if ($link === 'matchingRules') {
            $selectParams['whereClause'] = [['matchingId=' => $entity->get('id')]];

            return $this->getEntityManager()->getRepository('MatchingRule')->find($selectParams);
        }

        if ($link === 'matchedRecords') {
            $selectParams['whereClause'] = [['matchingId=' => $entity->get('id')]];

            return $this->getEntityManager()->getRepository('MatchedRecord')->find($selectParams);
        }

        return parent::findRelated($entity, $link, $selectParams);
    }

    public function countRelated(OrmEntity $entity, string $relationName, array $params = []): int
    {
        if ($relationName === 'matchingRules') {
            $params['offset'] = 0;
            $params['limit'] = \PHP_INT_MAX;

            return count($this->findRelated($entity, $relationName, $params));
        }

        if ($relationName === 'matchedRecords') {
            $selectParams['whereClause'] = [['matchingId=' => $entity->get('id')]];

            return $this->getEntityManager()->getRepository('MatchedRecord')->count($selectParams);
        }

        return parent::countRelated($entity, $relationName, $params);
    }

    public function markMatchingSearched(MatchingEntity $matching, string $entityName, string $entityId): void
    {
        $conn = $this->getEntityManager()->getConnection();

        $conn->createQueryBuilder()
            ->update($conn->quoteIdentifier(Util::toUnderScore(lcfirst($entityName))))
            ->set(Util::toUnderScore(self::prepareFieldName($matching->get('code'))), ':true')
            ->where('id = :id')
            ->setParameter('id', $entityId)
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->executeQuery();
    }

    public function isMatchingSearchedForDuplicate(MatchingEntity $matching, Entity $entity): bool
    {
        return $this->isMatchingSearchedForStaging($matching, $entity);
    }

    public function isMatchingSearchedForStaging(MatchingEntity $matching, Entity $entity): bool
    {
        $conn = $this->getEntityManager()->getConnection();

        $column = Util::toUnderScore(self::prepareFieldName($matching->get('code')));

        $res = $conn->createQueryBuilder()
            ->select("id, $column as val")
            ->from($conn->quoteIdentifier(Util::toUnderScore(lcfirst($matching->get('stagingEntity')))))
            ->where('id=:id')
            ->setParameter('id', $entity->id)
            ->fetchAssociative();

        return !empty($res['val']);
    }

    public function unmarkAllMatchingSearched(MatchingEntity $matching): void
    {
        $conn = $this->getEntityManager()->getConnection();

        $column = Util::toUnderScore(self::prepareFieldName($matching->get('code')));
        $conn->createQueryBuilder()
            ->update($conn->quoteIdentifier(Util::toUnderScore(lcfirst($matching->get('stagingEntity')))))
            ->set($column, ':false')
            ->where("$column = :true")
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->executeQuery();

        // it needs for immediate start of finding matched records
        $this->getMatchingManager()->createFindMatchesJob($matching);
    }

    public function findPossibleMatchesForEntity(MatchingEntity $matching, Entity $entity): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $qb = $conn->createQueryBuilder();
        $qb
            ->select('id')
            ->from($conn->quoteIdentifier(Util::toUnderScore($matching->get('masterEntity'))))
            ->where('deleted=:false')
            ->setParameter('false', false, ParameterType::BOOLEAN);

        if ($matching->get('masterEntity') === $matching->get('stagingEntity')) {
            $qb
                ->andWhere('id != :id')
                ->setParameter('id', $entity->get('id'));
        }
        $rulesParts = [];
        foreach ($matching->get('matchingRules') as $rule) {
            $rulesParts[] = $rule->prepareMatchingSqlPart($qb, $entity);
        }
        $qb->andWhere(implode(' OR ', $rulesParts));

        return $qb->fetchAllAssociative();
    }

    public function deleteMatchedRecordsForMatching(MatchingEntity $matching): void
    {
        $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->delete('matched_record')
            ->where('matching_id = :matchingId')
            ->setParameter('matchingId', $matching->id)
            ->executeQuery();
    }

    public function deleteMatchedRecordsForEntity(MatchingEntity $matching, Entity $entity): void
    {
        $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->delete('matched_record')
            ->where('matching_id = :matchingId')
            ->andWhere('staging_entity = :entityName AND staging_entity_id = :entityId')
            ->andWhere('status=:foundStatus')
            ->setParameter('matchingId', $matching->id)
            ->setParameter('entityName', $entity->getEntityName())
            ->setParameter('entityId', $entity->id)
            ->setParameter('foundStatus', 'found')
            ->executeQuery();

        // for duplicates
        if ($matching->get('stagingEntity') === $matching->get('masterEntity')) {
            $this->getEntityManager()->getConnection()->createQueryBuilder()
                ->delete('matched_record')
                ->where('matching_id = :matchingId')
                ->andWhere('master_entity = :entityName AND master_entity_id = :entityId')
                ->andWhere('status=:foundStatus')
                ->setParameter('matchingId', $matching->id)
                ->setParameter('entityName', $entity->getEntityName())
                ->setParameter('entityId', $entity->id)
                ->setParameter('foundStatus', 'found')
                ->executeQuery();
        }
    }

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

            $qb = $this->getEntityManager()->getConnection()->createQueryBuilder()
                ->insert('matched_record')
                ->setValue('id', ':id')
                ->setValue('matching_id', ':matchingId')
                ->setValue('staging_entity', ':stagingEntity')
                ->setValue('staging_entity_id', ':stagingEntityId')
                ->setValue('master_entity', ':masterEntity')
                ->setValue('master_entity_id', ':masterEntityId')
                ->setValue('score', ':score')
                ->setValue('status', ':status')
                ->setValue('hash', ':hash')
                ->setParameter('id', Util::generateId())
                ->setParameter('matchingId', $matching->id)
                ->setParameter('stagingEntity', $matching->get('stagingEntity'))
                ->setParameter('stagingEntityId', $item['stagingEntityId'])
                ->setParameter('masterEntity', $matching->get('masterEntity'))
                ->setParameter('masterEntityId', $item['masterEntityId'])
                ->setParameter('score', $score)
                ->setParameter('status', 'found')
                ->setParameter('hash', md5(implode('_', $hashParts)));

            try {
                $qb->executeQuery();
            } catch (\Throwable $e) {
            }
        }
    }

    public function getMatchedRecords(MatchingEntity $matching, Entity $entity): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $select = ['mr.status', 'mr.score', 't.id', 't.name', 'mr.id as mr_id'];
        foreach ($this->getMetadata()->get("entityDefs.{$matching->get('masterEntity')}.fields.name.lingualFields") ?? [] as $fieldName) {
            $select[] = 't.' . Util::toUnderScore($fieldName);
        }

        $result = [
            'entityName' => $matching->get('masterEntity'),
            'matches'    => []
        ];

        foreach (['confirmed', 'found', 'rejected'] as $status) {
            $qb = $conn->createQueryBuilder()
                ->select(implode(',', $select))
                ->from('matched_record', 'mr')
                ->leftJoin('mr', $conn->quoteIdentifier(Util::toUnderScore($matching->get('masterEntity'))), 't', 'mr.master_entity_id = t.id AND t.deleted = :false')
                ->where('mr.matching_id = :matchingId')
                ->andWhere('mr.staging_entity = :stagingEntity')
                ->andWhere('mr.staging_entity_id = :stagingEntityId')
                ->andWhere('t.id IS NOT NULL')
                ->andWhere('mr.status = :status')
                ->setParameter('matchingId', $matching->get('id'))
                ->setParameter('stagingEntity', $entity->getEntityName())
                ->setParameter('stagingEntityId', $entity->id)
                ->setParameter('status', $status)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->orderBy('mr.score', 'DESC');

            if ($status === 'found') {
                $qb->setFirstResult(0);
                $qb->setMaxResults(5);
            }

            $result['matches'][] = [
                'status' => $status,
                'list'   => Util::arrayKeysToCamelCase($qb->fetchAllAssociative())
            ];
        }

        return $result;
    }

    public function getForeignMatchedRecords(MatchingEntity $matching, Entity $entity): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $select = ['mr.status', 'mr.score', 't.id', 't.name', 'mr.id as mr_id'];
        foreach ($this->getMetadata()->get("entityDefs.{$matching->get('stagingEntity')}.fields.name.lingualFields") ?? [] as $fieldName) {
            $select[] = 't.' . Util::toUnderScore($fieldName);
        }

        $result = [
            'entityName' => $matching->get('stagingEntity'),
            'matches'    => []
        ];

        foreach (['confirmed', 'found', 'rejected'] as $status) {
            $qb = $conn->createQueryBuilder()
                ->select(implode(',', $select))
                ->from('matched_record', 'mr')
                ->leftJoin('mr', $conn->quoteIdentifier(Util::toUnderScore($matching->get('stagingEntity'))), 't', 'mr.staging_entity_id = t.id AND t.deleted = :false')
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

            if ($status === 'found') {
                $qb->setFirstResult(0);
                $qb->setMaxResults(5);
            }

            $result['matches'][] = [
                'status' => $status,
                'list'   => Util::arrayKeysToCamelCase($qb->fetchAllAssociative())
            ];
        }

        return $result;
    }

    protected function rebuild(): void
    {
        (new \Atro\Core\Application())->getContainer()->get('dataManager')->rebuild();
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('matchingManager');
    }

    protected function getMatchingManager(): MatchingManager
    {
        return $this->getInjection('matchingManager');
    }
}
