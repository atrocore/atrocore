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
use Atro\Core\Exceptions\Error;
use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Atro\Core\Utils\Language;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Atro\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityFactory;
use Espo\ORM\EntityManager;

class Hierarchy extends Base
{
    protected string $tableName;
    protected string $hierarchyTableName;

    public function __construct($entityType, EntityManager $entityManager, EntityFactory $entityFactory)
    {
        parent::__construct($entityType, $entityManager, $entityFactory);

        $this->tableName = $entityManager->getMapper()->toDb($this->entityType);
        $this->hierarchyTableName = $this->tableName . '_hierarchy';
    }

    public function getRoutes(Entity $entity): array
    {
        $res = $entity->get('routes');
        if ($res === null) {
            $dbData = $this->getConnection()->createQueryBuilder()
                ->select('routes')
                ->from($this->tableName)
                ->where('id=:id')
                ->setParameter('id', $entity->get('id'))
                ->fetchAssociative();

            if (!empty($dbData) && $dbData['routes'] !== null) {
                $res = json_decode($dbData['routes'], true);
            } else {
                $res = $this->buildRoutes($entity->get('id'));
            }

            $entity->set('routes', $res);
        }

        $routes = [];
        foreach ($res as $route) {
            $part = explode("|", $route);
            array_pop($part);
            array_shift($part);
            $routes[] = $part;
        }

        return $routes;
    }

    /**
     * Build routes for entity and all its children.
     *
     * @param string $id
     * @return array
     */
    public function buildRoutes(string $id): array
    {
        // remove old routes
        $this->getConnection()->createQueryBuilder()
            ->update($this->tableName)
            ->set('routes', ':null')
            ->where('routes LIKE :like OR id = :id')
            ->setParameter('null', null, ParameterType::NULL)
            ->setParameter('like', "%|$id|%")
            ->setParameter('id', $id)
            ->executeQuery();

        $routes = $this->prepareRoutes($id);
        if ($routes === ['']) {
            $routes = [];
        }

        $this->getConnection()->createQueryBuilder()
            ->update($this->tableName)
            ->set('routes', ':routes')
            ->where('id = :id')
            ->setParameter('routes', json_encode($routes))
            ->setParameter('id', $id)
            ->executeQuery();

        // build routes for children
        $children = $this->getConnection()->createQueryBuilder()
            ->select('*')
            ->from($this->hierarchyTableName, 'h')
            ->where('h.parent_id=:id')
            ->setParameter('id', $id)
            ->fetchAllAssociative();

        foreach ($children as $child) {
            $this->buildRoutes($child['entity_id']);
        }

        return $routes;
    }

    public function findRelated(Entity $entity, $relationName, array $params = [])
    {
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

    public function getEntityPosition(Entity $entity, string $parentId, array $sortParams): ?int
    {
        $quotedTableName = $this->getConnection()->quoteIdentifier($this->tableName);
        $quotedHierarchyTableName = $this->getConnection()->quoteIdentifier($this->hierarchyTableName);

        if (!empty($sortParams) && !empty($sortParams['sortBy'])) {
            $primarySortBy = Util::toUnderScore($sortParams['sortBy']);
            $sortOrder = !empty($sortParams['asc']) ? 'ASC' : 'DESC';
        } else {
            $primarySortBy = 'sort_order';
            $sortOrder = (!empty($this->getMetadata()->get(['entityDefs', $this->entityType, 'collection', 'asc'])) ? 'ASC' : 'DESC');
        }

        $secondarySortBy = $this->getMetadata()->get(['entityDefs', $this->entityType, 'collection', 'sortBy']);
        $secondarySortBy = Util::toUnderScore(!empty($secondarySortBy) ? $secondarySortBy : 'name');

        if (Converter::isPgSQL($this->getConnection())) {
            if (empty($parentId)) {
                $query = "SELECT x.position
                      FROM (SELECT t.id, row_number() over(ORDER BY t.$primarySortBy $sortOrder, t.$secondarySortBy $sortOrder, t.id ASC) AS position
                            FROM $quotedTableName t
                            WHERE t.deleted=:deleted AND t.routes= :emptyRoutes) x
                      WHERE x.id= :id";
            } else {
                $primarySortBy = $primarySortBy === 'sort_order' ? 'h.hierarchy_sort_order' : 't.' . $primarySortBy;
                $query = "SELECT x.position
                      FROM (SELECT t.id, row_number() over(ORDER BY $primarySortBy $sortOrder, t.$secondarySortBy $sortOrder, t.id ASC) AS position
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
                            WHERE t.deleted=:deleted and t.routes= :emptyRoutes
                            ORDER BY t.$primarySortBy $sortOrder, t.$secondarySortBy $sortOrder, t.id ASC) x
                      JOIN (select id from product where id=:id) y ON x.id = y.id";
            } else {
                $primarySortBy = $primarySortBy === 'sort_order' ? 'h.hierarchy_sort_order' : 't.' . $primarySortBy;
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
                            ORDER BY $primarySortBy $sortOrder, t.$secondarySortBy $sortOrder, t.id ASC) x
                      JOIN (select id from product where id=:id) y ON x.id = y.id";
            }
        }

        $sth = $this->getEntityManager()->getPDO()->prepare($query);
        $sth->bindValue(':id', $entity->get('id'), \PDO::PARAM_STR);
        $sth->bindValue(':deleted', false, \PDO::PARAM_BOOL);

        if (!empty($parentId)) {
            $sth->bindValue(':parentId', $parentId, \PDO::PARAM_STR);
        } else {
            $sth->bindValue(':emptyRoutes', '[]', \PDO::PARAM_STR);
        }

        $sth->execute();

        $position = $sth->fetch(\PDO::FETCH_COLUMN);

        return (int)$position;
    }

