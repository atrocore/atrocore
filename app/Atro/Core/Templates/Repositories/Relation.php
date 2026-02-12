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
use Atro\Core\PseudoTransactionManager;
use Atro\Core\Utils\IdGenerator;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Atro\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class Relation extends Base
{
    public function hasDeletedRecordsToClear(): bool
    {
        $tableName = $this->getEntityManager()->getMapper()->toDb($this->entityName);

        foreach ($this->getMetadata()->get("entityDefs.$this->entityName.fields") ?? [] as $field => $fieldDefs) {
            if (!empty($fieldDefs['relationField'])) {
                $alias = IdGenerator::unsortableId();
                $relEntity = $this->getMetadata()->get("entityDefs.$this->entityName.links.$field.entity");
                $relTable = $this->getEntityManager()->getMapper()->toDb($relEntity);

                $res = $this->getConnection()->createQueryBuilder()
                    ->select('t.id')
                    ->from($this->getConnection()->quoteIdentifier($tableName), 't')
                    ->leftJoin('t', $relTable, $alias, "$alias.id=t.{$relTable}_id")
                    ->where("$alias.id IS NULL")
                    ->fetchOne();

                if (!empty($res)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function clearDeletedRecords(): void
    {
        $tableName = $this->getEntityManager()->getMapper()->toDb($this->entityName);

        foreach ($this->getMetadata()->get("entityDefs.$this->entityName.fields") ?? [] as $field => $fieldDefs) {
            if (!empty($fieldDefs['relationField'])) {
                $alias = IdGenerator::unsortableId();
                $relEntity = $this->getMetadata()->get("entityDefs.$this->entityName.links.$field.entity");
                $relTable = $this->getEntityManager()->getMapper()->toDb($relEntity);

                while (true) {
                    $ids = $this->getConnection()->createQueryBuilder()
                        ->select('t.id')
                        ->from($this->getConnection()->quoteIdentifier($tableName), 't')
                        ->leftJoin('t', $relTable, $alias, "$alias.id=t.{$relTable}_id")
                        ->where("$alias.id IS NULL")
                        ->setFirstResult(0)
                        ->setMaxResults(10000)
                        ->fetchFirstColumn();

                    if (empty($ids)) {
                        break;
                    }

                    $this->getConnection()->createQueryBuilder()
                        ->delete($this->getConnection()->quoteIdentifier($tableName))
                        ->where('id IN (:ids)')
                        ->setParameter('ids', $ids, $this->getConnection()::PARAM_STR_ARRAY)
                        ->executeQuery();
                }
            }
        }
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

    protected function isAssociatesRelation(): bool
    {
        return !empty($this->getMetadata()->get(['scopes', $this->entityType, 'associatesForEntity']));
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        $this->validateAllowTypesIfRelationWithFile($entity);
        parent::beforeSave($entity, $options);

        if ($this->isAssociatesRelation()) {
            $scope = $this->getMetadata()->get(['scopes', $this->entityType, 'associatesForEntity']);

            if ($entity->get("associatingItemId") == $entity->get("associatedItemId")) {
                throw new BadRequest($this->getLanguage()->translate('itselfAssociation', 'exceptions', $scope));
            }

            if ($entity->isNew() && $entity->get('sorting') === null) {
                $last = $this->where(["associatingItemId" => $entity->get("associatingItemId")])->order('sorting', 'DESC')->findOne();
                $entity->set('sorting', empty($last) ? 0 : (int)$last->get('sorting') + 10);
            }
        }

        if ($entity->isNew() && !empty($options['duplicateRelationKeys']) && is_array($options['duplicateRelationKeys'])) {
            $duplicatingRelationEntity = $this->where($options['duplicateRelationKeys'])->findOne();

            if (!empty($duplicatingRelationEntity)) {
                foreach ($this->prepareDuplicatedRelationFields() as $field) {
                    $entity->set($field, $duplicatingRelationEntity->get($field));
                }
            }
        }
    }

    public function updateAssociatesSortOrder(array $ids): void
    {
        $collection = $this->where(['id' => $ids])->find();
        if (count($collection) === 0) {
            return;
        }

        foreach ($ids as $k => $id) {
            $sortOrder = (int)$k * 10;
            foreach ($collection as $entity) {
                if ($entity->get('id') !== (string)$id) {
                    continue;
                }
                $entity->set('sorting', $sortOrder);
                $this->save($entity);
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

        if (!empty($this->getMetadata()->get(['scopes', $this->entityType, 'isHierarchyEntity']))) {
            if (empty($this->getMetadata()->get(['scopes', $this->getHierarchicalEntity(), 'multiParents']))) {
                $this->getConnection()->createQueryBuilder()
                    ->delete($this->getEntityManager()->getMapper()->toDB($this->entityType))
                    ->where('entity_id=:entityId AND parent_id <> :parentId')
                    ->setParameter('entityId', $entity->get('entityId'))
                    ->setParameter('parentId', $entity->get('parentId'))
                    ->executeQuery();
            }

            // rebuild routes
            if ($entity->isNew() || $entity->isAttributeChanged('parentId')) {
                $this
                    ->getEntityManager()
                    ->getRepository($this->getHierarchicalEntity())
                    ->buildRoutes($entity->get('entityId'));
            }
        }
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        parent::beforeRemove($entity, $options);

        $this->deleteAlreadyDeleted($entity);
        if ($this->isAssociatesRelation()) {
            /**
             * Delete reverse relation
             */
            $scope = $this->getMetadata()->get(['scopes', $this->entityType, 'associatesForEntity']);

            if (empty($options['skipDeleteReverseAssociatedItem']) && !empty($reverseAssociatedItem = $entity->get("reverseAssociated$scope"))) {
                $this->remove($reverseAssociatedItem, ['skipDeleteReverseAssociatedItem' => true]);
            }
        }
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

        if (in_array($hierarchicalEntityLink, $this->getEntityManager()->getRepository($hierarchicalEntity)->getUnInheritableFields())) {
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

    protected function updateModifiedAtForRelatedEntity(Entity $entity): void
    {
        $isHierarchyEntity = $this->getMetadata()->get(['scopes', $this->entityType, 'isHierarchyEntity'], false);

        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'links'], []) as $link => $defs) {
            if (empty($defs['entity'])) {
                continue;
            }

            $modifiedExtendedRelations = $this->getMetadata()->get(['scopes', $defs['entity'], 'modifiedExtendedRelations'], []);
            if (empty($modifiedExtendedRelations)) {
                continue;
            }

            foreach ($modifiedExtendedRelations as $relation) {
                $relDefs = $this->getMetadata()->get(['entityDefs', $defs['entity'], 'links', $relation]);
                if (!empty($relDefs['relationName']) && ucfirst($relDefs['relationName']) == $this->entityType) {
                    if ($isHierarchyEntity) {
                        if (empty($relDefs['midKeys']) || !is_array($relDefs['midKeys']) || count($relDefs['midKeys']) < 2) {
                            continue;
                        }
                        if ($link . 'Id' != $relDefs['midKeys'][1]) {
                            continue;
                        }
                    }

                    $this->getPseudoTransactionManager()->pushUpdateEntityJob($defs['entity'], $entity->get($link . 'Id'), [
                        'modifiedAt'   => (new \DateTime())->format('Y-m-d H:i') . ':00',
                        'modifiedById' => $this->getEntityManager()->getUser()->get('id')
                    ]);
                }
            }
        }
    }

    protected function validateAllowTypesIfRelationWithFile(Entity $entity): void
    {
        $relationFields = $this->getRelationFields();
        if (empty($relationFields[0]) || empty($relationFields[1])) {
            return;
        }

        $linkDefs1 = $this->getMetadata()->get(['entityDefs', $this->entityType, 'links', $relationFields[0]]);
        $linkDefs2 = $this->getMetadata()->get(['entityDefs', $this->entityType, 'links', $relationFields[1]]);

        if (empty($linkDefs1['entity']) || empty($linkDefs2['entity'])) {
            return;
        }

        if ($linkDefs1['entity'] !== 'File' && $linkDefs2['entity'] !== 'File') {
            return;
        }

        $linkDefs = $linkDefs1['entity'] === 'File' ? $linkDefs2 : $linkDefs1;
        $fileField = $linkDefs1['entity'] === 'File' ? $relationFields[0] : $relationFields[0];

        foreach ($this->getMetadata()->get(['entityDefs', $linkDefs['entity'], 'links']) as $link => $defs) {
            if (!empty($defs['relationName']) && $defs['entity'] === 'File' && ucfirst($defs['relationName']) === $this->entityType) {
                $allowTypeIds = $this->getMetadata()->get(['entityDefs', $linkDefs['entity'], 'fields', $link, 'fileTypes'], []);
                if (!empty($allowTypeIds) && !empty($file = $entity->get($fileField)) && !in_array($file->get('typeId'), $allowTypeIds)) {
                    $allowTypeNames = $this->getMetadata()->get(['entityDefs', $linkDefs['entity'], 'fields', $link, 'allowFileTypesNames'], []);
                    throw  new BadRequest(sprintf($this->getLanguage()->translate('notAllowToFileWithEntity', 'exceptions', 'File'), $file->get('typeName'), $linkDefs['entity'], join(', ', $allowTypeNames)));
                }
            }
        }
    }

    protected function prepareDuplicatedRelationFields(): array
    {
        $result = [];

        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields']) as $field => $defs) {
            if (!empty($defs['duplicateIgnore']) || !empty($defs['relationField']) || !empty($defs['notStorable']) || !empty($defs['disabled'])) {
                continue;
            }

            if (in_array($field, $this->getMetadata()->get(['scopes', $this->entityType, 'nonDuplicatableFields'], []))) {
                continue;
            }

            if (in_array($defs['type'], ['link', 'linkMultiple']) && in_array($field, $this->getMetadata()->get(['scopes', $this->entityType, 'duplicatableRelations'], []))) {
                if ($defs['type'] == 'link') {
                    $field .= 'Id';
                }

                if ($defs['type'] == 'linkMultiple') {
                    if ($this->getMetadata()->get(['entityDefs', $defs['entity'], 'fields', $defs['foreign'], 'type']) == 'link') {
                        continue;
                    }

                    $field .= 'Ids';
                }
            }

            $result[] = $field;
        }

        return $result;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('pseudoTransactionManager');
    }

    protected function getPseudoTransactionManager(): PseudoTransactionManager
    {
        return $this->getInjection('pseudoTransactionManager');
    }

    public function getRelatedLink(string $entityName): ?string
    {
        $derivedScope = $this->getMetadata()->get(['scopes', $this->entityType, 'derivativeScope']);
        if (!empty($derivedScope) && $this->getMetadata()->get(['scopes', $derivedScope, 'primaryEntityId']) === $entityName) {
            $entityName = $derivedScope;
        }

        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'links']) as $link => $defs) {
            if (!empty($defs['entity']) && $defs['entity'] === $entityName) {
                return $link;
            }
        }
        return null;
    }
}
