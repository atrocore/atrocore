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

namespace Atro\Core\Templates\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\Services\Record;
use Atro\Core\Exceptions\NotModified;

class Relationship extends Record
{
    public const VIRTUAL_FIELD_DELIMITER = "_";

    public function getSelectAttributeList($params)
    {
        $list = parent::getSelectAttributeList($params);

        if (!empty($list) && is_array($list)) {
            foreach ($this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'fields'], []) as $field => $fieldDefs) {
                if (empty($fieldDefs['type']) || $fieldDefs['type'] !== 'link') {
                    continue;
                }

                if (!empty($fieldDefs['relationshipField']) && !in_array($field . 'Id', $list)) {
                    $list[] = $field . 'Id';
                }
                if (!empty($fieldDefs['relationshipField']) && !in_array($field . 'Name', $list)) {
                    $list[] = $field . 'Name';
                }
            }
        }

        return $list;
    }

    public function createEntity($attachment)
    {
        if ($this->isPseudoTransaction()) {
            return parent::createEntity($attachment);
        }

        $mainEntity = $this->getRepository()->getMainRelationshipEntity();
        if (!$this->getMetadata()->get(['scopes', $mainEntity, 'relationInheritance'], false)) {
            return parent::createEntity($attachment);
        }

        if (in_array($this->getRepository()->getMainRelationshipEntityField(), $this->getMetadata()->get(['scopes', $mainEntity, 'unInheritedRelations'], []))) {
            return parent::createEntity($attachment);
        }

        $inTransaction = false;
        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getEntityManager()->getPDO()->beginTransaction();
            $inTransaction = true;
        }
        try {
            $result = parent::createEntity($attachment);
            $this->createEntityForChildren(clone $attachment);
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->rollBack();
            }
            throw $e;
        }

        return $result;
    }

    protected function createEntityForChildren(\stdClass $data, string $parentTransactionId = null): void
    {
        $mainEntity = $this->getRepository()->getMainRelationshipEntity();
        $mainRelationshipFieldId = lcfirst($mainEntity) . 'Id';
        $mainRelationshipFieldName = lcfirst($mainEntity) . 'Name';

        if (!property_exists($data, $mainRelationshipFieldId)) {
            return;
        }

        $children = $this->getEntityManager()->getRepository($mainEntity)->getChildrenArray($data->$mainRelationshipFieldId);
        foreach ($children as $child) {
            $inputData = clone $data;
            $inputData->$mainRelationshipFieldId = $child['id'];
            $inputData->$mainRelationshipFieldName = $child['name'];
            $transactionId = $this->getPseudoTransactionManager()->pushCreateEntityJob($this->entityType, $inputData, $parentTransactionId);
            if ($child['childrenCount'] > 0) {
                $this->createEntityForChildren(clone $inputData, $transactionId);
            }
        }
    }

    public function deleteEntity($id)
    {
        if ($this->isPseudoTransaction()) {
            return parent::deleteEntity($id);
        }

        $mainEntity = $this->getRepository()->getMainRelationshipEntity();
        if (!$this->getMetadata()->get(['scopes', $mainEntity, 'relationInheritance'], false)) {
            return parent::deleteEntity($id);
        }

        if (in_array($this->getRepository()->getMainRelationshipEntityField(), $this->getMetadata()->get(['scopes', $mainEntity, 'unInheritedRelations'], []))) {
            return parent::deleteEntity($id);
        }

        $inTransaction = false;
        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getEntityManager()->getPDO()->beginTransaction();
            $inTransaction = true;
        }
        try {
            $this->deleteEntityFromChildren($id);
            $result = parent::deleteEntity($id);
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->rollBack();
            }
            throw $e;
        }

        return $result;
    }

    protected function deleteEntityFromChildren(string $id, string $parentTransactionId = null): void
    {
        foreach ($this->getRepository()->getInheritedRecords($id) as $record) {
            $transactionId = $this->getPseudoTransactionManager()->pushDeleteEntityJob($this->entityType, $record->get('id'), $parentTransactionId);
            if ($record->_childrenCount > 0) {
                $this->deleteEntityFromChildren($record->get('id'), $transactionId);
            }
        }
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        if ($this->getMetadata()->get(['scopes', $collection->getEntityName(), 'relationsVirtualFields']) !== false && empty($collection->preparedRelationVirtualFields)) {
            $select = empty($selectParams['select']) ? null : $selectParams['select'];
            $virtualFields = $this->getRelationsVirtualFields($collection->getEntityName(), $select);
            if (!empty($relEntities = $this->getRelationEntities($collection, $virtualFields))) {
                foreach ($collection as $entity) {
                    $this->prepareRelationVirtualFields($entity, $relEntities, $virtualFields);
                }
            }
        }

        parent::prepareCollectionForOutput($collection, $selectParams);
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        if ($this->getMetadata()->get(['scopes', $entity->getEntityName(), 'relationsVirtualFields']) !== false && empty($entity->preparedRelationVirtualFields)) {
            $collection = new EntityCollection([$entity], $entity->getEntityName());
            $virtualFields = $this->getRelationsVirtualFields($entity->getEntityName());
            if (!empty($relEntities = $this->getRelationEntities($collection, $virtualFields))) {
                $this->prepareRelationVirtualFields($entity, $relEntities, $virtualFields);
            }
        }

        parent::prepareEntityForOutput($entity);
    }

    public function getRelationsVirtualFields(string $entityName, ?array $select = null): array
    {
        $result = [];

        foreach ($this->getMetadata()->get(['entityDefs', $entityName, 'fields'], []) as $field => $fieldDefs) {
            $fieldName = $field;
            if (isset($fieldDefs['type']) && $fieldDefs['type'] === 'link') {
                $fieldName .= 'Id';
            }

            if ($select !== null && !in_array($fieldName, $select)) {
                continue;
            }

            $parts = explode(self::VIRTUAL_FIELD_DELIMITER, $field);
            if (count($parts) === 2) {
                $result[$field] = $fieldDefs;
                $result[$field]['relationName'] = $parts[0];
                $result[$field]['relationFieldName'] = $parts[1];
            }
        }

        return $result;
    }

    public function getRelationEntities(EntityCollection $collection, array $virtualFields): array
    {
        $relEntities = [];

        foreach ($virtualFields as $field => $fieldDefs) {
            $relEntityName = $this->getMetadata()->get(['entityDefs', $collection->getEntityName(), 'links', $fieldDefs['relationName'], 'entity']);
            if (!isset($relEntities[$fieldDefs['relationName']])) {
                $where = [
                    [
                        'type'      => 'in',
                        'attribute' => 'id',
                        'value'     => array_column($collection->toArray(), $fieldDefs['relationName'] . 'Id')
                    ]
                ];
                $res = $this->getServiceFactory()->create($relEntityName)->findEntities(['where' => $where]);
                if (isset($res['collection'])) {
                    foreach ($res['collection'] as $relEntity) {
                        $relEntities[$fieldDefs['relationName']][$relEntity->get('id')] = $relEntity;
                    }
                }
            }
        }

        return $relEntities;
    }

    public function prepareRelationVirtualFields(Entity $entity, array $relEntities, array $virtualFields): void
    {
        foreach ($virtualFields as $field => $fieldDefs) {
            if (empty($relEntities[$fieldDefs['relationName']][$entity->get($fieldDefs['relationName'] . 'Id')])) {
                continue;
            }

            $relEntity = $relEntities[$fieldDefs['relationName']][$entity->get($fieldDefs['relationName'] . 'Id')];

            if (empty($fieldDefs['relationFieldName'])) {
                continue;
            }

            $relationFieldDefs = $this->getMetadata()->get(['entityDefs', $relEntity->getEntityType(), 'fields', $fieldDefs['relationFieldName']]);

            if (empty($relationFieldDefs['type'])) {
                continue;
            }

            switch ($relationFieldDefs['type']) {
                case 'rangeInt':
                case 'rangeFloat':
                    $entity->set($field . 'From', $relEntity->get($fieldDefs['relationFieldName'] . 'From'));
                    $entity->set($field . 'To', $relEntity->get($fieldDefs['relationFieldName'] . 'To'));
                    if (!empty($relationFieldDefs['measureId'])) {
                        $entity->set($field . 'UnitId', $relEntity->get($fieldDefs['relationFieldName'] . 'UnitId'));
                    }
                    break;
                case 'varchar':
                    if (empty($relationFieldDefs['unitField'])) {
                        $entity->set($field, $relEntity->get($fieldDefs['relationFieldName']));
                    } else {
                        $entity->set($fieldDefs['mainField'], $relEntity->get($fieldDefs['relationFieldName']));
                        $entity->set($fieldDefs['mainField'] . 'UnitId', $relEntity->get($fieldDefs['relationFieldName'] . 'UnitId'));
                    }
                    break;
                case 'currency':
                    $entity->set($field, $relEntity->get($fieldDefs['relationFieldName']));
                    $entity->set($field . 'Currency', $relEntity->get($fieldDefs['relationFieldName'] . 'Currency'));
                    break;
                case 'link':
                case 'file':
                case 'asset':
                    $entity->set($field . 'Id', $relEntity->get($fieldDefs['relationFieldName'] . 'Id'));
                    $entity->set($field . 'Name', $relEntity->get($fieldDefs['relationFieldName'] . 'Name'));
                    break;
                case 'enum':
                case 'multiEnum':
                    $key = "__original_value_{$fieldDefs['relationFieldName']}";
                    $entity->set($field, $relEntity->$key ?? null);
                    break;
                default:
                    $entity->set($field, $relEntity->get($fieldDefs['relationFieldName']));
            }
        }

        $entity->preparedRelationVirtualFields = true;
    }

    public function createRelationshipEntitiesViaAddRelation(string $entityType, EntityCollection $entities, array $foreignIds): array
    {
        $relationshipEntities = $this->getRelationshipEntities();
        if (count($relationshipEntities) !== 2) {
            throw new BadRequest('Action blocked.');
        }

        $foreignCollection = new EntityCollection();
        foreach ($relationshipEntities as $relationshipEntity) {
            if ($relationshipEntity !== $entityType) {
                $foreignCollection = $this->getEntityManager()->getRepository($relationshipEntity)->where(['id' => $foreignIds])->find();
            }
        }

        $related = 0;
        $notRelated = [];

        foreach ($foreignCollection as $foreignEntity) {
            foreach ($entities as $entity) {
                $input = [];
                foreach ($relationshipEntities as $field => $entityType) {
                    if ($entityType === $entity->getEntityType()) {
                        $input[$field . 'Id'] = $entity->get('id');
                    } elseif ($entityType === $foreignEntity->getEntityType()) {
                        $input[$field . 'Id'] = $foreignEntity->get('id');
                    }
                }
                try {
                    if (empty($this->getRepository()->where($input)->findOne())) {
                        $this->createEntity(json_decode(json_encode($input)));
                        $related++;
                    }
                } catch (\Throwable $e) {
                    $notRelated[] = [
                        'id'          => $entity->get('id'),
                        'name'        => $entity->get('name'),
                        'foreignId'   => $foreignEntity->get('id'),
                        'foreignName' => $foreignEntity->get('name'),
                        'message'     => utf8_encode($e->getMessage())
                    ];
                }
            }
        }

        return [
            'related'    => $related,
            'notRelated' => $notRelated
        ];
    }

    public function deleteRelationshipEntitiesViaRemoveRelation(string $entityType, EntityCollection $entities, array $foreignIds): array
    {
        $relationshipEntities = $this->getRelationshipEntities();
        if (count($relationshipEntities) !== 2) {
            throw new BadRequest('Action blocked.');
        }

        $foreignCollection = new EntityCollection();
        foreach ($relationshipEntities as $relationshipEntity) {
            if ($relationshipEntity !== $entityType) {
                $foreignCollection = $this->getEntityManager()->getRepository($relationshipEntity)->where(['id' => $foreignIds])->find();
            }
        }

        $unRelated = 0;
        $notUnRelated = [];

        foreach ($entities as $entity) {
            foreach ($foreignCollection as $foreignEntity) {
                $where = [];
                foreach ($relationshipEntities as $field => $entityType) {
                    if ($entityType === $entity->getEntityType()) {
                        $where[$field . 'Id'] = $entity->get('id');
                    } elseif ($entityType === $foreignEntity->getEntityType()) {
                        $where[$field . 'Id'] = $foreignEntity->get('id');
                    }
                }
                try {
                    $record = $this->getRepository()->where($where)->findOne();
                    if (!empty($record)) {
                        $this->deleteEntity($record->get('id'));
                    }
                    $unRelated++;
                } catch (\Throwable $e) {
                    $notUnRelated[] = [
                        'id'          => $entity->get('id'),
                        'name'        => $entity->get('name'),
                        'foreignId'   => $foreignEntity->get('id'),
                        'foreignName' => $foreignEntity->get('name'),
                        'message'     => utf8_encode($e->getMessage())
                    ];
                }
            }
        }

        return [
            'unRelated'    => $unRelated,
            'notUnRelated' => $notUnRelated
        ];
    }

    protected function getRelationshipEntities(): array
    {
        $relationshipEntities = [];
        foreach ($this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'fields']) as $field => $fieldDefs) {
            if (!empty($fieldDefs['relationshipField'])) {
                $relationshipEntities[$field] = $this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'links', $field, 'entity']);
            }
        }

        return $relationshipEntities;
    }

    public function prepareRelationFieldDataToUpdate(Entity $entity): array
    {
        if (!property_exists($entity, '_input')) {
            return [];
        }

        /** @var \stdClass $input */
        $input = $entity->_input;

        $relationsData = [];
        foreach ($this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'fields'], []) as $field => $fieldDefs) {
            if (!empty($fieldDefs['relationVirtualField'])) {
                $parts = explode(self::VIRTUAL_FIELD_DELIMITER, $field);
                switch ($fieldDefs['type']) {
                    case 'currency':
                        if (property_exists($input, $field)) {
                            $relationsData[$parts[0]]['input'][$parts[1]] = $entity->get($field);
                        }
                        if (property_exists($input, $field . 'Currency')) {
                            $relationsData[$parts[0]]['input'][$parts[1] . 'Currency'] = $entity->get($field . 'Currency');
                        }
                        break;
                    case 'unit':
                        if (property_exists($input, $field)) {
                            $relationsData[$parts[0]]['input'][$parts[1]] = $entity->get($field);
                        }
                        if (property_exists($input, $field . 'Unit')) {
                            $relationsData[$parts[0]]['input'][$parts[1] . 'Unit'] = $entity->get($field . 'Unit');
                        }
                        break;
                    case 'link':
                    case 'file':
                    case 'asset':
                        if (property_exists($input, $field . 'Id')) {
                            $relationsData[$parts[0]]['input'][$parts[1] . 'Id'] = $entity->get($field . 'Id');
                            if (property_exists($input, $field . 'Name')) {
                                $relationsData[$parts[0]]['input'][$parts[1] . 'Name'] = $entity->get($field . 'Name');
                            }
                        }
                        break;
                    default:
                        if (property_exists($input, $field)) {
                            $relationsData[$parts[0]]['input'][$parts[1]] = $entity->get($field);
                        }
                }
                if (!empty($relationsData[$parts[0]]['input'])) {
                    $relationsData[$parts[0]]['id'] = $entity->get($parts[0] . 'Id');
                    $relationsData[$parts[0]]['entity'] = $fieldDefs['entity'];
                }
            }
        }

        foreach ($relationsData as $k => $preparedData) {
            $relationsData[$k]['input'] = json_decode(json_encode($preparedData['input']));
        }

        return $relationsData;
    }

    protected function storeEntity(Entity $entity)
    {
        $inTransaction = false;
        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getEntityManager()->getPDO()->beginTransaction();
            $inTransaction = true;
        }

        try {
            try {
                $result = $this->getRepository()->save($entity, $this->getDefaultRepositoryOptions());
            } catch (\PDOException $e) {
                if (!empty($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
                    return true;
                }
                throw $e;
            }

            foreach ($this->prepareRelationFieldDataToUpdate($entity) as $preparedData) {
                try {
                    $this->getServiceFactory()->create($preparedData['entity'])->updateEntity($preparedData['id'], $preparedData['input']);
                } catch (NotModified $e) {
                    // ignore this error
                }
            }

            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->rollBack();
            }
            throw $e;
        }

        return $result;
    }

    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        $preparedRelationFieldData = $this->prepareRelationFieldDataToUpdate($entity);
        if (!empty($preparedRelationFieldData)) {
            return true;
        }

        return parent::isEntityUpdated($entity, $data);
    }
}