    public function clearDeletedRecords(): void
    {
        if (empty($this->seed)) {
            return;
        }

        parent::clearDeletedRecords();

        $tableName = $this->getEntityManager()->getMapper()->toDb($this->entityName);

        foreach (['entity_id', 'parent_id'] as $column) {
            while (true) {
                $ids = $this->getConnection()->createQueryBuilder()
                    ->select('h.id')
                    ->from("{$tableName}_hierarchy", 'h')
                    ->leftJoin('h', $tableName, 't', "t.id=h.$column")
                    ->where("t.id IS NULL")
                    ->setFirstResult(0)
                    ->setMaxResults(10000)
                    ->fetchFirstColumn();

                if (empty($ids)) {
                    break;
                }

                $this->getConnection()->createQueryBuilder()
                    ->delete("{$tableName}_hierarchy")
                    ->where('id IN (:ids)')
                    ->setParameter('ids', $ids, $this->getConnection()::PARAM_STR_ARRAY)
                    ->executeQuery();
            }
        }
    }

    public function getInheritableFields(array $fieldsDefs = null): array
    {
        $unInheritableFields = $this->getUnInheritableFields();

        $fields = [];

        foreach ($fieldsDefs ?? $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields'], []) as $field => $fieldData) {
            if (in_array($field, $fields) || in_array($field, $unInheritableFields)) {
                continue;
            }

            if (!empty($fieldData['notStorable'])) {
                continue;
            }

            $fields[] = $field;
        }

        return $fields;
    }

    public function getUnInheritableFields(): array
    {
        $fields = array_merge($this->getMetadata()->get('app.nonInheritedFields', []), $this->getMetadata()->get(['scopes', $this->entityType, 'mandatoryUnInheritedFields'], []));
        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields'], []) as $field => $defs) {
            if (!empty($defs['inheritanceDisabled'])) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    public function getUnInheritableRelations(): array
    {
        $result = array_merge([], $this->getMetadata()->get('app.nonInheritedFields', []));
        $result = array_merge($result, $this->getMetadata()->get(['scopes', $this->entityType, 'mandatoryUnInheritedFields'], []));
        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields'], []) as $field => $defs) {
            if ($defs['type'] == 'linkMultiple' && !empty($defs['isUninheritableRelation'])) {
                $result[] = $field;
            }
        }

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

    public function updatePositionInTree(string $entityId, string $position, string $target, string $parentId, bool $sortAsc = true): void
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
        } elseif (!empty($entity)) {
            $this->getEntityManager()->removeEntity($entity, ['move' => true]);
        }

        $ids = array_column($this->getChildrenArray($parentId, false), 'id');
        unset($ids[array_search($entityId, $ids)]);
        $ids = array_values($ids);

