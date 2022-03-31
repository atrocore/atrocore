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

namespace Espo\Core\Templates\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\Services\Record;
use Treo\Core\EventManager\Event;
use Treo\Core\Exceptions\NotModified;

class Hierarchy extends Record
{
    public function inheritAll(string $id, string $link): bool
    {
        $event = $this->dispatchEvent('beforeInheritAll', new Event(['id' => $id, 'link' => $link]));

        $id = $event->getArgument('id');
        $link = $event->getArgument('link');

        if (empty($id) || empty($link)) {
            throw new BadRequest("'id' and 'link' is required parameters.");
        }

        if (empty($entity = $this->getRepository()->get($id))) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }

        if (empty($foreignEntityType = $entity->getRelationParam($link, 'entity'))) {
            throw new Error();
        }

        if (!$this->getAcl()->check($foreignEntityType, in_array($link, $this->noEditAccessRequiredLinkList) ? 'read' : 'edit')) {
            throw new Forbidden();
        }

        $parents = $entity->get('parents');
        if (empty($parents[0])) {
            throw new NotFound();
        }

        $foreignIds = [];
        foreach ($parents as $parent) {
            $foreignIds = array_merge($foreignIds, $parent->getLinkMultipleIdList($link));
        }
        $foreignIds = array_unique($foreignIds);

        if (empty($foreignIds)) {
            throw new BadRequest($this->getInjection('language')->translate('nothingToInherit', 'exceptions'));
        }

        foreach ($foreignIds as $k => $foreignId) {
            if ($k < $this->maxMassLinkCount) {
                $this->linkEntity($id, $link, $foreignId);
            } else {
                $this->getPseudoTransactionManager()->pushLinkEntityJob($this->entityType, $id, $link, $foreignId);
                $this->createPseudoTransactionLinkJobs($id, $link, $foreignId);
            }
        }

