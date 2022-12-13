<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Espo\Core\Templates\Repositories;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\ORM\Repositories\RDB;
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

        $this->tableName = $entityManager->getQuery()->toDb($this->entityType);
        $this->hierarchyTableName = $this->tableName . '_hierarchy';
    }

    public function getUnInheritedFields(): array
    {
        $result = array_merge($this->getMetadata()->get('app.nonInheritedFields', []), $this->getMetadata()->get(['scopes', $this->entityType, 'mandatoryUnInheritedFields'], []));
        $result = array_merge($result, $this->getMetadata()->get(['scopes', $this->entityType, 'unInheritedFields'], []));

        // add relations
        $result = array_merge($result, $this->getUnInheritedRelations());

        return $result;
    }

    public function getUnInheritedRelations(): array
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

    public function fetchById(string $id): array
    {
        $result = $this
            ->getPDO()
            ->query("SELECT * FROM `$this->tableName` WHERE deleted=0 AND id={$this->getPDO()->quote($id)}")
            ->fetch(\PDO::FETCH_ASSOC);

        if (empty($result)) {
            return [];
        }

        $this->pushLinkMultipleFields($result);

        return $result;
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
                $result["{$field}_ids"] = array_column($entity->get($field)->toArray(), 'id');
                sort($result["{$field}_ids"]);
            }
        }
    }

    public function updatePositionInTree(string $entityId, string $position, string $target, string $parentId): void
    {
        // prepare vars
        $preparedEntityId = $this->getPDO()->quote($entityId);
        $preparedParentId = $this->getPDO()->quote($parentId);

        $this->getPDO()->exec("DELETE FROM `$this->hierarchyTableName` WHERE entity_id=$preparedEntityId");
        if (!empty($parentId)) {
            $this->getPDO()->exec("INSERT INTO `$this->hierarchyTableName` (entity_id, parent_id) VALUES ($preparedEntityId, $preparedParentId)");
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

        foreach ($sortedIds as $k => $id) {
            $sortOrder = $k * 10;
            if (empty($parentId)) {
                $this->getPDO()->exec("UPDATE `$this->tableName` SET sort_order=$sortOrder WHERE id='$id' AND deleted=0");
            } else {
                $this->getPDO()->exec("UPDATE `$this->hierarchyTableName` SET hierarchy_sort_order=$sortOrder WHERE entity_id='$id' AND deleted=0");
            }
        }
    }

    public function hasMultipleParents(): bool
    {
        $query = "SELECT COUNT(e.id) as total 
                  FROM (SELECT entity_id FROM `$this->hierarchyTableName` WHERE deleted=0 GROUP BY entity_id HAVING COUNT(entity_id) > 1) AS rel 
                  LEFT JOIN `$this->tableName` e ON e.id=rel.entity_id 
                  WHERE e.deleted=0";

        $count = $this
            ->getPDO()
            ->query($query)
            ->fetch(\PDO::FETCH_COLUMN);

        return !empty($count);
    }

    public function updateHierarchySortOrder(string $parentId, array $ids): void
    {
        $parentId = $this->getPDO()->quote($parentId);
        foreach ($ids as $k => $id) {
            $id = $this->getPDO()->quote($id);
            $sortOrder = $k * 10;
            $this->getPDO()->exec("UPDATE `$this->hierarchyTableName` SET hierarchy_sort_order=$sortOrder WHERE parent_id=$parentId AND entity_id=$id AND deleted=0");
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

    public function getChildrenArray(string $parentId, bool $withChildrenCount = true, int $offset = null, $maxSize = null): array
    {
        $select = 'e.*';
        if ($withChildrenCount) {
            $select .= ", (SELECT COUNT(r1.id) FROM `$this->hierarchyTableName` r1 JOIN `$this->tableName` e1 ON e1.id=r1.entity_id WHERE r1.parent_id=e.id AND e1.deleted=0) as childrenCount";
        }

        if (empty($parentId)) {
            $sortOrder = 'e.sort_order, e.id';
            if (empty($this->getMetadata()->get(['scopes', $this->entityType, 'dragAndDrop']))) {
                $sortOrder = 'e.' . $this->getEntityManager()->getQuery()->toDb($this->getMetadata()->get(['entityDefs', $this->entityType, 'collection', 'sortBy'], 'id'));
                if (empty($this->getMetadata()->get(['entityDefs', $this->entityType, 'collection', 'asc']))) {
                    $sortOrder .= ' DESC';
                }
            }
            $query = "SELECT {$select} 
                      FROM `$this->tableName` e
                      WHERE e.id NOT IN (SELECT entity_id FROM `$this->hierarchyTableName` WHERE deleted=0)
                      AND e.deleted=0
                      ORDER BY " . $sortOrder;
        } else {
            $sortOrder = 'h.hierarchy_sort_order, e.id';
            if (empty($this->getMetadata()->get(['scopes', $this->entityType, 'dragAndDrop']))) {
                $sortOrder = 'e.' . $this->getEntityManager()->getQuery()->toDb($this->getMetadata()->get(['entityDefs', $this->entityType, 'collection', 'sortBy'], 'id'));
                if (empty($this->getMetadata()->get(['entityDefs', $this->entityType, 'collection', 'asc']))) {
                    $sortOrder .= ' DESC';
                }
            }
            $parentId = $this->getPDO()->quote($parentId);
            $query = "SELECT {$select}
                  FROM `$this->hierarchyTableName` h
                  LEFT JOIN `$this->tableName` e ON e.id=h.entity_id
                  WHERE h.deleted=0
                    AND e.deleted=0
                    AND h.parent_id={$parentId}
                  ORDER BY " . $sortOrder;
        }

        if (!is_null($offset) && !is_null($maxSize)) {
            $query .= " LIMIT $maxSize OFFSET $offset";
        }

        return $this->getPDO()->query($query)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @return int
     */
    public function getChildrenCount(string $parentId): int
    {
        if (empty($parentId)) {
            $query = "SELECT COUNT(e.id) as count
                      FROM `$this->tableName` e
                      WHERE e.id NOT IN (SELECT entity_id FROM `$this->hierarchyTableName` WHERE deleted=0)
                      AND e.deleted=0";
        } else {
            $query = "SELECT COUNT(e.id) as count
                      FROM $this->tableName e
                      LEFT JOIN $this->hierarchyTableName h on e.id=h.entity_id
                      WHERE e.deleted=0
                        AND h.deleted=0
                        AND h.parent_id='$parentId'";
        }

        return (int)$this->getPDO()->query($query)->fetch(\PDO::FETCH_ASSOC)['count'];
    }

    public function isRoot(string $id): bool
    {
        $id = $this->getPDO()->quote($id);

        $query = "SELECT id
                  FROM `$this->hierarchyTableName`
                  WHERE deleted=0
                    AND entity_id={$id}";

        $record = $this->getPDO()->query($query)->fetch(\PDO::FETCH_COLUMN);

        return empty($record);
    }


    public function getHierarchyRoute(string $id): array
    {
        $route = [];
        while (!empty($record = $this->getParentRecord($id))) {
            $route[$record['id']] = $record['name'];
            $id = $record['id'];
        }

        return array_reverse($route);
    }

    public function getParentRecord(string $id): array
    {
        $id = $this->getPDO()->quote($id);

        $query = "SELECT t.*
                  FROM `$this->hierarchyTableName` h
                  LEFT JOIN `$this->tableName` t ON t.id=h.parent_id
                  WHERE h.deleted=0
                    AND t.deleted=0
                    AND h.entity_id={$id}";

        $record = $this->getPDO()->query($query)->fetch(\PDO::FETCH_ASSOC);

        return empty($record) ? [] : $record;
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

        parent::beforeSave($entity, $options);
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

            if (empty($this->getMetadata()->get(['scopes', $this->entityType, 'multiParents']))) {
                $parents = $entity->get('parents');
                if (!empty($parents) && count($parents) > 0) {
                    foreach ($parents as $parent) {
                        $this->unrelate($entity, 'parents', $parent);
                    }
                }
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

            if (empty($this->getMetadata()->get(['scopes', $this->entityType, 'multiParents']))) {
                $parents = $foreign->get('parents');
                if (!empty($parents) && count($parents) > 0) {
                    foreach ($parents as $parent) {
                        $this->unrelate($foreign, 'parents', $parent);
                    }
                }
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
        $id = $this->getPDO()->quote($id);
        $query = "SELECT r.parent_id FROM `$this->hierarchyTableName` r LEFT JOIN `$this->tableName` m ON r.parent_id=m.id WHERE r.deleted=0 AND r.entity_id=$id AND m.deleted=0";
        if (!empty($res = $this->getPDO()->query($query)->fetchAll(\PDO::FETCH_COLUMN))) {
            $ids = array_values(array_unique(array_merge($ids, $res)));
            foreach ($res as $v) {
                $this->collectParents($v, $ids);
            }
        }
    }

    protected function collectChildren(string $id, array &$ids): void
    {
        $id = $this->getPDO()->quote($id);
        $query = "SELECT r.entity_id FROM `$this->hierarchyTableName` r LEFT JOIN `$this->tableName` m ON r.entity_id=m.id WHERE r.deleted=0 AND r.parent_id=$id AND m.deleted=0";
        if (!empty($res = $this->getPDO()->query($query)->fetchAll(\PDO::FETCH_COLUMN))) {
            $ids = array_values(array_unique(array_merge($ids, $res)));
            foreach ($res as $v) {
                $this->collectChildren($v, $ids);
            }
        }
    }
}