        $sortedIds = [];
        if ($position === 'after') {
            foreach ($ids as $id) {
                if ($sortAsc) {
                    $sortedIds[] = $id;
                    if ($id === $target) {
                        $sortedIds[] = $entityId;
                    }
                } else {
                    if ($id === $target) {
                        $sortedIds[] = $entityId;
                    }
                    $sortedIds[] = $id;
                }
            }
        } elseif ($position === 'inside') {
            if ($sortAsc) {
                $sortedIds = array_merge([$entityId], $ids);
            } else {
                $sortedIds = array_merge($ids, [$entityId]);
            }
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
        $entity = $this->get($id);

        if (empty($entity)) {
            return [];
        }

        $res = [];
        foreach ($this->getRoutes($entity) as $ids) {
            $res = array_merge($res, $ids);
        }

        return $res;
    }

    public function getChildrenRecursivelyArray(string $id): array
    {
        return $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from($this->tableName)
            ->where('routes LIKE :like')
            ->setParameter('like', "%|$id|%")
            ->fetchFirstColumn();
    }

    public function getLeafChildren(string $id): array
    {
        $children = $this->getChildrenRecursivelyArray($id);
        $qb = $this->getConnection()->createQueryBuilder();
        $query = $qb->select('r.parent_id')
            ->distinct()
            ->from($this->hierarchyTableName, 'r')
            ->leftJoin('r', $this->tableName, 'm', 'r.entity_id = m.id')
            ->where('r.deleted = :false')
            ->andWhere('m.deleted = :false')
            ->andWhere($qb->expr()->in('r.parent_id', ':ids'))
            ->setParameter('ids', $children, Connection::PARAM_STR_ARRAY)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->executeQuery();

        $parentNodes = $query->fetchFirstColumn();

        return array_diff($children, $parentNodes);
    }

    public function getChildrenArray(string $parentId, bool $withChildrenCount = true, array $params = []): array
    {
        $countParams = json_decode(json_encode($params), true);
        $mapper = $this->getMapper();

        if (empty($parentId)) {
            $params['where'][] = [
                'attribute' => 'routes',
                'type'      => 'equals',
                'value'     => '[]'
            ];
        } else {
            $params['where'][] = [
                'attribute' => 'routes',
                'type'      => 'like',
                'value'     => '%|' . $parentId . '|"%'
            ];
        }

        $mtAlias = $this->getMapper()->getQueryConverter()->getMainTableAlias();
        $sp = $this->convertToSelectParams($params);
        $qb = $mapper->createSelectQueryBuilder($this->get(), $sp);

        $qb->select("$mtAlias.*");

        if (!empty($parentId)) {
            if (!empty($sp['orderBy'])) {
                $primarySortBy = Util::toUnderScore($sp['orderBy']);
            } else {
                $primarySortBy = 'sort_order';
            }

            $secondarySortBy = $this->getMetadata()->get(['entityDefs', $this->entityType, 'collection', 'sortBy']);
            $secondarySortBy = Util::toUnderScore(!empty($secondarySortBy) ? $secondarySortBy : 'name');
            $quotedHierarchyTableName = $this->getConnection()->quoteIdentifier($this->hierarchyTableName);

            $sortOrder = $selectParams['order'] ?? (!empty($this->getMetadata()->get(['entityDefs', $this->entityType, 'collection', 'asc'])) ? 'ASC' : 'DESC');
            $withDeleted = !empty($selectParams['withDeleted']) && $selectParams['withDeleted'] === true;

            $qb->addSelect('h.hierarchy_sort_order');
            $qb->leftJoin($mtAlias, $quotedHierarchyTableName, 'h', "h.entity_id = $mtAlias.id")
                ->andWhere('h.parent_id = :parentId')
                ->orderBy($primarySortBy === 'sort_order' ? 'h.hierarchy_sort_order' : "$mtAlias.$primarySortBy", $sortOrder)
                ->addOrderBy($secondarySortBy === 'sort_order' ? 'h.hierarchy_sort_order' : "$mtAlias.$secondarySortBy", $sortOrder)
                ->setParameter('parentId', $parentId);

            if (!$withDeleted) {
                $qb->andWhere('h.deleted = :deleted');
                $qb->setParameter('deleted', false, ParameterType::BOOLEAN);
            }
        }

        if ($withChildrenCount) {
            $sp = $this->convertToSelectParams($countParams);
            $sp['aggregation'] = 'COUNT';
            $sp['aggregationBy'] = 'id';
            $sp['skipBelongsToJoins'] = true;
            $selectCountQuery = $mapper->createSelectQueryBuilder($this->get(), $sp, true);
            $selectCountQuery->andWhere("{$mtAlias}.routes LIKE CONCAT('%|', mt_alias.id, '|\"%')");
            $selectCountQuery->select("COUNT({$mtAlias}.id)");

            $countSql = str_replace([$mtAlias, 'mt_alias'], [$mtAlias . '_count', 'main'], $selectCountQuery->getSQL());

            $mainQb = $qb;
            // use subquery to optimize performance with offset
            $qb = $this->getConnection()->createQueryBuilder()
                ->select("main.*")
                ->from('(' . $mainQb->getSQL() . ')', 'main');

            $qb->addSelect("($countSql) AS children_count");

            foreach ($selectCountQuery->getParameters() as $pName => $pValue) {
                $qb->setParameter($pName, $pValue, $mapper::getParameterType($pValue));
            }
            foreach ($mainQb->getParameters() as $pName => $pValue) {
                $qb->setParameter($pName, $pValue, $mapper->getParameterType($pValue));
            }
        }


        return Util::arrayKeysToCamelCase($qb->fetchAllAssociative());
    }