        return $this->dispatchEvent('afterUnlinkAll', new Event(['entity' => $entity, 'link' => $link, 'result' => true]))->getArgument('result');
    }

    public function inheritField(string $field, string $id): bool
    {
        $event = $this->dispatchEvent('beforeInheritField', new Event(['id' => $id, 'field' => $field]));

        $id = $event->getArgument('id');
        $field = $event->getArgument('field');

        $entity = $this->getRepository()->get($id);

        if (empty($entity)) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }

        $parents = $entity->get('parents');
        if (empty($parents[0])) {
            throw new BadRequest('No parents found.');
        }

        $resultInput = new \stdClass();
        foreach ($parents as $parent) {
            $input = new \stdClass();
            switch ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $field, 'type'], 'varchar')) {
                case 'asset':
                case 'image':
                case 'link':
                    $input->{$field . 'Id'} = $parent->get($field . 'Id');
                    $input->{$field . 'Name'} = $parent->get($field . 'Name');
                    break;
                case 'multiEnum':
                case 'enum':
                    $field = $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $field, 'multilangField'], $field);
                    $input->$field = $parent->get($field);
                    break;
                case 'currency':
                    $input->$field = $parent->get($field);
                    $input->{$field . 'Currency'} = $parent->get($field . 'Currency');
                    break;
                case 'unit':
                    $input->$field = $parent->get($field);
                    $input->{$field . 'Unit'} = $parent->get($field . 'Unit');
                    break;
                case 'linkMultiple':
                    $input->{$field . 'Ids'} = array_column($parent->get($field)->toArray(), 'id');
                    break;
                default:
                    $input->$field = $parent->get($field);
                    break;
            }

            foreach ($input as $k => $v) {
                if (property_exists($resultInput, $k) && $resultInput->$k !== $v) {
                    throw new BadRequest($this->getInjection('language')->translate('parentRecordsHaveDifferentValues', 'exceptions'));
                }
            }

            $resultInput = clone $input;
        }

        try {
            $this->updateEntity($id, $resultInput);
        } catch (Conflict $e) {
        } catch (NotModified $e) {
        }

        return true;
    }

    public function unlinkAllHierarchically(string $id, string $link): bool
    {
        $event = $this->dispatchEvent('beforeUnlinkAllHierarchically', new Event(['id' => $id, 'link' => $link]));

        $id = $event->getArgument('id');
        $link = $event->getArgument('link');

        if (empty($id) || empty($link)) {
            throw new BadRequest("'id' and 'link' is required parameters.");
        }

        if (empty($entity = $this->getRepository()->get($id))) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }

        if (empty($foreignEntityType = $entity->getRelationParam($link, 'entity'))) {
            throw new Error();
        }

        if (!$this->getAcl()->check($foreignEntityType, in_array($link, $this->noEditAccessRequiredLinkList) ? 'read' : 'edit')) {
            throw new Forbidden();
        }

        $foreignIds = $entity->getLinkMultipleIdList($link);

        foreach ($foreignIds as $k => $foreignId) {
            if ($k < $this->maxMassUnlinkCount) {
                $this->unlinkEntity($id, $link, $foreignId);
            } else {
                $this->getPseudoTransactionManager()->pushUnLinkEntityJob($this->entityType, $id, $link, $foreignId);
            }
        }

        return $this->dispatchEvent('afterUnlinkAllHierarchically', new Event(['entity' => $entity, 'link' => $link, 'result' => true]))->getArgument('result');
    }

    public function getRoute(string $id): array
    {
        return $this->getRepository()->getRoute($id);
    }

    public function getChildren(string $parentId): array
    {
        $result = [];
        foreach ($this->getRepository()->getChildrenArray($parentId) as $record) {
            $result[] = [
                'id'             => $record['id'],
                'name'           => $record['name'],
                'load_on_demand' => !empty($record['childrenCount'])
            ];
        }

        return $result;
    }

    public function getEntity($id = null)
    {
        $entity = parent::getEntity($id);

        if (!empty($entity)) {
            $entity->set('inheritedFields', $this->getInheritedFields($entity));
        }

        return $entity;
    }

    public function createEntity($attachment)
    {
        $this->prepareChildInputData($attachment);

        return parent::createEntity($attachment);
    }

    public function prepareChildInputData(\stdClass $attachment): void
    {
        if (property_exists($attachment, 'parentsIds') && !empty($attachment->parentsIds[0])) {
            foreach ($this->getDuplicateAttributes($attachment->parentsIds[0]) as $field => $value) {
                if (property_exists($attachment, $field) || in_array($field, $this->getNonInheritedFieldsKeys())) {
                    continue 1;
                }
                $attachment->$field = $value;
            }
            if (empty($this->getMetadata()->get(['scopes', $this->entityType, 'relationInheritance'])) && property_exists($attachment, '_duplicatingEntityId')) {
                unset($attachment->_duplicatingEntityId);
            }
        }
    }

    public function updateEntity($id, $data)
    {
        if (property_exists($data, '_sortedIds') && property_exists($data, '_id')) {
            $this->getRepository()->updateHierarchySortOrder($data->_id, $data->_sortedIds);
            return $this->getEntity($id);
        }

        if (property_exists($data, '_position') && property_exists($data, '_target') && property_exists($data, 'parentId')) {
            if (empty($entity = $this->getRepository()->get($id))) {
                throw new NotFound();
            }
            if (!$this->getAcl()->check($entity, 'edit')) {
                throw new Forbidden();
            }
            $this->getRepository()->updatePositionInTree((string)$id, (string)$data->_position, (string)$data->_target, (string)$data->parentId);
            return $this->getEntity($id);
        }

        if (property_exists($data, '_fieldValueInheritance') && $data->_fieldValueInheritance) {
            return parent::updateEntity($id, $data);
        }

        if (empty($this->getMetadata()->get(['scopes', $this->entityType, 'fieldValueInheritance']))) {
            return parent::updateEntity($id, $data);
        }

        $this->getEntityManager()->getPDO()->beginTransaction();
        try {
            $entityData = $this->getRepository()->fetchById($id);
            $result = parent::updateEntity($id, $data);
            $this->createPseudoTransactionJobs($entityData, clone $data);
            $this->getEntityManager()->getPDO()->commit();
        } catch (\Throwable $e) {
            $this->getEntityManager()->getPDO()->rollBack();
            throw $e;
        }

        return $result;
    }

    public function linkEntity($id, $link, $foreignId)
    {
        if (empty($this->getMetadata()->get(['scopes', $this->entityType, 'relationInheritance']))) {
            return parent::linkEntity($id, $link, $foreignId);
        }

        if ($this->isPseudoTransaction()) {
            return parent::linkEntity($id, $link, $foreignId);
        }

        $this->getEntityManager()->getPDO()->beginTransaction();
        try {
            $result = parent::linkEntity($id, $link, $foreignId);
            $this->createPseudoTransactionLinkJobs($id, $link, $foreignId);
            $this->getEntityManager()->getPDO()->commit();
        } catch (\Throwable $e) {
            $this->getEntityManager()->getPDO()->rollBack();
            throw $e;
        }

        return $result;
    }

    public function unlinkEntity($id, $link, $foreignId)
    {
        if (!empty($this->originUnlinkAction)) {
            return parent::unlinkEntity($id, $link, $foreignId);
        }

        if (empty($this->getMetadata()->get(['scopes', $this->entityType, 'relationInheritance']))) {
            return parent::unlinkEntity($id, $link, $foreignId);
        }

        if ($this->isPseudoTransaction()) {
            return parent::unlinkEntity($id, $link, $foreignId);
        }

        $this->getEntityManager()->getPDO()->beginTransaction();
        try {
            $result = parent::unlinkEntity($id, $link, $foreignId);
            $this->createPseudoTransactionUnlinkJobs($id, $link, $foreignId);
            $this->getEntityManager()->getPDO()->commit();
        } catch (\Throwable $e) {
            $this->getEntityManager()->getPDO()->rollBack();
            throw $e;
        }

        return $result;
    }

    public function deleteEntity($id)
    {
        $this->getEntityManager()->getPDO()->beginTransaction();
        try {
            $result = parent::deleteEntity($id);
            foreach ($this->getRepository()->getChildrenRecursivelyArray($id) as $childId) {
                parent::deleteEntity($childId);
            }
            $this->getEntityManager()->getPDO()->commit();
        } catch (\Throwable $e) {
            $this->getEntityManager()->getPDO()->rollBack();
            throw $e;
        }

        return $result;
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $entity->set('isRoot', $this->getRepository()->isRoot($entity->get('id')));

        if ($entity->has('hierarchySortOrder')) {
            $entity->set('sortOrder', $entity->get('hierarchySortOrder'));
        }
    }

    public function findLinkedEntities($id, $link, $params)
    {
        $result = parent::findLinkedEntities($id, $link, $params);
        if (empty($result['total'])) {
            return $result;
        }

        if ($link === 'children') {
            $result['collection'] = $this->sortCollection($result['collection']);
            return $result;
        }

        if ($link === 'parents') {
            return $result;
        }

        $parents = $this->getRepository()->get($id)->get('parents');
        if (empty($parents) || count($parents) === 0) {
            return $result;
        }

        $parentsRelatedIds = [];
        foreach ($parents as $parent) {
            $parentsRelatedIds = array_merge($parentsRelatedIds, array_column($parent->get($link)->toArray(), 'id'));
        }

        $result['list'] = $result['collection']->toArray();
        unset($result['collection']);

        foreach ($result['list'] as $k => $record) {
            $result['list'][$k]['isInherited'] = in_array($record['id'], $parentsRelatedIds);
        }

        return $result;
    }

    protected function duplicateParents($entity, $duplicatingEntity): void
    {
        // ignore duplicating for link 'parents'
    }

    protected function duplicateChildren($entity, $duplicatingEntity): void
    {
        // ignore duplicating for link 'children'
    }

    protected function afterUpdateEntity(Entity $entity, $data)
    {
        $entity->set('inheritedFields', $this->getInheritedFields($entity));
    }

    protected function createPseudoTransactionJobs(array $parent, \stdClass $data, string $parentTransactionId = null): void
    {
        $children = $this->getRepository()->getChildrenArray($parent['id']);
        foreach ($children as $child) {
            $this->getRepository()->pushLinkMultipleFields($child);
            $inputData = $this->createInputDataForPseudoTransactionJob($parent, $child, clone $data);
            if (!empty((array)$inputData)) {
                $inputData->_fieldValueInheritance = true;
                $transactionId = $this->getPseudoTransactionManager()->pushUpdateEntityJob($this->entityType, $child['id'], $inputData, $parentTransactionId);
                if ($child['childrenCount'] > 0) {
                    $this->createPseudoTransactionJobs($child, clone $inputData, $transactionId);
                }
            }
        }
    }

    protected function createPseudoTransactionLinkJobs(string $id, string $link, string $foreignId, string $parentTransactionId = null): void
    {
        $unInheritedRelations = array_merge(
            $this->getMetadata()->get(['app', 'nonInheritedRelations'], []), $this->getMetadata()->get(['scopes', $this->entityType, 'unInheritedRelations'], [])
        );
        if (in_array($link, $unInheritedRelations)) {
            return;
        }

        if (empty($this->getMetadata()->get(['entityDefs', $this->entityType, 'links', $link, 'relationName']))) {
            return;
        }

        $children = $this->getRepository()->getChildrenArray($id);
        foreach ($children as $child) {
            $transactionId = $this->getPseudoTransactionManager()->pushLinkEntityJob($this->entityType, $child['id'], $link, $foreignId, $parentTransactionId);
            if ($child['childrenCount'] > 0) {
                $this->createPseudoTransactionLinkJobs($child['id'], $link, $foreignId, $transactionId);
            }
        }
    }

    protected function createPseudoTransactionUnlinkJobs(string $id, string $link, string $foreignId, string $parentTransactionId = null): void
    {
        $unInheritedRelations = array_merge(
            $this->getMetadata()->get(['app', 'nonInheritedRelations'], []), $this->getMetadata()->get(['scopes', $this->entityType, 'unInheritedRelations'], [])
        );
        if (in_array($link, $unInheritedRelations)) {
            return;
        }

        if (empty($this->getMetadata()->get(['entityDefs', $this->entityType, 'links', $link, 'relationName']))) {
            return;
        }

        $children = $this->getRepository()->getChildrenArray($id);
        foreach ($children as $child) {
            $transactionId = $this->getPseudoTransactionManager()->pushUnLinkEntityJob($this->entityType, $child['id'], $link, $foreignId, $parentTransactionId);
            if ($child['childrenCount'] > 0) {
                $this->createPseudoTransactionUnlinkJobs($child['id'], $link, $foreignId, $transactionId);
            }
        }
    }

    protected function createInputDataForPseudoTransactionJob(array $parent, array $child, \stdClass $data): \stdClass
    {
        $unInheritedFields = $this->getRepository()->getUnInheritedFields();
        $inputData = new \stdClass();
        foreach ($data as $field => $value) {
            if (in_array($field, $unInheritedFields)) {
                continue 1;
            }
            $underScoredField = Util::toUnderScore($field);
            if (!array_key_exists($underScoredField, $parent)) {
                continue 1;
            }
            if ($this->areValuesEqual($this->getRepository()->get(), $field, $parent[$underScoredField], $child[$underScoredField])) {
                $inputData->$field = $value;
            }
        }

        return $inputData;
    }

    protected function sortCollection(EntityCollection $inputCollection): EntityCollection
    {
        $ids = [];
        foreach ($inputCollection as $entity) {
            $ids[$entity->get('id')] = $entity->get('sortOrder');
        }
        asort($ids);

        $collection = new EntityCollection();
        foreach ($ids as $id => $sortOrder) {
            foreach ($inputCollection as $entity) {
                if ($entity->get('id') === $id) {
                    $collection->append($entity);
                    break;
                }
            }
        }

        return $collection;
    }

    protected function getInheritedFields(Entity $entity): array
    {
        $parents = $entity->get('parents');
        if (empty($parents[0])) {
            return [];
        }

        $unInheritedFields = $this->getRepository()->getUnInheritedFields();

        $inheritedFields = [];
        foreach ($parents as $parent) {
            foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields'], []) as $field => $fieldData) {
                if (in_array($field, $inheritedFields) || in_array($field, $unInheritedFields)) {
                    continue 1;
                }

                if (!empty($fieldData['notStorable'])) {
                    continue 1;
                }

                switch ($fieldData['type']) {
                    case 'asset':
                    case 'image':
                    case 'link':
                        if ($this->areValuesEqual($this->getRepository()->get(), $field, $parent->get($field . 'Id'), $entity->get($field . 'Id'))) {
                            $inheritedFields[] = $field;
                        }
                        break;
                    case 'currency':
                        if (
                            $this->areValuesEqual($this->getRepository()->get(), $field, $parent->get($field), $entity->get($field))
                            && $this->areValuesEqual($this->getRepository()->get(), $field . 'Currency', $parent->get($field . 'Currency'), $entity->get($field . 'Currency'))
                        ) {
                            $inheritedFields[] = $field;
                        }
                        break;
                    case 'unit':
                        if (
                            $this->areValuesEqual($this->getRepository()->get(), $field, $parent->get($field), $entity->get($field))
                            && $this->areValuesEqual($this->getRepository()->get(), $field . 'Unit', $parent->get($field . 'Unit'), $entity->get($field . 'Unit'))
                        ) {
                            $inheritedFields[] = $field;
                        }
                        break;
                    case 'linkMultiple':
                        if (!in_array($field, $this->getRepository()->getUnInheritedFields())) {
                            $parentIds = array_column($parent->get($field)->toArray(), 'id');
                            sort($parentIds);
                            $entityIds = array_column($entity->get($field)->toArray(), 'id');
                            sort($entityIds);
                            if ($this->areValuesEqual($this->getRepository()->get(), $field . 'Ids', $parentIds, $entityIds)) {
                                $inheritedFields[] = $field;
                            }
                        }
                        break;
                    default:
                        if ($this->areValuesEqual($this->getRepository()->get(), $field, $parent->get($field), $entity->get($field))) {
                            $inheritedFields[] = $field;
                        }
                        break;
                }
            }
        }

        return $inheritedFields;
    }

    protected function getNonInheritedFieldsKeys(): array
    {
        $result = [];

        $unInheritedFields = $this->getRepository()->getUnInheritedFields();

        foreach ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields'], []) as $field => $fieldData) {
            if (!in_array($field, $unInheritedFields)) {
                continue 1;
            }

            if ($fieldData['type'] === 'linkMultiple' || !empty($fieldData['notStorable'])) {
                continue 1;
            }

            if ($fieldData['type'] === 'link') {
                $result[] = $field . 'Id';
                $result[] = $field . 'Name';
            } else {
                $result[] = $field;
            }
        }

        return $result;

    }
}
