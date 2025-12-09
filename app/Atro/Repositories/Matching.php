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

use Atro\Core\MatchingManager;
use Atro\Core\Templates\Repositories\Base;
use Atro\Core\Utils\Util;
use Atro\Entities\Matching as MatchingEntity;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;
use Espo\ORM\Entity as OrmEntity;

class Matching extends Base
{
    public static function createCodeForDuplicate(string $entityName): string
    {
        return "$entityName-D2D";
    }

    public static function createCodeForMasterRecord(string $entityName): string
    {
        return "$entityName-S2M";
    }

    public static function prepareFieldName(string $code): string
    {
        $parts = explode('-', $code);

        return 'Matching' . $parts[0] . ucfirst(strtolower($parts[1]));
    }

    public function getEntityByCode(string $code): ?Entity
    {
        return $this->where(['code' => $code])->findOne();
    }

    public function activate(string $id, bool $skipMatchingUpdate = false): void
    {
        if (!$skipMatchingUpdate) {
            $matching = $this->get($id);
            $matching->set('isActive', true);
            $this->getEntityManager()->saveEntity($matching);
        }

        $matchings = $this->getConfig()->get('matchings', []);
        $matchings[$id] = true;

        $this->getConfig()->set('matchings', $matchings);
        $this->getConfig()->save();
    }

    public function deactivate(string $id, bool $skipMatchingUpdate = false): void
    {
        if (!$skipMatchingUpdate) {
            $matching = $this->get($id);
            $matching->set('isActive', false);
            $this->getEntityManager()->saveEntity($matching);
        }

        $matchings = $this->getConfig()->get('matchings', []);
        $matchings[$id] = false;

        $this->getConfig()->set('matchings', $matchings);
        $this->getConfig()->save();

        $this->getEntityManager()->getRepository('Job')->cancelMatchingJobs($id);
    }

    protected function beforeSave(OrmEntity $entity, array $options = []): void
    {
        if ($entity->isAttributeChanged('entity') && $entity->get('type') === 'duplicate') {
            $entity->set('sourceEntity', $entity->get('entity'));
            $entity->set('masterEntity', $entity->get('entity'));
        }

        if ($entity->isAttributeChanged('name') && $entity->get('type') === 'masterRecord') {
            $entity->set('foreignName', $entity->get('name'));
        }

        parent::beforeSave($entity, $options);
    }

    protected function createMasterDataEntity(string $id): void
    {
        $mde = $this->getEntityManager()->getRepository('MasterDataEntity')->get($id);
        if (empty($mde)) {
            $mde = $this->getEntityManager()->getRepository('MasterDataEntity')->get();
            $mde->id = $id;
            $mde->set([
                'ownerUserId'    => $this->getEntityManager()->getUser()->id,
                'assignedUserId' => $this->getEntityManager()->getUser()->id,
            ]);
            $this->getEntityManager()->saveEntity($mde);
        }
    }

    /**
     * @param MatchingEntity $entity
     * @param array     $options
     *
     * @return void
     */
    protected function afterSave(OrmEntity $entity, array $options = []): void
    {
        parent::afterSave($entity, $options);

        if ($entity->isNew()) {
            if ($entity->get('type') === 'duplicate') {
                $this->createMasterDataEntity($entity->get('sourceEntity'));
            } elseif ($entity->get('type') === 'masterRecord') {
                $this->createMasterDataEntity($entity->get('sourceEntity'));
                $this->createMasterDataEntity($entity->get('masterEntity'));
            }

            $this->rebuild();
        }

        if ($entity->isAttributeChanged('isActive') && !$entity->isNew()) {
            if (!empty($entity->get('isActive'))) {
                $this->activate($entity->id, true);
            } else {
                $this->deactivate($entity->id, true);
            }
        }

        if ($entity->isAttributeChanged('minimumScore') || $entity->isAttributeChanged('isActive') || $entity->isAttributeChanged('matchedRecordsMax')) {
            if (!empty($entity->get('isActive'))) {
                $this->unmarkAllMatchingSearched($entity);
            }
        }
    }

    protected function deleteMasterDataEntity(MatchingEntity $matching, string $entityName): void
    {
        $exists = $this->where(['sourceEntity' => $entityName, 'id!=' => $matching->id])->findOne();
        if (!empty($exists)) {
            return;
        }

        $exists = $this->where(['masterEntity' => $entityName, 'id!=' => $matching->id])->findOne();
        if (!empty($exists)) {
            return;
        }

        $mde = $this->getEntityManager()->getRepository('MasterDataEntity')->get($entityName);
        if (!empty($mde)) {
            $this->getEntityManager()->removeEntity($mde);
        }
    }

