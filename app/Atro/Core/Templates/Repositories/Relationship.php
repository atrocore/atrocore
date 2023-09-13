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

    public function isInherited(Entity $entity): bool
    {
        return false;
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

        // prepare where
        $where = [$mainRelationshipField => array_column($children, 'id')];
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

        $result = $this
            ->where($where)
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
