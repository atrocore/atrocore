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

namespace Atro\Core\Templates\Repositories;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\ORM\Repositories\RDB;
use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityFactory;
use Espo\ORM\EntityManager;

class Hierarchy extends RDB
{
    protected string $tableName;
    protected string $hierarchyTableName;

    public function __construct($entityType, EntityManager $entityManager, EntityFactory $entityFactory)
    {
        parent::__construct($entityType, $entityManager, $entityFactory);

        $this->tableName = $entityManager->getMapper()->toDb($this->entityType);
        $this->hierarchyTableName = $this->tableName . '_hierarchy';
    }

    public function findRelated(Entity $entity, $relationName, array $params = [])
    {
        if ($this->getMetadata()->get(['scopes', $this->entityType, 'disableHierarchy'], false)) {
            return parent::findRelated($entity, $relationName, $params);
        }

        if ($relationName === 'children') {
            $params['orderBy'] = $this->hierarchyTableName . '_mm.hierarchy_sort_order';
            $params['order'] = "ASC";
        }

        return parent::findRelated($entity, $relationName, $params);
    }

    protected function beforeRestore($id)
    {
        parent::beforeRestore($id);

        $res = $this->getConnection()->createQueryBuilder()
            ->select('h.parent_id, t.deleted')
            ->from($this->hierarchyTableName, 'h')
            ->leftJoin('h', $this->tableName, 't', 't.id=h.parent_id')
            ->where('h.entity_id=:id')
            ->setParameter('id', $id)
            ->fetchAssociative();

        if (!empty($res['parent_id']) && !empty($res['deleted'])) {
            if (!empty($this->getMetadata()->get(['scopes', $this->entityType, 'multiParents']))) {
                throw new BadRequest('Restore prohibited for entity with possible multiple parents.');
            }
            $this->getInjection('serviceFactory')->create($this->entityType)->restoreEntity($res['parent_id']);
        }
    }

    public function getEntityPosition(Entity $entity, string $parentId): ?int
    {
        $quotedTableName = $this->getConnection()->quoteIdentifier($this->tableName);
        $quotedHierarchyTableName = $this->getConnection()->quoteIdentifier($this->hierarchyTableName);

        $sortBy = Util::toUnderScore($this->getMetadata()->get(['entityDefs', $this->entityType, 'collection', 'sortBy'], 'name'));
        $sortOrder = !empty($this->getMetadata()->get(['entityDefs', $this->entityType, 'collection', 'asc'])) ? 'ASC' : 'DESC';
        if (Converter::isPgSQL($this->getConnection())) {
            if (empty($parentId)) {
                $query = "SELECT x.position
                      FROM (SELECT t.id, row_number() over(ORDER BY t.sort_order ASC, t.$sortBy $sortOrder, t.id ASC) AS position
                            FROM $quotedTableName t
                            LEFT JOIN $quotedHierarchyTableName h ON t.id=h.entity_id AND h.deleted=:deleted
                            WHERE t.deleted=:deleted AND h.entity_id IS NULL) x
                      WHERE x.id= :id";
            } else {
                $query = "SELECT x.position
                      FROM (SELECT t.id, row_number() over(ORDER BY h.hierarchy_sort_order ASC, t.$sortBy $sortOrder, t.id ASC) AS position
                            FROM $quotedHierarchyTableName h
                                LEFT JOIN $quotedTableName t ON t.id=h.entity_id
                                LEFT JOIN $quotedTableName t1 ON t1.id=h.parent_id
                            WHERE h.parent_id=:parentId AND h.deleted=:deleted AND t.deleted=:deleted AND t1.deleted=:deleted) x
                      WHERE x.id=:id";
            }
        } else {
            if (empty($parentId)) {
                $query = "SELECT x.position
                      FROM (SELECT t.id, @rownum:=@rownum + 1 AS position
                            FROM $quotedTableName t
                                JOIN (SELECT @rownum:=0) r
                                LEFT JOIN $quotedHierarchyTableName h ON t.id=h.entity_id AND h.deleted=:deleted
                            WHERE t.deleted=:deleted
                              AND h.entity_id IS NULL
                            ORDER BY t.sort_order ASC, t.$sortBy $sortOrder, t.id ASC) x
                      JOIN (select id from product where id=:id) y ON x.id = y.id";
            } else {
                $query = "SELECT x.position
                      FROM (SELECT t.id, @rownum:=@rownum + 1 AS position
                            FROM $quotedHierarchyTableName h
                                JOIN (SELECT @rownum:=0) r
                                LEFT JOIN $quotedTableName t ON t.id=h.entity_id
                                LEFT JOIN $quotedTableName t1 ON t1.id=h.parent_id
                            WHERE h.parent_id=:parentId
                              AND h.deleted=:deleted
                              AND t.deleted=:deleted
                              AND t1.deleted=:deleted
                            ORDER BY h.hierarchy_sort_order ASC, t.$sortBy $sortOrder, t.id ASC) x
                      JOIN (select id from product where id=:id) y ON x.id = y.id";
            }
        }

        $sth = $this->getEntityManager()->getPDO()->prepare($query);
        $sth->bindValue(':id', $entity->get('id'), \PDO::PARAM_STR);
        $sth->bindValue(':deleted', false, \PDO::PARAM_BOOL);
        if (!empty($parentId)) {
            $sth->bindValue(':parentId', $parentId, \PDO::PARAM_STR);
        }
        $sth->execute();

        $position = $sth->fetch(\PDO::FETCH_COLUMN);

        return (int)$position;
    }