    /**
     * @param MatchingEntity $entity
     * @param array     $options
     *
     * @return void
     */
    protected function afterRemove(OrmEntity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        if ($entity->get('type') === 'duplicate') {
            $this->deleteMasterDataEntity($entity, $entity->get('sourceEntity'));
        } elseif ($entity->get('type') === 'masterRecord') {
            $this->deleteMasterDataEntity($entity, $entity->get('sourceEntity'));
            $this->deleteMasterDataEntity($entity, $entity->get('masterEntity'));
        }

        foreach ($this->getEntityManager()->getRepository('MatchingRule')->find() as $rule) {
            if ($rule->get('matchingId') === $entity->get('id')) {
                $this->getEntityManager()->removeEntity($rule);
            }
        }
    }

    public function markMatchingSearched(MatchingEntity $matching, string $entityName, string $entityId): void
    {
        $conn = $this->getEntityManager()->getConnection();

        $conn->createQueryBuilder()
            ->update($conn->quoteIdentifier(Util::toUnderScore(lcfirst($entityName))))
            ->set(Util::toUnderScore(self::prepareFieldName($matching->id)), ':true')
            ->where('id = :id')
            ->setParameter('id', $entityId)
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->executeQuery();
    }

    public function isMatchingSearchedForStaging(MatchingEntity $matching, Entity $entity): bool
    {
        $conn = $this->getEntityManager()->getConnection();

        $column = Util::toUnderScore(self::prepareFieldName($matching->get('code')));

        $res = $conn->createQueryBuilder()
            ->select("id, $column as val")
            ->from($conn->quoteIdentifier(Util::toUnderScore(lcfirst($matching->get('entity')))))
            ->where('id=:id')
            ->setParameter('id', $entity->id)
            ->fetchAssociative();

        return !empty($res['val']);
    }

    public function unmarkAllMatchingSearched(MatchingEntity $matching): void
    {
        $conn = $this->getEntityManager()->getConnection();

        $column = Util::toUnderScore(self::prepareFieldName($matching->id));
        $conn->createQueryBuilder()
            ->update($conn->quoteIdentifier(Util::toUnderScore(lcfirst($matching->get('entity')))))
            ->set($column, ':false')
            ->where("$column = :true")
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->executeQuery();

        // it needs for immediate start of finding matched records
        $this->getMatchingManager()->createFindMatchesJob($matching);
    }

    public function unmarkMatchingSearchedForEntity(MatchingEntity $matching, Entity $entity): void
    {
        $conn = $this->getEntityManager()->getConnection();

        $column = Util::toUnderScore(self::prepareFieldName($matching->id));
        $conn->createQueryBuilder()
            ->update($conn->quoteIdentifier(Util::toUnderScore(lcfirst($entity->getEntityName()))))
            ->set($column, ':false')
            ->where('id = :id')
            ->setParameter('id', $entity->id)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->executeQuery();

        $jobEntity = $this->getEntityManager()->getEntity('Job');
        $jobEntity->set([
            'name'     => "Find matches for {$entity->getEntityName()}: {$entity->get('name')}",
            'type'     => 'FindMatchesForRecord',
            'status'   => 'Pending',
            'priority' => 25,
            'payload'  => [
                'matchingId' => $matching->id,
                'entityName' => $entity->getEntityName(),
                'entityId'   => $entity->id,
            ],
        ]);
        $this->getEntityManager()->saveEntity($jobEntity);
    }

    public function findPossibleMatchesForEntity(MatchingEntity $matching, Entity $entity): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $table = Util::toUnderScore(lcfirst($matching->get('masterEntity')));

        $alias = 'mt';

        $qb = $conn->createQueryBuilder();

        $qb
            ->select("{$alias}.*")
            ->from($conn->quoteIdentifier($table), $alias)
            ->where("{$alias}.deleted=:false")
            ->setParameter('false', false, ParameterType::BOOLEAN);

        if ($matching->get('masterEntity') === $matching->get('entity')) {
            $qb
                ->andWhere("{$alias}.id != :id")
                ->setParameter('id', $entity->get('id'));
        }
        $rulesParts = [];
        foreach ($matching->get('matchingRules') ?? [] as $rule) {
            $sqlPart = $rule->prepareMatchingSqlPart($qb, $entity);
            if (!empty($sqlPart)) {
                $rulesParts[] = $sqlPart;
            }
        }
        if (!empty($rulesParts)) {
            $qb->andWhere(implode(' OR ', $rulesParts));
        }

        return $qb->fetchAllAssociative();
    }

    protected function rebuild(): void
    {
        $jobEntity = $this->getEntityManager()->getEntity('Job');
        $jobEntity->set([
            'name'     => "Rebuild database",
            'type'     => 'Rebuild',
            'priority' => 800,
        ]);
        $this->getEntityManager()->saveEntity($jobEntity);
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
