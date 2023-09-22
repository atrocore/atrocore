<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Core\Templates\Repositories;

use Espo\Core\Exceptions\Error;
use Espo\Core\ORM\Repositories\RDB;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class Relationship extends RDB
{
    public const SYSTEM_FIELDS = ['id', 'deleted', 'createdAt', 'modifiedAt', 'createdBy', 'modifiedBy', 'ownerUser', 'assignedUser'];

    protected array $mainEntities = [];
    protected array $mainEntityParentIds = [];

    public function getMainRelationshipEntity(): string
    {
        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields'], []) as $field => $fieldDefs) {
            if (!empty($fieldDefs['mainRelationshipEntity'])) {
                return $this->getMetadata()->get(['entityDefs', $this->entityType, 'links', $field, 'entity']);
            }
        }

        throw new Error("Param 'mainRelationshipEntity' is required for Relationship entity.");
    }

    public function getMainRelationshipEntityField(): string
    {
        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields'], []) as $field => $fieldDefs) {
            if (!empty($fieldDefs['mainRelationshipEntity'])) {
                return $this->getMetadata()->get(['entityDefs', $this->entityType, 'links', $field, 'foreign']);
            }
        }

        throw new Error("Param 'mainRelationshipEntity' is required for Relationship entity.");
    }

    public function inheritable(): bool
    {
        try {
            $mainEntity = $this->getMainRelationshipEntity();
        } catch (Error $e) {
            return false;
        }

        if ($this->getMetadata()->get(['scopes', $mainEntity, 'type']) !== 'Hierarchy' || !$this->getMetadata()->get(['scopes', $mainEntity, 'relationInheritance'], false)) {
            return false;
        }

        if (in_array($this->getMainRelationshipEntityField(), $this->getMetadata()->get(['scopes', $mainEntity, 'unInheritedRelations'], []))) {
            return false;
        }

        return true;
    }

    public function getMainEntity(Entity $relationshipEntity): ?Entity
    {
        $entity = $this->getMainRelationshipEntity();

        $id = $relationshipEntity->get(lcfirst($entity) . 'Id');
        if (empty($id)) {
            return null;
        }

        if (!isset($this->mainEntities[$id])) {
            $this->mainEntities[$id] = $relationshipEntity->get(lcfirst($entity));
        }

        return $this->mainEntities[$id];
    }

    public function getMainEntityParentIds(Entity $mainEntity): array
    {
        if (!isset($this->mainEntityParentIds[$mainEntity->get('id')])) {
            $this->mainEntityParentIds[$mainEntity->get('id')] = $mainEntity->getLinkMultipleIdList('parents');
        }

        return $this->mainEntityParentIds[$mainEntity->get('id')];
    }

    public function isInherited(Entity $entity): ?bool
    {
        $mainEntity = $this->getMainEntity($entity);
        if (empty($mainEntity)) {
            return null;
        }

        $parentIds = $this->getMainEntityParentIds($mainEntity);
        if (empty($parentIds)) {
            return null;
        }

        $parentRecord = $this
            ->select(['id'])
            ->where($this->prepareWhereForInheritanceCheck($entity, [lcfirst($mainEntity->getEntityType()) . 'Id' => $parentIds]))
            ->findOne();

        return !empty($parentRecord);
    }

    public function getInheritedEntities(string $parentId): EntityCollection
    {
        $mainRelationshipEntity = $this->getMainRelationshipEntity();
        $mainRelationshipField = lcfirst($mainRelationshipEntity) . 'Id';

        $entity = $this->get($parentId);
        if (empty($entity) || empty($entity->get($mainRelationshipField))) {
            return new EntityCollection([], $this->entityType);
        }

        $children = $this->getEntityManager()->getRepository($mainRelationshipEntity)->getChildrenArray($entity->get($mainRelationshipField));
        if (empty($children)) {
            return new EntityCollection([], $this->entityType);
        }

        $result = $this
            ->where($this->prepareWhereForInheritanceCheck($entity, [$mainRelationshipField => array_column($children, 'id')]))
            ->find();

        foreach ($result as $record) {
            $record->_childrenCount = 0;
            foreach ($children as $child) {
                if ($child['id'] === $record->get($mainRelationshipField)) {
                    $record->_childrenCount = $child['childrenCount'];
                    break;
                }
            }
        }

        return $result;
    }

    protected function prepareWhereForInheritanceCheck(Entity $entity, array $where): array
    {
        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields']) as $field => $fieldDefs) {
            // skip virtual
            if (!empty($fieldDefs['notStorable'])) {
                continue;
            }

            // skip system
            if (in_array($field, self::SYSTEM_FIELDS)) {
                continue;
            }

            if (!empty($fieldDefs['mainRelationshipEntity'])) {
                continue;
            }

            if (empty($fieldDefs['type']) || in_array($fieldDefs['type'], ['linkMultiple'])) {
                continue;
            }

            if (in_array($fieldDefs['type'], ['link', 'asset'])) {
                $field .= 'Id';
            }

            $where[$field] = $entity->get($field);
            if (is_array($where[$field])) {
                $where[$field] = json_encode($where[$field]);
            }
        }

        return $where;
    }

    public function remove(Entity $entity, array $options = [])
    {
        try {
            $result = parent::remove($entity, $options);
        } catch (\Throwable $e) {
            // delete duplicate
            if ($e instanceof \PDOException && strpos($e->getMessage(), '1062') !== false) {
                if (!empty($toDelete = $this->getDuplicateEntity($entity, true))) {
                    $this->deleteFromDb($toDelete->get('id'), true);
                }
                return parent::remove($entity, $options);
            }
            throw $e;
        }

        return $result;
    }

    public function getDuplicateEntity(Entity $entity, bool $deleted = false): ?Entity
    {
        $where = [
            'id!='    => $entity->get('id'),
            'deleted' => $deleted,
        ];

        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields']) as $field => $fieldDefs) {
            if (!empty($fieldDefs['relationshipField'])) {
                if ($fieldDefs['type'] === 'link') {
                    $where[$field . 'Id'] = $entity->get($field . 'Id');
                } else {
                    $where[$field] = $entity->get($field);
                }
            }
        }

        return $this->where($where)->findOne(['withDeleted' => $deleted]);
    }
}
