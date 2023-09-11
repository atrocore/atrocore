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
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;

class Relationship extends RDB
{
    public function getMainEntityType(): string
    {
        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields'], []) as $field => $fieldDefs) {
            if (!empty($fieldDefs['mainRelationshipField'])) {
                return $this->getMetadata()->get(['entityDefs', $this->entityType, 'links', $field, 'entity']);
            }
        }

        throw new Error("Param 'mainRelationshipField' is required for Relationship entity.");
    }

    public function getMainEntityField(): string
    {
        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields'], []) as $field => $fieldDefs) {
            if (!empty($fieldDefs['mainRelationshipField'])) {
                return $this->getMetadata()->get(['entityDefs', $this->entityType, 'links', $field, 'foreign']);
            }
        }

        throw new Error("Param 'mainRelationshipField' is required for Relationship entity.");
    }

    public function getChildren(string $parentId): array
    {
        $mainEntity = $this->getMainEntityType();
        $mainRelationshipFieldId = lcfirst($mainEntity) . 'Id';

        $entity = $this->get($parentId);
        if (empty($entity) || empty($entity->get($mainRelationshipFieldId))) {
            return [];
        }

        $children = $this->getEntityManager()->getRepository($mainEntity)->getChildrenArray($entity->get($mainRelationshipFieldId));
        if (empty($children)) {
            return [];
        }

        $qb = $this->getConnection()->createQueryBuilder();
        $qb
            ->select('*')
            ->from(Util::toUnderScore($this->entityType));
        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'uniqueIndexes', 'unique_relationship']) as $column) {
            $field = Util::toCamelCase($column);
            if ($field === $mainRelationshipFieldId) {
                $qb->andWhere("$column IN (:{$field}s)")->setParameter("{$field}s", array_column($children, 'id'), \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);
            } else {
                $qb->andWhere("$column = :$field")->setParameter("$field", $entity->get($field));
            }
        }
        $qb->orderBy('created_at', 'ASC');

        $res = $qb->fetchAllAssociative();

        $result = [];
        foreach ($res as $record) {
            foreach ($children as $child) {
                if ($child['id'] === $record[Util::toUnderScore($mainRelationshipFieldId)]) {
                    $record['childrenCount'] = $child['childrenCount'];
                    break 1;
                }
            }
            $result[] = $record;
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

    protected function prepareMainEntity(): void
    {
        if ($this->mainEntityType !== null) {
            return;
        }

        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields'], []) as $field => $fieldDefs) {
            if (!empty($fieldDefs['mainRelationshipField'])) {
                $this->mainEntityType = $this->getMetadata()->get(['entityDefs', $this->entityType, 'links', $field, 'entity']);
                $this->mainEntityField = $this->getMetadata()->get(['entityDefs', $this->entityType, 'links', $field, 'foreign']);
                return;
            }
        }

        throw new Error("Param 'mainRelationshipField' is required for Relationship entity.");
    }
}