    public function getInheritableFields(): array
    {
        $unInheritableFields = $this->getUnInheritableFields();

        $fields = [];

        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields'], []) as $field => $fieldData) {
            if (in_array($field, $fields) || in_array($field, $unInheritableFields)) {
                continue 1;
            }

            if (!empty($fieldData['notStorable'])) {
                continue 1;
            }

            $fields[] = $field;
        }

        return $fields;
    }

    public function getUnInheritableFields(): array
    {
        $fields = array_merge($this->getMetadata()->get('app.nonInheritedFields', []), $this->getMetadata()->get(['scopes', $this->entityType, 'mandatoryUnInheritedFields'], []));
        $fields = array_merge($fields, $this->getMetadata()->get(['scopes', $this->entityType, 'unInheritedFields'], []));

        // add relations
        $fields = array_merge($fields, $this->getUnInheritedRelations());

        return $fields;
    }

    public function getUnInheritableRelations(): array
    {
        $result = array_merge([], $this->getMetadata()->get('app.nonInheritedRelations', []));
        $result = array_merge($result, $this->getMetadata()->get(['scopes', $this->entityType, 'mandatoryUnInheritedRelations'], []));
        $result = array_merge($result, $this->getMetadata()->get(['scopes', $this->entityType, 'unInheritedRelations'], []));

        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'links'], []) as $link => $linkDefs) {
            if (!empty($linkDefs['type']) && $linkDefs['type'] === 'hasMany') {
                if (empty($linkDefs['relationName'])) {
                    $result[] = $link;
                }
            }
        }

        return $result;
    }

    /**
     * @deprecated use getUnInheritableFields instead
     */
    public function getUnInheritedFields(): array
    {
        return $this->getUnInheritableFields();
    }

    /**
     * @deprecated use getUnInheritableRelations instead
     */
    public function getUnInheritedRelations(): array
    {
        return $this->getUnInheritableRelations();
    }

    public function pushLinkMultipleFields(array &$result): void
    {
        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields']) as $field => $fieldData) {
            if (
                array_key_exists('type', $fieldData)
                && $fieldData['type'] === 'linkMultiple'
                && array_key_exists('noLoad', $fieldData)
                && $fieldData['noLoad'] === false
                && !in_array($field, $this->getUnInheritedFields())
            ) {
                if (empty($entity)) {
                    $entity = $this->get($result['id']);
                }
                $result["{$field}_ids"] = $entity->getLinkMultipleIdList($field);
                sort($result["{$field}_ids"]);
            }
        }
    }

    public function updatePositionInTree(string $entityId, string $position, string $target, string $parentId): void
    {
        /** @var Relation $relationRepository */
        $relationRepository = $this->getEntityManager()->getRepository(ucfirst(Util::toCamelCase($this->hierarchyTableName)));

        $entity = $relationRepository->where(['entityId' => $entityId])->findOne();

        if (!empty($parentId)) {
            if (empty($entity)) {
                $entity = $relationRepository->get();
                $entity->set('entityId', $entityId);
            }
            $entity->set('parentId', $parentId);
            $this->getEntityManager()->saveEntity($entity);
        } else {
            $this->getEntityManager()->removeEntity($entity, ['move' => true]);
        }

        $ids = array_column($this->getChildrenArray($parentId, false), 'id');
        unset($ids[array_search($entityId, $ids)]);
        $ids = array_values($ids);

        $sortedIds = [];
        if ($position === 'after') {
            foreach ($ids as $id) {
                $sortedIds[] = $id;
                if ($id === $target) {
                    $sortedIds[] = $entityId;
                }
            }
        } elseif ($position === 'inside') {
            $sortedIds = array_merge([$entityId], $ids);
        }

        $collection = [];
        if (empty($parentId)) {
            $field = 'sortOrder';
            foreach ($this->where(['id' => $sortedIds])->find() as $v) {
                $collection[$v->get('id')] = $v;
            }
        } else {
            $field = 'hierarchySortOrder';
            foreach ($relationRepository->where(['entityId' => $sortedIds])->find() as $v) {
                $collection[$v->get('entityId')] = $v;
            }
        }

        foreach ($sortedIds as $k => $id) {
            $sortOrder = $k * 10;
            $entity = $collection[$id];
            $entity->set($field, $sortOrder);
            $this->getEntityManager()->saveEntity($entity);
        }
    }

    public function hasMultipleParents(): bool
    {
        $quotedTableName = $this->getConnection()->quoteIdentifier($this->tableName);
        $quotedHierarchyTableName = $this->getConnection()->quoteIdentifier($this->hierarchyTableName);

        $query = "SELECT COUNT(e.id) as total
                  FROM (SELECT entity_id FROM $quotedHierarchyTableName WHERE deleted=:deleted GROUP BY entity_id HAVING COUNT(entity_id) > 1) AS rel
                  LEFT JOIN $quotedTableName e ON e.id=rel.entity_id
                  WHERE e.deleted=:deleted";

        $sth = $this->getEntityManager()->getPDO()->prepare($query);
        $sth->bindValue(':deleted', false, \PDO::PARAM_BOOL);
        $sth->execute();

        $count = $sth->fetch(\PDO::FETCH_COLUMN);

        return !empty($count);
    }

    public function updateHierarchySortOrder(string $parentId, array $ids): void
    {
        /** @var Relation $relationRepository */
        $relationRepository = $this->getEntityManager()->getRepository(ucfirst(Util::toCamelCase($this->hierarchyTableName)));

        $collection = [];
        foreach ($relationRepository->where(['parentId' => $parentId, 'entityId' => $ids])->find() as $v) {
            $collection[$v->get('entityId')] = $v;
        }

        foreach ($ids as $k => $id) {
            $sortOrder = $k * 10;
            $entity = $collection[$id];
            $entity->set('hierarchySortOrder', $sortOrder);
            $this->getEntityManager()->saveEntity($entity);
        }
    }

    public function getParentsRecursivelyArray(string $id): array
    {
        $ids = [];
        $this->collectParents($id, $ids);

        return $ids;
    }

    public function getChildrenRecursivelyArray(string $id): array
    {
        $ids = [];
        $this->collectChildren($id, $ids);

        return $ids;
    }

    public function getChildrenArray(string $parentId, bool $withChildrenCount = true, int $offset = null, $maxSize = null, $selectParams = null): array
    {
        $quotedTableName = $this->getConnection()->quoteIdentifier($this->tableName);
        $quotedHierarchyTableName = $this->getConnection()->quoteIdentifier($this->hierarchyTableName);
        $sortBy = Util::toUnderScore($this->getMetadata()->get(['entityDefs', $this->entityType, 'collection', 'sortBy'], 'name'));
        $sortOrder = !empty($this->getMetadata()->get(['entityDefs', $this->entityType, 'collection', 'asc'])) ? 'ASC' : 'DESC';
        $withDeleted = !empty($selectParams['withDeleted']) && $selectParams['withDeleted'] === true;
        $selectCountQuery = $this->getConnection()->createQueryBuilder()
            ->from($quotedHierarchyTableName, 'r1')
            ->select('COUNT(r1.id)')
            ->join('r1', $quotedTableName, 'e1', 'e1.id = r1.entity_id');

        if (!$withDeleted) {
            $selectCountQuery->andWhere('e1.deleted = :deleted');
        }
        if ($selectParams) {
            return $this->getChildrenArrayUsingSelectParams($selectParams, $parentId, $selectCountQuery);
        }

        $expr = $this->getConnection()->createExpressionBuilder();

        $qb = $this->getConnection()
            ->createQueryBuilder()
            ->from($quotedTableName, 'e');

        if ($withChildrenCount) {
            $qb->select('e.*', "({$selectCountQuery->where('e.id = r1.parent_id')->getSQL()}) as children_count");
        } else {
            $qb->select('e.*');
        }

        if (!$withDeleted) {
            $qb->where('e.deleted = :deleted');
        }

        if (empty($parentId)) {
            $qb->andWhere(
                $expr->notIn(
                    'e.id',
                    "SELECT entity_id FROM $quotedHierarchyTableName qh WHERE qh.deleted = :deleted"
                )
            )
                ->orderBy('e.sort_order')
                ->addOrderBy("e.$sortBy", $sortOrder);
        } else {
            $qb->leftJoin('e', $quotedHierarchyTableName, 'h', 'h.entity_id = e.id')
                ->andWhere('h.parent_id = :parentId')
                ->orderBy('h.hierarchy_sort_order')
                ->addOrderBy("e.$sortBy", $sortOrder);

            if (!$withDeleted) {
                $qb->andWhere('h.deleted = :deleted');
            }
        }

        if (!is_null($offset) && !is_null($maxSize)) {
            $qb->setFirstResult($offset);
            $qb->setMaxResults($maxSize);
        }

        $qb->setParameter('deleted', false, ParameterType::BOOLEAN);

        if (!empty($parentId)) {
            $qb->setParameter('parentId', $parentId);
        }

        return Util::arrayKeysToCamelCase($qb->fetchAllAssociative());
    }

    public function getChildrenCount(string $parentId, array $selectParams = null): int
    {
        $quotedTableName = $this->getConnection()->quoteIdentifier($this->tableName);
        $quotedHierarchyTableName = $this->getConnection()->quoteIdentifier($this->hierarchyTableName);
        $withDeleted = !empty($selectParams['withDeleted']) && $selectParams['withDeleted'] === true;

        if ($selectParams) {
            return $this->getChildrenCountUsingSelectParams($selectParams, $parentId);
        }

        $expr = $this->getConnection()->createExpressionBuilder();
        $qb = $this->getConnection()
            ->createQueryBuilder()
            ->from($quotedTableName, 'e')
            ->select('COUNT(e.id) as count');

        if (!$withDeleted) {
            $qb->where('e.deleted = :deleted');
        }

        if (empty($parentId)) {
            $qb->where(
                $expr->notIn(
                    'e.id',
                    "SELECT entity_id FROM $quotedHierarchyTableName qh WHERE qh.deleted = :deleted"
                )
            );
        } else {
            $qb->leftJoin('e', $quotedHierarchyTableName, 'h', 'h.entity_id = e.id')
                ->andWhere('h.deleted = :deleted')
                ->andWhere('h.parent_id = :parentId');
        }

        $qb->setParameter('deleted', false, ParameterType::BOOLEAN);

        if (!empty($parentId)) {
            $qb->setParameter('parentId', $parentId);
        }

        return (int)$qb->fetchAssociative()['count'];
    }

    public function isRoot(string $id, array $roots = null): bool
    {
        if ($roots === null) {
            $roots = $this->getEntitiesParents([$id]);
        }

        return empty($roots[$id]);
    }

    public function hasChildren(string $id, array $children = null): bool
    {
        if ($children === null) {
            $children = $this->getEntitiesChildren([$id]);
        }

        return !empty($children[$id]);
    }

    public function getEntitiesParents(array $ids): array
    {
        $records = $this->getConnection()->createQueryBuilder()
            ->select('h.entity_id, h.parent_id')
            ->from($this->hierarchyTableName, 'h')
            ->where('h.deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->andWhere('h.entity_id IN (:entityIds)')
            ->setParameter('entityIds', $ids, Connection::PARAM_STR_ARRAY)
            ->fetchAllAssociative();

        $result = [];
        foreach ($records as $record) {
            $result[$record['entity_id']][] = $record['parent_id'];
        }

        return $result;
    }

    public function getEntitiesChildren(array $ids): array
    {
        $records = $this->getConnection()->createQueryBuilder()
            ->select('h.entity_id, h.parent_id')
            ->from($this->hierarchyTableName, 'h')
            ->where('h.deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->andWhere('h.parent_id IN (:entityIds)')
            ->setParameter('entityIds', $ids, Connection::PARAM_STR_ARRAY)
            ->fetchAllAssociative();

        $result = [];
        foreach ($records as $record) {
            $result[$record['parent_id']][] = $record['entity_id'];
        }

        return $result;
    }

    public function getHierarchyRoute(string $id): array
    {
        $route = [];
        while (!empty($record = $this->getParentRecord($id))) {
            $route[$record['id']] = $record['name'];
            $id = $record['id'];
        }

        return array_reverse($route, true);
    }

    public function getParentRecord(string $id): array
    {
        $record = $this->getConnection()->createQueryBuilder()
            ->select('t.*')
            ->from($this->hierarchyTableName, 'h')
            ->leftJoin('h', $this->tableName, 't', 't.id = h.parent_id')
            ->where('h.deleted = :false')
            ->andWhere('t.deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->andWhere('h.entity_id = :entityId')
            ->setParameter('entityId', $id)
            ->fetchAssociative();

        return empty($record) ? [] : $record;
    }

    protected function entityHasArchive(Entity $entity): bool
    {
        return !empty($this->getMetadata()->get(['scopes', $entity->getEntityType(), 'hasArchive']));
    }


    protected function validateIsArchived(Entity $entity): void
    {
        $fieldName = 'isArchived';
        if ($entity->isAttributeChanged($fieldName) && $entity->get($fieldName) == true) {
            // search all childs
            $hasNonArchivedChildren = false;
            foreach ($entity->get('children') as $child) {
                if ($child->get('isArchived') == false) {
                    $hasNonArchivedChildren = true;
                    break;
                }
            }

            if ($hasNonArchivedChildren) {
                $language = $this->getLanguage();
                throw new BadRequest(
                    sprintf($language->translate('childsMustBeArchived', 'exceptions', 'Global'), $language->translate($fieldName, 'fields', $entity->getEntityType()))
                );
            }
        }
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if (!empty($entity->get('parentsIds'))) {
            foreach ($entity->get('parentsIds') as $parentId) {
                $ids = array_merge($this->getParentsRecursivelyArray($parentId), [$parentId]);
                if (in_array($entity->get('id'), $ids)) {
                    throw new BadRequest("Child record cannot be chosen as a parent.");
                }
            }
        }

        if (!empty($entity->get('childrenIds'))) {
            foreach ($entity->get('childrenIds') as $childId) {
                $ids = array_merge($this->getChildrenRecursivelyArray($childId), [$childId]);
                if (in_array($entity->get('id'), $ids)) {
                    throw new BadRequest("Parent record cannot be chosen as a child.");
                }
            }
        }

        if ($this->entityHasArchive($entity)) {
            $this->validateIsArchived($entity);
        }

        $this->prepareSortOrder($entity);

        parent::beforeSave($entity, $options);
    }

    protected function prepareSortOrder(Entity $entity): void
    {
        if ($this->getMetadata()->get(['scopes', $entity->getEntityType(), 'type']) !== 'Hierarchy'
            || $this->getMetadata()->get(['scopes', $this->entityType, 'disableHierarchy'], false)
        ) {
            return;
        }

        if ($entity->get('sortOrder') === null) {
            $entity->set('sortOrder', time() - (new \DateTime('2023-01-01'))->getTimestamp());
        }
    }

    protected function beforeRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
        parent::beforeRelate($entity, $relationName, $foreign, $data, $options);

        if ($relationName === 'parents') {
            if (is_bool($foreign)) {
                throw new BadRequest("Action blocked. Please, specify {$this->entityType}.");
            }
            $foreign = is_string($foreign) ? $this->get($foreign) : $foreign;
            if (in_array($foreign->get('id'), $this->getChildrenRecursivelyArray($entity->get('id')))) {
                throw new BadRequest("Child record cannot be chosen as a parent.");
            }
        }

        if ($relationName === 'children') {
            if (is_bool($foreign)) {
                throw new BadRequest("Action blocked. Please, specify {$this->entityType}.");
            }
            $foreign = is_string($foreign) ? $this->get($foreign) : $foreign;
            if (in_array($foreign->get('id'), $this->getParentsRecursivelyArray($entity->get('id')))) {
                throw new BadRequest("Parent record cannot be chosen as a child.");
            }
        }
    }

    protected function createRoute(array $records, string $id, array &$route): void
    {
        foreach ($records as $record) {
            if ($record['entity_id'] === $id) {
                $route[] = $record['parent_id'];
                $this->createRoute($records, $record['parent_id'], $route);
            }
        }
    }

    protected function collectParents(string $id, array &$ids): void
    {
        $res = $this->getConnection()->createQueryBuilder()
            ->select('r.parent_id')
            ->from($this->hierarchyTableName, 'r')
            ->leftJoin('r', $this->tableName, 'm', 'r.parent_id = m.id')
            ->where('r.deleted = :false')
            ->andWhere('m.deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->andWhere('r.entity_id = :entityId')
            ->setParameter('entityId', $id)
            ->fetchAllAssociative();

        if (!empty($res)) {
            $res = array_column($res, 'parent_id');
            $ids = array_values(array_unique(array_merge($ids, $res)));
            foreach ($res as $v) {
                $this->collectParents($v, $ids);
            }
        }
    }

    protected function collectChildren(string $id, array &$ids): void
    {
        $res = $this->getConnection()->createQueryBuilder()
            ->select('r.entity_id')
            ->from($this->hierarchyTableName, 'r')
            ->leftJoin('r', $this->tableName, 'm', 'r.entity_id = m.id')
            ->where('r.deleted = :false')
            ->andWhere('m.deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->andWhere('r.parent_id = :parentId')
            ->setParameter('parentId', $id)
            ->fetchAllAssociative();

        if (!empty($res)) {
            $res = array_column($res, 'entity_id');
            $ids = array_values(array_unique(array_merge($ids, $res)));
            foreach ($res as $v) {
                $this->collectChildren($v, $ids);
            }
        }
    }

    protected function getChildrenArrayUsingSelectParams(array $selectParams, string $parentId, QueryBuilder $selectCountQuery)
    {

        $quotedHierarchyTableName = $this->getConnection()->quoteIdentifier($this->hierarchyTableName);
        $sortBy = Util::toUnderScore($this->getMetadata()->get(['entityDefs', $this->entityType, 'collection', 'sortBy'], 'name'));
        $sortOrder = !empty($this->getMetadata()->get(['entityDefs', $this->entityType, 'collection', 'asc'])) ? 'ASC' : 'DESC';
        $withDeleted = !empty($selectParams['withDeleted']) && $selectParams['withDeleted'] === true;

        $selectParams['callbacks'][] = function (QueryBuilder $qb, $entity, $params, Mapper $mapper)
        use ($quotedHierarchyTableName, $parentId, $sortOrder, $sortBy, $selectCountQuery, $withDeleted) {
            $expr = $this->getConnection()->createExpressionBuilder();
            $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();
            $subQuery = clone $qb;

            $subQuery->setFirstResult(0);
            $subQuery->setMaxResults(null);
            $selectCountQuery
                ->andWhere($expr->in("$tableAlias.id", $subQuery->select("$tableAlias.id")->getSQL()));

            $qb->select("$tableAlias.*", "({$selectCountQuery->where("$tableAlias.id = r1.parent_id ".($withDeleted?"":"and r1.deleted=:deleted"))->getSQL()}) as children_count");
            if (!$withDeleted) {
                $qb->setParameter('deleled', false, ParameterType::BOOLEAN);
            }

            if (empty($parentId)) {

                $qb->andWhere(
                    $expr->notIn(
                        "$tableAlias.id",
                        "SELECT entity_id FROM $quotedHierarchyTableName qh" . (!$withDeleted ? " WHERE qh.deleted = :deleted" : "")
                    )
                )
                    ->orderBy("$tableAlias.sort_order")
                    ->addOrderBy("$tableAlias.$sortBy", $sortOrder)
                    ->addOrderBy("$tableAlias.id");
            } else {
                $qb->addSelect('h.hierarchy_sort_order');
                $qb->leftJoin($tableAlias, $quotedHierarchyTableName, 'h', "h.entity_id = $tableAlias.id")
                    ->andWhere('h.parent_id = :parentId')
                    ->orderBy('h.hierarchy_sort_order')
                    ->setParameter('parentId', $parentId);

                if (!$withDeleted) {
                    $qb->andWhere('h.deleted = :deleted');
                }
            }
            if (!$withDeleted) {
                $qb->setParameter('deleted', false, ParameterType::BOOLEAN);
            }
        };
        $result = $this->getMapper()->select($this->entityFactory->create($this->entityName), $selectParams);

        return Util::arrayKeysToCamelCase($result);
    }

    protected function getChildrenCountUsingSelectParams(array $selectParams, string $parentId): int
    {
        $quotedHierarchyTableName = $this->getConnection()->quoteIdentifier($this->hierarchyTableName);
        $withDeleted = !empty($selectParams['withDeleted']) && $selectParams['withDeleted'] === true;

        $selectParams['callbacks'][] = function (QueryBuilder $qb, $entity, $params, Mapper $mapper)
        use ($quotedHierarchyTableName, $parentId, $withDeleted) {
            $expr = $this->getConnection()->createExpressionBuilder();
            $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();

            if (empty($parentId)) {
                $qb->andWhere(
                    $expr->notIn(
                        "$tableAlias.id",
                        "SELECT entity_id FROM $quotedHierarchyTableName qh WHERE qh.deleted = :deleted"
                    )
                );
            } else {
                $qb->leftJoin($tableAlias, $quotedHierarchyTableName, 'h', "h.entity_id = $tableAlias.id")
                    ->andWhere('h.parent_id = :parentId')
                    ->setParameter('parentId', $parentId);

                if (!$withDeleted) {
                    $qb->andWhere('h.deleted = :deleted');
                }
            }
            if (!$withDeleted) {
                $qb->setParameter('deleted', false, ParameterType::BOOLEAN);
            }
        };
        $result = $this->getMapper()->count($this->entityFactory->create($this->entityName), $selectParams);

        return $result;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('serviceFactory');
    }
}