    public function getChildrenCount(string $parentId, array $params = []): int
    {
        if (empty($parentId)) {
            $params['where'][] = [
                'attribute' => 'routes',
                'type'      => 'equals',
                'value'     => '[]'
            ];
        } else {
            $params['where'][] = [
                'attribute' => 'routes',
                'type'      => 'like',
                'value'     => '%|' . $parentId . '|"%'
            ];
        }


        $sp = $this->convertToSelectParams($params);
        return $this->getMapper()->count($this->get(), $sp);
    }

    public function hasChildren(string $id): bool
    {
        $res = $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from($this->tableName)
            ->where('routes LIKE :like')
            ->setParameter('like', "%|$id|%")
            ->fetchAssociative();

        return !empty($res);
    }

    public function hasChildrenByIds(array $ids): array
    {
        $res = $this->getConnection()->createQueryBuilder()
            ->select('id, parent_id')
            ->from($this->hierarchyTableName)
            ->where('parent_id IN (:ids)')
            ->andWhere('deleted = :false')
            ->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        $result = [];
        foreach ($ids as $id) {
            $result[$id] = false;
        }

        foreach ($res as $record) {
            $result[$record['parent_id']] = true;
        }

        return $result;
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
        if ($this->getMetadata()->get(['scopes', $entity->getEntityType(), 'type']) !== 'Hierarchy') {
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
            if (!empty($foreign) && in_array($foreign->get('id'), $this->getChildrenRecursivelyArray($entity->get('id')))) {
                throw new BadRequest("Child record cannot be chosen as a parent.");
            }
        }

        if ($relationName === 'children') {
            if (is_bool($foreign)) {
                throw new BadRequest("Action blocked. Please, specify {$this->entityType}.");
            }
            $foreign = is_string($foreign) ? $this->get($foreign) : $foreign;
            if (!empty($foreign) && in_array($foreign->get('id'), $this->getParentsRecursivelyArray($entity->get('id')))) {
                throw new BadRequest("Parent record cannot be chosen as a child.");
            }
        }
    }

    protected function prepareRoutes(string $id): array
    {
        $parents = $this->getConnection()->createQueryBuilder()
            ->select('*')
            ->from($this->hierarchyTableName, 'h')
            ->innerJoin('h', $this->tableName, 't', 't.id=h.parent_id AND t.deleted = :false')
            ->where('h.entity_id=:id')
            ->andWhere('h.deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('id', $id)
            ->fetchAllAssociative();

        if (empty($parents)) {
            return [""];
        }

        $routes = [];

        foreach ($parents as $parent) {
            $parentRoutes = $this->prepareRoutes($parent['parent_id']);
            foreach ($parentRoutes as $route) {
                $routes[] = substr($route, 0, -1) . "|{$parent['parent_id']}|";
            }
        }

        return $routes;
    }

    public function convertToSelectParams($params): array
    {
        return $this->getInjection('selectManagerFactory')->create($this->entityType)->getSelectParams($params, true, true);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('serviceFactory');
        $this->addDependency('selectManagerFactory');;
    }
}