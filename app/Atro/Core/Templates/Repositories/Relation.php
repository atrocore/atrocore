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
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Exceptions\NotUnique;
use Atro\Core\ORM\Repositories\RDB;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Atro\Core\Utils\Util;
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

    public function hasDeletedRecordsToCleanup(): bool
    {
        return false;
    }

    public function cleanupDeletedRecords(): void
    {
    }

    public function deleteAlreadyDeleted(Entity $entity): void
    {
        $uniqueColumns = $this->getEntityManager()->getEspoMetadata()->get(['entityDefs', $entity->getEntityType(), 'uniqueIndexes', 'unique_relation']);
        if (empty($uniqueColumns)) {
            throw new \Error('No unique column found.');
        }

        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb->delete($this->getEntityManager()->getConnection()->quoteIdentifier($this->getMapper()->toDb($entity->getEntityType())));
        $qb->where('deleted = :true');
        $qb->setParameter("true", true, ParameterType::BOOLEAN);
        foreach ($uniqueColumns as $column) {
            if ($column === 'deleted') {
                continue;
            }
            $value = $entity->get(Util::toCamelCase($column));
            $qb->andWhere("$column = :{$column}_val");
            $qb->setParameter("{$column}_val", $value, Mapper::getParameterType($value));
        }
        $qb->executeQuery();
    }

    public function createHierarchical(Entity $entity): void
    {
        if (!$this->isInheritedRelation()) {
            return;
        }

        $link = $this->getHierarchicalRelation();
        if (empty($link)) {
            return;
        }

        $hierarchicalEntity = $entity->get($link);
        if (empty($hierarchicalEntity)) {
            return;
        }

        $children = $this->getEntityManager()->getRepository($hierarchicalEntity->getEntityType())->getChildrenArray($hierarchicalEntity->get('id'), false);
        if (empty($children)) {
            return;
        }

        $additionalFields = $this->getAdditionalFieldsNames();

        /** @var \Atro\Core\Templates\Services\Relation $service */
        $service = $this->getInjection('container')->get('serviceFactory')->create($this->entityType);

        foreach ($children as $child) {
            $input = new \stdClass();
            foreach ($this->getRelationFields() as $relField) {
                $input->{"{$relField}Id"} = $relField === $link ? $child['id'] : $entity->get("{$relField}Id");
            }
            foreach ($additionalFields as $additionalField) {
                $input->{$additionalField} = $entity->get($additionalField);
            }

            try {
                $service->createEntity($input);
            } catch (Forbidden $e) {
            } catch (BadRequest $e) {
            } catch (\Throwable $e) {
                $GLOBALS['log']->error('updateHierarchical failed: ' . $e->getMessage());
            }
        }
    }

    public function updateHierarchical(Entity $entity): void
    {
        if (!$this->isInheritedRelation()) {
            return;
        }

        $childrenRecords = $this->getChildren($entity->_fetchedEntity ?? $entity);
        if ($childrenRecords === null) {
            return;
        }

        /** @var \Atro\Core\Templates\Services\Relation $service */
        $service = $this->getInjection('container')->get('serviceFactory')->create($this->entityType);

        foreach ($childrenRecords as $childrenRecord) {
            try {
                $service->updateEntity($childrenRecord->get('id'), clone $entity->_input);
            } catch (Forbidden $e) {
            } catch (NotFound $e) {
            } catch (BadRequest $e) {
            } catch (\Throwable $e) {
                $GLOBALS['log']->error('updateHierarchical failed: ' . $e->getMessage());
            }
        }
    }

    public function deleteHierarchical(Entity $entity): void
    {
        if (!$this->isInheritedRelation()) {
            return;
        }

        $childrenRecords = $this->getChildren($entity);
        if ($childrenRecords === null) {
            return;
        }

        /** @var \Atro\Core\Templates\Services\Relation $service */
        $service = $this->getInjection('container')->get('serviceFactory')->create($this->entityType);

        foreach ($childrenRecords as $childrenRecord) {
            try {
                $service->deleteEntity($childrenRecord->get('id'));
            } catch (Forbidden $e) {
            } catch (NotFound $e) {
            } catch (BadRequest $e) {
            } catch (\Throwable $e) {
                $GLOBALS['log']->error('deleteHierarchical failed: ' . $e->getMessage());
            }
        }
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->isNew()) {
            $this->createHierarchical($entity);
        } else {
            $this->updateHierarchical($entity);
        }

        $this->updateModifiedAtForRelatedEntity($entity);

        if (!empty($this->getMetadata()->get(['scopes', $this->entityType, 'isHierarchyEntity'], false))
            && empty($this->getMetadata()->get(['scopes', $this->getHierarchicalEntity(), 'multiParents']))
        ) {
            $table = $this->getEntityManager()->getMapper()->toDB($this->entityType);
            $this->getConnection()->createQueryBuilder()
                ->delete($this->getConnection()->quoteIdentifier($table))
                ->where('entity_id=:entityId AND parent_id <> :parentId')
                ->setParameter('entityId', $entity->get('entityId'))
                ->setParameter('parentId', $entity->get('parentId'))
                ->executeQuery();
        }

    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        parent::beforeRemove($entity, $options);

        $this->deleteAlreadyDeleted($entity);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->deleteHierarchical($entity);

        $this->updateModifiedAtForRelatedEntity($entity);
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
            if (in_array($fieldDefs['type'], ['link', 'file'])) {
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

    protected function updateModifiedAtForRelatedEntity(Entity $entity)
    {
        $isHierarchyEntity = $this->getMetadata()->get(['scopes', $this->entityType, 'isHierarchyEntity'], false);

        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'links'], []) as $link => $defs) {
            if (array_key_exists('entity', $defs) && !empty($defs['entity'])) {
                $relEntityName = $defs['entity'];
                $modifiedExtendedRelations = $this->getMetadata()->get(['scopes', $relEntityName, 'modifiedExtendedRelations'], []);

                if (!empty($modifiedExtendedRelations)) {
                    foreach ($modifiedExtendedRelations as $relation) {
                        $relDefs = $this->getMetadata()->get(['entityDefs', $relEntityName, 'links', $relation]);

                        if (!empty($relDefs['relationName']) && $relDefs['relationName'] == lcfirst($this->entityType)) {
                            if ($isHierarchyEntity) {
                                if (empty($relDefs['midKeys']) || !is_array($relDefs['midKeys']) || count($relDefs['midKeys']) < 2) {
                                    continue;
                                }

                                if ($link . 'Id' != $relDefs['midKeys'][1]) {
                                    continue;
                                }
                            }

                            $relEntity = $entity->get($link);
                            if ($relEntity) {
                                try {
                                    $this->getEntityManager()->saveEntity($relEntity);
                                } catch (NotUnique $e) {
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
