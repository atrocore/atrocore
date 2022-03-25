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
    protected string $hierarchyTableName;

    public function __construct($entityType, EntityManager $entityManager, EntityFactory $entityFactory)
    {
        parent::__construct($entityType, $entityManager, $entityFactory);

        $this->hierarchyTableName = $entityManager->getQuery()->toDb($this->entityType) . '_hierarchy';
    }

    public function fetchById(string $id): array
    {
        $id = $this->getPDO()->quote($id);
        $tableName = $this->getEntityManager()->getQuery()->toDb($this->entityType);

        $result = $this
            ->getPDO()
            ->query("SELECT * FROM `$tableName` WHERE deleted=0 AND id=$id")
            ->fetch(\PDO::FETCH_ASSOC);

        return empty($result) ? [] : $result;
    }

    public function updatePositionInTree(string $entityId, string $position, string $target, string $parentId): void
    {
        // prepare vars
        $preparedEntityId = $this->getPDO()->quote($entityId);
        $preparedParentId = $this->getPDO()->quote($parentId);
        $tableName = $this->getEntityManager()->getQuery()->toDb($this->entityType);

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
                $this->getPDO()->exec("UPDATE `$tableName` SET sort_order=$sortOrder WHERE id='$id' AND deleted=0");
            } else {
                $this->getPDO()->exec("UPDATE `$this->hierarchyTableName` SET hierarchy_sort_order=$sortOrder WHERE entity_id='$id' AND deleted=0");
            }
        }
    }

    public function hasMultipleParents(): bool
    {
        $tableName = $this->getEntityManager()->getQuery()->toDb($this->entityType);

        $count = $this
            ->getPDO()
            ->query("SELECT COUNT(e.id) as total FROM (SELECT entity_id, deleted FROM `$this->hierarchyTableName` WHERE deleted=0 GROUP BY entity_id, deleted HAVING COUNT(entity_id) > 1) AS rel LEFT JOIN `$tableName` e ON e.id=rel.entity_id WHERE e.deleted=0")
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

    public function getChildrenArray(string $parentId, bool $withChildrenCount = true): array
    {
        $tableName = $this->getEntityManager()->getQuery()->toDb($this->entityType);

        $additionalSelect = $withChildrenCount ? ", (SELECT COUNT(id) FROM `$this->hierarchyTableName` WHERE parent_id=e.id) as childrenCount" : "";

        if (empty($parentId)) {
            $query = "SELECT e.*{$additionalSelect} 
                      FROM `{$tableName}` e
                      WHERE e.id NOT IN (SELECT entity_id FROM `$this->hierarchyTableName` WHERE deleted=0)
                      AND e.deleted=0
                      ORDER BY e.sort_order";
        } else {
            $parentId = $this->getPDO()->quote($parentId);
            $query = "SELECT e.*{$additionalSelect}
                  FROM `$this->hierarchyTableName` h
                  LEFT JOIN `{$tableName}` e ON e.id=h.entity_id
                  WHERE h.deleted=0
                    AND e.deleted=0
                    AND h.parent_id={$parentId}
                  ORDER BY h.hierarchy_sort_order";
        }

        return $this->getPDO()->query($query)->fetchAll(\PDO::FETCH_ASSOC);
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

    public function getRoute(string $id): array
    {
        $records = $this
            ->getPDO()
            ->query("SELECT entity_id, parent_id FROM `{$this->getHierarchyTableName()}` WHERE deleted=0")
            ->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($records)) {
            return [];
        }

        $route = [];
        $this->createRoute($records, $id, $route);

        return $route;
    }

    protected function beforeRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
        parent::beforeRelate($entity, $relationName, $foreign, $data, $options);

        if ($relationName === 'parents') {
            if (is_bool($foreign)) {
                throw new BadRequest("Action blocked. Please, specify {$this->entityType}.");
            }
            $foreignId = is_string($foreign) ? $foreign : $foreign->get('id');
            if (in_array($foreignId, $this->getChildrenRecursivelyArray($entity->get('id')))) {
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
            $foreignId = is_string($foreign) ? $foreign : $foreign->get('id');
            if (in_array($foreignId, $this->getParentsRecursivelyArray($entity->get('id')))) {
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

    protected function getHierarchyTableName(): string
    {
        return $this->getEntityManager()->getQuery()->toDb($this->entityType) . '_hierarchy';
    }

    protected function collectParents(string $id, array &$ids): void
    {
        $id = $this->getPDO()->quote($id);
        $query = "SELECT parent_id FROM `{$this->getHierarchyTableName()}` WHERE deleted=0 AND entity_id=$id";
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
        $query = "SELECT entity_id FROM `{$this->getHierarchyTableName()}` WHERE deleted=0 AND parent_id=$id";
        if (!empty($res = $this->getPDO()->query($query)->fetchAll(\PDO::FETCH_COLUMN))) {
            $ids = array_values(array_unique(array_merge($ids, $res)));
            foreach ($res as $v) {
                $this->collectChildren($v, $ids);
            }
        }
    }
}
