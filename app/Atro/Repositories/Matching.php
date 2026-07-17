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
        return "$entityName-C2M";
    }

    public static function prepareFieldName(string $code): string
    {
        $parts = explode('-', $code);

        return ucfirst($parts[0]) . ucfirst(strtolower($parts[1]));
    }

    public function activate(string $id): void
    {
        $matching = $this->get($id);
        $matching->set('isActive', true);
        $this->getEntityManager()->saveEntity($matching);
    }

    public function deactivate(string $id): void
    {
        $matching = $this->get($id);
        $matching->set('isActive', false);
        $this->getEntityManager()->saveEntity($matching);
    }

    protected function beforeSave(OrmEntity $entity, array $options = []): void
    {
        if ($entity->isAttributeChanged('entity') && $entity->get('type') === 'duplicate') {
            $entity->set('masterEntity', $entity->get('entity'));
        }

        if ($entity->get('type') === 'masterRecord' && ($entity->isNew() || $entity->isAttributeChanged('entity'))) {
            $scopeDefs = $this->getMetadata()->get("scopes.{$entity->get('entity')}") ?? [];
            if (empty($scopeDefs['primaryEntityId']) || ($scopeDefs['role'] ?? null) !== 'contributor') {
                throw new BadRequest(
                    sprintf(
                        $this->getLanguage()->translate('masterRecordEntityInvalid', 'exceptions', 'Matching'),
                        (string)$entity->get('entity')
                    )
                );
            }
        }

        if ($entity->isNew()) {
            if ($entity->get('type') === 'duplicate') {
                $entity->set('code', self::createCodeForDuplicate($entity->get('entity')));
                $entity->set('masterEntity', $entity->get('entity'));
            } elseif ($entity->get('type') === 'masterRecord') {
                $entity->set('code', self::createCodeForMasterRecord($entity->get('entity')));
                $entity->set('masterEntity', $this->getMetadata()->get("scopes.{$entity->get('entity')}.primaryEntityId"));
            }

            if (!empty($entity->get('code')) && !empty($this->where(['code' => $entity->get('code')])->findOne())) {
                throw new BadRequest(
                    sprintf(
                        $this->getLanguage()->translate('matchingAlreadyExists', 'exceptions', 'Matching'),
                        $this->getLanguage()->translateOption($entity->get('type'), 'type', 'Matching'),
                        $entity->get('entity')
                    )
                );
            }
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('isActive') && !empty($entity->get('isActive'))) {
            $rule = $this->getEntityManager()->getRepository('MatchingRule')
                ->where(['matchingId' => $entity->id])
                ->findOne();

            if (empty($rule)) {
                throw new BadRequest($this->getLanguage()->translate('noRules', 'exceptions', 'Matching'));
            }
        }

        parent::beforeSave($entity, $options);
    }

    /**
     * @param MatchingEntity $entity
     * @param array          $options
     *
     * @return void
     */
    protected function afterSave(OrmEntity $entity, array $options = []): void
    {
        parent::afterSave($entity, $options);

        if ($entity->isNew()) {
            $this->rebuild();
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('isActive')) {
            $this->getInjection('dataManager')->clearCache();

            if (empty($entity->get('isActive'))) {
                $this->getEntityManager()->getRepository('Job')->cancelMatchingJobs($entity->id);
            }
        }

        if ($entity->isAttributeChanged('minimumScore') || $entity->isAttributeChanged('isActive') || $entity->isAttributeChanged('matchedRecordsMax')) {
            if (!empty($entity->get('isActive'))) {
                $this->unmarkAllMatchingSearched($entity);
            }
        }
    }

    /**
     * @param MatchingEntity $entity
     * @param array          $options
     *
     * @return void
     */
    protected function afterRemove(OrmEntity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->getEntityManager()->getRepository('Job')->cancelMatchingJobs($entity->id);

        foreach ($entity->get('matchingRules') ?? [] as $rule) {
            $this->getEntityManager()->removeEntity($rule);
        }

        $this->rebuild();
    }

    public function markMatchingSearched(MatchingEntity $matching, string $entityName, string $entityId, string $matchedAt, bool $onlyIfAlreadySearched = false): void
    {
        $conn = $this->getDbal();
        $column = Util::toUnderScore(self::prepareFieldName($matching->get('code')));

        $qb = $conn->createQueryBuilder()
            ->update($conn->quoteIdentifier(Util::toUnderScore(lcfirst($entityName))))
            ->set($column, ':matchedAt')
            ->where('id = :id')
            ->setParameter('id', $entityId)
            ->setParameter('matchedAt', $matchedAt);

        if ($onlyIfAlreadySearched) {
            $qb->andWhere("$column IS NOT NULL");
        }

        $qb->executeQuery();
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

        $column = Util::toUnderScore(self::prepareFieldName($matching->get('code')));
        $conn->createQueryBuilder()
            ->update($conn->quoteIdentifier(Util::toUnderScore(lcfirst($matching->get('entity')))))
            ->set($column, ':null')
            ->where("$column IS NOT NULL")
            ->andWhere("deleted=:false")
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('null', null, ParameterType::NULL)
            ->executeQuery();
    }

    public function unmarkMatchingSearchedForEntity(MatchingEntity $matching, Entity $entity): void
    {
        $conn = $this->getEntityManager()->getConnection();

        $column = Util::toUnderScore(self::prepareFieldName($matching->get('code')));
        $conn->createQueryBuilder()
            ->update($conn->quoteIdentifier(Util::toUnderScore(lcfirst($entity->getEntityName()))))
            ->set($column, ':null')
            ->where('id = :id')
            ->setParameter('id', $entity->id)
            ->setParameter('null', null, ParameterType::NULL)
            ->executeQuery();

        $jobEntity = $this->getEntityManager()->getEntity('Job');
        $jobEntity->set([
            'name'     => "Find matches for {$entity->getEntityName()}: {$entity->get('name')}",
            'type'     => 'FindMatchesForRecord',
            'status'   => 'Pending',
            'priority' => 25,
            'payload'  => [
                'matching'   => $matching->toPayload(),
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

        return Util::arrayKeysToCamelCase($qb->fetchAllAssociative());
    }

    protected function rebuild(): void
    {
        $this->getInjection('dataManager')->rebuild();
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('dataManager');
    }
}
