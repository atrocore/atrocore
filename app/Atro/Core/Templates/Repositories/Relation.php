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

use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Espo\Core\ORM\Repositories\RDB;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class Relation extends RDB
{
    public static function buildVirtualFieldName(string $relationName, string $fieldName): string
    {
        return "{$relationName}__{$fieldName}";
    }

    public static function isVirtualRelationField(string $fieldName): array
    {
        if (preg_match_all('/^(.*)\_\_(.*)$/', $fieldName, $matches)) {
            return [
                'relationName' => $matches[1][0],
                'fieldName'    => $matches[2][0]
            ];
        }
        return [];
    }

    public function deleteAlreadyDeleted(Entity $entity): void
    {
        $uniqueColumns = $this->getEntityManager()->getEspoMetadata()->get(['entityDefs', $entity->getEntityType(), 'uniqueIndexes', 'unique_relation']);
        if (empty($uniqueColumns)) {
            throw new \Error('No unique column found.');
        }

        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb->delete($this->getEntityManager()->getConnection()->quoteIdentifier($this->getMapper()->toDb($entity->getEntityType())), 't2');
        $qb->where('t2.deleted = :true');
        $qb->setParameter("true", true, ParameterType::BOOLEAN);
        foreach ($uniqueColumns as $column) {
            if ($column === 'deleted') {
                continue;
            }
            $value = $entity->get(Util::toCamelCase($column));
            $qb->andWhere("t2.{$column} = :{$column}_val");
            $qb->setParameter("{$column}_val", $value, Mapper::getParameterType($value));
        }
        $qb->executeQuery();
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        parent::beforeRemove($entity, $options);

        $this->deleteAlreadyDeleted($entity);
    }

    public function getHierarchicalRelation(): ?string
    {
        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields']) as $field => $fieldDefs) {
            if (empty($fieldDefs['relationField'])) {
                continue;
            }

            $entity = $this->getMetadata()->get(['entityDefs', $this->entityType, 'links', $field, 'entity']);
            if (empty($entity)) {
                continue;
            }

            if ($this->getMetadata()->get(['scopes', $entity, 'type']) !== 'Hierarchy') {
                continue;
            }

            return $field;
        }

        return null;
    }

    public function getHierarchicalEntity(): ?string
    {
        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields']) as $field => $fieldDefs) {
            if (empty($fieldDefs['relationField'])) {
                continue;
            }

            $entity = $this->getMetadata()->get(['entityDefs', $this->entityType, 'links', $field, 'entity']);
            if (empty($entity)) {
                continue;
            }

            if ($this->getMetadata()->get(['scopes', $entity, 'type']) !== 'Hierarchy') {
                continue;
            }

            return $entity;
        }

        return null;
    }

    public function getHierarchicalEntityLink(string $hierarchicalEntity): ?string
    {
        foreach ($this->getMetadata()->get(['entityDefs', $hierarchicalEntity, 'links']) as $link => $linkDefs) {
            if (!empty($linkDefs['relationName']) && $linkDefs['relationName'] === lcfirst($this->entityType)) {
                return $link;
            }
        }

        return null;
    }

    public function getRelationFields(): array
    {
        $res = [];
        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields']) as $field => $fieldDefs) {
            if (empty($fieldDefs['relationField'])) {
                continue;
            }
            $res[] = $field;
        }

        return $res;
    }


    public function getAdditionalFieldsNames(): array
    {
        $res = [];
        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields']) as $field => $fieldDefs) {
            if (empty($fieldDefs['additionalField'])) {
                continue;
            }

            $name = $field;
            if (in_array($fieldDefs['type'], ['link', 'asset'])) {
                $name .= 'Id';
            }

            $res[] = $name;
        }

        return $res;
    }

    public function isInheritedRelation(): bool
    {
        $hierarchicalEntity = $this->getHierarchicalEntity();
        if (empty($hierarchicalEntity)) {
            return false;
        }

        if (empty($this->getMetadata()->get(['scopes', $hierarchicalEntity, 'relationInheritance']))) {
            return false;
        }

        $hierarchicalEntityLink = $this->getHierarchicalEntityLink($hierarchicalEntity);
        if (empty($hierarchicalEntityLink)) {
            return false;
        }

        if (in_array($hierarchicalEntityLink, $this->getEntityManager()->getRepository($hierarchicalEntity)->getUnInheritedRelations())) {
            return false;
        }

        return true;
    }

    public function getChildren(Entity $entity): ?EntityCollection
    {
        $link = $this->getHierarchicalRelation();
        if (empty($link)) {
            return null;
        }

        $hierarchicalEntity = $entity->get($link);
        if (empty($hierarchicalEntity)) {
            return null;
        }

        $childrenIds = $hierarchicalEntity->getLinkMultipleIdList('children');
        if (empty($childrenIds[0])) {
            return null;
        }

        $additionalFields = $this->getAdditionalFieldsNames();

        $where = [];
        foreach ($childrenIds as $childId) {
            foreach ($this->getRelationFields() as $relField) {
                if ($relField === $link) {
                    $where["{$relField}Id"][] = $childId;
                } else {
                    $where["{$relField}Id"] = $entity->get("{$relField}Id");
                }
            }
            foreach ($additionalFields as $additionalField) {
                $where[$additionalField] = $entity->get($additionalField);
            }
        }

        return $this
            ->where($where)
            ->find();
    }
}
