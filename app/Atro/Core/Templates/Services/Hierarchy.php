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

namespace Atro\Core\Templates\Services;

use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\NotUnique;
use Atro\Core\Templates\Repositories\Relation;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Conflict;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\Services\Record;
use Atro\Core\Exceptions\NotModified;

class Hierarchy extends Record
{
    public function getHierarchySortOrderFieldName(): string
    {
        $relationEntityName = ucfirst($this->getMetadata()->get(['entityDefs', $this->getEntityType(), 'links', 'children', 'relationName']));

        return Relation::buildVirtualFieldName($relationEntityName, 'hierarchySortOrder');
    }

    public function getSelectAttributeList($params)
    {
        if ($this->getMetadata()->get(['scopes', $this->entityType, 'disableHierarchy'], false)) {
            return parent::getSelectAttributeList($params);
        }

        $res = parent::getSelectAttributeList($params);
        if (is_array($res) && $this->getMetadata()->get(['scopes', $this->getEntityType(), 'type']) == 'Hierarchy') {
            $hierarchySortOrderField = $this->getHierarchySortOrderFieldName();
            if (!in_array($hierarchySortOrderField, $res)) {
                $res[] = $hierarchySortOrderField;
            }
        }

        return $res;
    }

    public function inheritAllForChildren(string $id): bool
    {
        $parent = parent::getEntity($id);
        if (empty($parent)) {
            throw new NotFound();
        }

        $childrenIds = $this->getRepository()->getChildrenRecursivelyArray($id);
        if (empty($childrenIds)) {
            return false;
        }

        $children = $this->getRepository()->where(['id' => $childrenIds])->find();

        $inheritableFields = [];
        $inheritableLinks = [];
        foreach ($this->getRepository()->getInheritableFields() as $field) {
            if ($this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $field, 'type']) === 'linkMultiple') {
                $inheritableLinks[] = $field;
            } else {
                $inheritableFields[] = $field;
            }
        }

        foreach ($children as $child) {
            $this->getServiceFactory()->create($child->getEntityType())->prepareEntityForOutput($child);
            $unInheritedFields = array_diff($inheritableFields, $this->getInheritedFromParentFields($parent, $child));
            foreach ($unInheritedFields as $unInheritedField) {
                if ($child->get($unInheritedField) === null) {
                    try {
                        $this->inheritField($unInheritedField, $child->get('id'));
                    } catch (\Throwable $e) {
                        $GLOBALS['log']->error('Inherit field failed: ' . $e->getMessage());
                    }
                }
            }

            foreach ($inheritableLinks as $link) {
                try {
                    $this->inheritAllForLink($child->get('id'), $link);
                } catch (\Throwable $e) {
                    $GLOBALS['log']->error('Inherit all for link failed: ' . $e->getMessage());
                }
            }

            $this->dispatchEvent('inheritAllForChild', new Event(['parent' => $parent, 'child' => $child]));
        }

        return true;
    }

    public function getTreeDataForSelectedNode(string $id): array
    {
        $treeBranches = [];
        $this->createTreeBranches($this->getEntity($id), $treeBranches);

        if (empty($entity = $treeBranches[0])) {
            throw new NotFound();
        }

        $tree = [];
        $this->prepareTreeForSelectedNode($entity, $tree);
        $this->prepareTreeData($tree);

        $total = empty($tree[0]['total']) ? 0 : $tree[0]['total'];

        return ['total' => $total, 'list' => $tree];
    }

    public function getTreeData(array $ids): array
    {
        $tree = [];

        $treeBranches = [];
        foreach ($this->getRepository()->where(['id' => $ids])->find() as $entity) {
            $this->createTreeBranches($entity, $treeBranches);
        }

        if (!empty($treeBranches)) {
            foreach ($treeBranches as $entity) {
                $this->prepareTreeNode($entity, $tree, $ids);
            }
            $this->prepareTreeData($tree);
        }

        return ['total' => count($ids), 'tree' => $tree];
    }

    protected function prepareTreeData(array &$tree): void
    {
        $tree = array_values($tree);
        foreach ($tree as &$v) {
            if (!empty($v['children'])) {
                $this->prepareTreeData($v['children']);
            }
        }
    }

    protected function createTreeBranches(Entity $entity, array &$treeBranches): void
    {
        $parents = $entity->get('parents');
        if ($parents === null || count($parents) == 0) {
            $treeBranches[] = $entity;
        } else {
            foreach ($parents as $parent) {
                $parent->child = $entity;
                $this->createTreeBranches($parent, $treeBranches);
            }
        }
    }

    protected function prepareTreeNode($entity, array &$tree, array $ids): void
    {
        $tree[$entity->get('id')]['id'] = $entity->get('id');
        $tree[$entity->get('id')]['name'] = $entity->get('name');
        $tree[$entity->get('id')]['disabled'] = !in_array($entity->get('id'), $ids);
        if (!empty($entity->child)) {
            if (empty($tree[$entity->get('id')]['children'])) {
                $tree[$entity->get('id')]['children'] = [];
            }
            $this->prepareTreeNode($entity->child, $tree[$entity->get('id')]['children'], $ids);
        }
    }

    protected function prepareTreeForSelectedNode($entity, array &$tree, string $parentId = ''): void
    {
        $limit = $this->getConfig()->get('recordsPerPageSmall', 20);

        $position = $this->getRepository()->getEntityPosition($entity, $parentId);
        $index = $position - 1;

        $offset = $index - $limit;
        if ($offset < 0) {
            $offset = 0;
        }

        $children = $this->getChildren($parentId, ['offset' => $offset, 'maxSize' => $index - $offset + $limit]);
        if (!empty($children['list'])) {
            foreach ($children['list'] as $v) {
                $tree[$v['id']] = $v;
                $tree[$v['id']]['total'] = $children['total'];
                $tree[$v['id']]['disabled'] = false;
            }
        }

        if (!empty($entity->child)) {
            $tree[$entity->get('id')]['load_on_demand'] = false;
            if (empty($tree[$entity->get('id')]['children'])) {
                $tree[$entity->get('id')]['children'] = [];
            }
            $this->prepareTreeForSelectedNode($entity->child, $tree[$entity->get('id')]['children'], $entity->get('id'));
        }
    }

    public function inheritAllForLink(string $id, string $link): bool
    {
        $event = $this->dispatchEvent('beforeInheritAllForLink', new Event(['id' => $id, 'link' => $link]));

        $id = $event->getArgument('id');
        $link = $event->getArgument('link');

        if (empty($id) || empty($link)) {
            throw new BadRequest("'id' and 'link' is required parameters.");
        }

        $entity = $this->getRepository()->get($id);
        if (empty($entity)) {
            throw new NotFound();
        }

        if ($this->getMetadata()->get(['scopes', $entity->getEntityType(), 'type']) !== 'Hierarchy') {
            throw new BadRequest("Inheriting available only for entities type Hierarchy.");
        }

        if ($this->getMetadata()->get(['scopes', $entity->getEntityType(), 'disableHierarchy'], false)) {
            throw new BadRequest("Inheriting available on enable Hierarchy, hierarchy feature is disable.");
        }

        if (!$this->getMetadata()->get(['scopes', $entity->getEntityType(), 'relationInheritance'])) {
            throw new BadRequest("Relations inheriting is disabled.");
        }

        $relationName = $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'links', $link, 'relationName']);
        if (empty($relationName)) {
            return false;
        }

        $relationEntityName = ucfirst($relationName);

        if (in_array($link, $this->getRepository()->getUnInheritedRelations())) {
            return false;
        }

        $parentsIds = $entity->getLinkMultipleIdList('parents');
        if (empty($parentsIds[0])) {
            return false;
        }

        $keySet = $this->getRepository()->getMapper()->getKeys($entity, $link);

        $parentsCollection = $this->getEntityManager()->getRepository($relationEntityName)
            ->where([$keySet['nearKey'] => $parentsIds])
            ->find();

        if (!empty($parentsCollection[0])) {
            $additionalFields = $this->getAdditionalFieldsNames($entity->getEntityType(), $link);
            $maxMassLinkCount = $this->getConfig()->get('maxMassLinkCount', 20);
            foreach ($parentsCollection as $k => $parentItem) {
                $input = new \stdClass();
                $input->{$keySet['nearKey']} = $id;
                $input->{$keySet['distantKey']} = $parentItem->get($keySet['distantKey']);
                foreach ($additionalFields as $additionalField) {
                    $input->{$additionalField} = $parentItem->get($additionalField);
                }
                if ($k < $maxMassLinkCount) {
                    try {
                        $this->getServiceFactory()->create($relationEntityName)->createEntity($input);
                    } catch (NotUnique $e) {
                    } catch (Forbidden $e) {
                    }
                } else {
                    $this->getPseudoTransactionManager()->pushCreateEntityJob($relationEntityName, $input);
                }
            }
        }

        return $this->dispatchEvent('afterInheritAllForLink', new Event(['entity' => $entity, 'link' => $link, 'result' => true]))->getArgument('result');
    }

    public function inheritField(string $field, string $id): bool
    {
        $event = $this->dispatchEvent('beforeInheritField', new Event(['id' => $id, 'field' => $field]));

        $id = $event->getArgument('id');
        $field = $event->getArgument('field');

        if (in_array($field, $this->getRepository()->getUnInheritedFields())) {
            return false;
        }

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

            $fieldDefs = $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $field]);
            switch ($fieldDefs['type']) {
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
                case 'rangeInt':
                case 'rangeFloat':
                    $input->{$field . 'From'} = $parent->get($field . 'From');
                    $input->{$field . 'To'} = $parent->get($field . 'To');
                    if (!empty($fieldDefs['measureId'])) {
                        $input->{$field . 'UnitId'} = $parent->get($field . 'UnitId');
                    }
                    break;
                case 'linkMultiple':
                    $input->{$field . 'Ids'} = array_column($parent->get($field)->toArray(), 'id');
                    break;
                case 'varchar':
                    if (empty($fieldDefs['unitField'])) {
                        $input->$field = $parent->get($field);
                    } else {
                        $mainField = $fieldDefs['mainField'];
                        $input->$mainField = $parent->get($mainField);
                        $input->{$mainField . 'UnitId'} = $parent->get($mainField . 'UnitId');
                    }
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

    public function getChildren(string $parentId, array $params): array
    {
        $result = [];
        $selectParams = $this->getSelectParams($params);
        $records = $this->getRepository()->getChildrenArray($parentId, true, $params['offset'], $params['maxSize'], $selectParams);
        if (empty($records)) {
            return $result;
        }

        $offset = $params['offset'];
        $total = $this->getRepository()->getChildrenCount($parentId, $selectParams);
        $ids = [];
        foreach ($this->getRepository()->where(['id' => array_column($records, 'id')])->find() as $entity) {
            if ($this->getAcl()->check($entity, 'read')) {
                $ids[] = $entity->get('id');
            }
        }

        foreach ($records as $k => $record) {
            $result[] = [
                'id'             => $record['id'],
                'name'           => $record['name'],
                'offset'         => $offset + $k,
                'total'          => $total,
                'disabled'       => !in_array($record['id'], $ids),
                'load_on_demand' => !empty($record['childrenCount']) && $record['childrenCount'] > 0
            ];
        }

        return [
            'list'  => $result,
            'total' => $total
        ];
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
        if (
            $this->getMetadata()->get(['scopes', $this->entityType, 'type']) !== 'Hierarchy'
            || $this->getMetadata()->get(['scopes', $this->entityType, 'disableHierarchy'], false)
        ) {
            return parent::createEntity($attachment);
        }

        $this->prepareChildInputData($attachment);

        $entity = parent::createEntity($attachment);

        // run inherit all for child relations
        if ((!property_exists($attachment, '_duplicatingEntityId') || empty($attachment->_duplicatingEntityId)) && !empty($entity) && !empty($this->getMetadata()->get(['scopes', $entity->getEntityType(), 'relationInheritance']))) {
            foreach ($this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'links']) as $link => $linkDefs) {
                $relationName = $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'links', $link, 'relationName']);
                if (!empty($relationName) && !in_array($link, $this->getRepository()->getUnInheritedRelations())) {
                    $parentsIds = $entity->getLinkMultipleIdList('parents');
                    if (!empty($parentsIds[0])) {
                        $this->unlinkAll($entity->get('id'), $link);
                        $this->inheritAllForLink($entity->get('id'), $link);
                    }
                }
            }
        }

        return $entity;
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
        if ($this->getMetadata()->get(['scopes', $this->entityType, 'type']) !== 'Hierarchy'
            || $this->getMetadata()->get(['scopes', $this->entityType, 'disableHierarchy'], false)) {
            return parent::updateEntity($id, $data);
        }

        if (property_exists($data, '_sortedIds') && property_exists($data, '_id') && property_exists($data, '_link') && $data->_link === 'children') {
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

        $fetchedEntity = $this->getRepository()->get($id);

        $entityData = Util::arrayKeysToUnderScore($fetchedEntity->toArray());

        $result = parent::updateEntity($id, $data);

        $this->getRepository()->pushLinkMultipleFields($entityData);

        $this->createPseudoTransactionJobs($entityData, clone $data);

        return $result;
    }

    public function linkEntity($id, $link, $foreignId)
    {
        if ($this->getMetadata()->get(['scopes', $this->entityType, 'type']) !== 'Hierarchy'
            || $this->getMetadata()->get(['scopes', $this->entityType, 'disableHierarchy'], false)) {
            return parent::linkEntity($id, $link, $foreignId);
        }

        /**
         * Delegate to Update if ManyToOne or OneToOne relation
         */
        if ($this->getMetadata()->get(['entityDefs', $this->entityName, 'links', $link, 'type']) === 'belongsTo') {
            $data = new \stdClass();
            $data->{"{$link}Id"} = $foreignId;
            try {
                $this->updateEntity($id, $data);
            } catch (NotModified $e) {
                // ignore
            }

            return true;
        }

        /**
         * Delegate to Update if OneToMany relation
         */
        if (!empty($linkData = $this->getOneToManyRelationData($link))) {
            $data = new \stdClass();
            $data->{"{$linkData['foreign']}Id"} = $id;
            try {
                $this->getServiceFactory()->create($linkData['entity'])->updateEntity($foreignId, $data);
            } catch (NotModified $e) {
                // ignore
            }

            return true;
        }

        if (empty($this->getMetadata()->get(['scopes', $this->entityType, 'relationInheritance']))) {
            return parent::linkEntity($id, $link, $foreignId);
        }

        if ($this->isPseudoTransaction()) {
            return parent::linkEntity($id, $link, $foreignId);
        }

        $result = parent::linkEntity($id, $link, $foreignId);
        $this->createPseudoTransactionLinkJobs($id, $link, $foreignId);

        return $result;
    }

    public function unlinkEntity($id, $link, $foreignId)
    {
        if ($this->getMetadata()->get(['scopes', $this->entityType, 'type']) !== 'Hierarchy'
            || $this->getMetadata()->get(['scopes', $this->entityType, 'disableHierarchy'], false)) {
            return parent::unlinkEntity($id, $link, $foreignId);
        }

        /**
         * Delegate to Update if ManyToOne or OneToOne relation
         */
        if ($this->getMetadata()->get(['entityDefs', $this->entityName, 'links', $link, 'type']) === 'belongsTo') {
            $data = new \stdClass();
            $data->{"{$link}Id"} = null;
            try {
                $this->updateEntity($id, $data);
            } catch (NotModified $e) {
                // ignore
            }

            return true;
        }

        /**
         * Delegate to Update if OneToMany relation
         */
        if (!empty($linkData = $this->getOneToManyRelationData($link))) {
            $data = new \stdClass();
            $data->{"{$linkData['foreign']}Id"} = null;
            try {
                $this->getServiceFactory()->create($linkData['entity'])->updateEntity($foreignId, $data);
            } catch (NotModified $e) {
                // ignore
            }

            return true;
        }

        if (empty($this->getMetadata()->get(['scopes', $this->entityType, 'relationInheritance']))) {
            return parent::unlinkEntity($id, $link, $foreignId);
        }

        if ($this->isPseudoTransaction()) {
            return parent::unlinkEntity($id, $link, $foreignId);
        }

        $result = parent::unlinkEntity($id, $link, $foreignId);
        $this->createPseudoTransactionUnlinkJobs($id, $link, $foreignId);

        return $result;
    }

    public function deleteEntity($id)
    {
        if ($this->getMetadata()->get(['scopes', $this->entityType, 'type']) !== 'Hierarchy'
            || $this->getMetadata()->get(['scopes', $this->entityType, 'disableHierarchy'], false)) {
            return parent::deleteEntity($id);
        }

        $inTransaction = false;
        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getEntityManager()->getPDO()->beginTransaction();
            $inTransaction = true;
        }
        try {
            $result = parent::deleteEntity($id);
            foreach ($this->getRepository()->getChildrenRecursivelyArray($id) as $childId) {
                parent::deleteEntity($childId);
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

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);
        $attributeList = empty($selectParams['select']) ? [] : $selectParams['select'];

        if (count($collection) > 0 && $collection[0]->hasAttribute('isRoot') && $collection[0]->hasAttribute('hasChildren')) {
            $ids = array_column($collection->toArray(), 'id');

            if (in_array('isRoot', $attributeList)) {
                $roots = $this->getRepository()->getEntitiesParents($ids);
                foreach ($collection as $entity) {
                    $entity->set('isRoot', $this->getRepository()->isRoot($entity->get('id'), $roots));
                }
            }

            if (in_array('hasChildren', $attributeList)) {
                $children = $this->getRepository()->getEntitiesChildren($ids);
                foreach ($collection as $entity) {
                    $entity->set('hasChildren', $this->getRepository()->hasChildren($entity->get('id'), $children));
                }
            }

            foreach ($collection as $entity) {
                if (in_array('hierarchyRoute', $attributeList) && $this->getMetadata()->get(['scopes', $this->entityType, 'multiParents']) !== true) {
                    $entity->set('hierarchyRoute', $this->getRepository()->getHierarchyRoute($entity->get('id')));
                }
                $entity->_skipHierarchyRoute = true;
            }
        }
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if ($this->getMetadata()->get(['scopes', $this->entityType, 'type']) !== 'Hierarchy'
            || $this->getMetadata()->get(['scopes', $this->entityType, 'disableHierarchy'], false)) {
            return;
        }

        if (empty($this->getMemoryStorage()->get('exportJobId'))) {
            if (empty($entity->_skipHierarchyRoute)) {
                if (!$entity->has('isRoot')) {
                    $entity->set('isRoot', $this->getRepository()->isRoot($entity->get('id')));
                }
                if (!$entity->has('hasChildren')) {
                    $entity->set('hasChildren', $this->getRepository()->hasChildren($entity->get('id')));
                }
                if ($this->getMetadata()->get(['scopes', $this->entityType, 'multiParents']) !== true) {
                    $entity->set('hierarchyRoute', $this->getRepository()->getHierarchyRoute($entity->get('id')));
                }
            }

            if ($this->getMetadata()->get(['scopes', $this->getEntityType(), 'type']) == 'Hierarchy') {
                $hierarchySortOrderField = $this->getHierarchySortOrderFieldName();
                if ($entity->has($hierarchySortOrderField)) {
                    $entity->set('sortOrder', $entity->get($hierarchySortOrderField));
                }
            }
        }
    }

    public function findLinkedEntities($id, $link, $params)
    {
        $result = parent::findLinkedEntities($id, $link, $params);

        $this->markInheritedRecords($result, $id, $link);

        return $result;
    }

    public function markInheritedRecords(array &$result, string $id, string $link): void
    {
        if (!empty($this->getMemoryStorage()->get('exportJobId'))) {
            return;
        }

        if ($this->getMetadata()->get(['scopes', $this->entityType, 'type']) !== 'Hierarchy'
            || $this->getMetadata()->get(['scopes', $this->entityType, 'disableHierarchy'], false)) {
            return;
        }

        if (empty($this->getMetadata()->get(['scopes', $this->entityType, 'relationInheritance']))) {
            return;
        }

        if (in_array($link, $this->getRepository()->getUnInheritedRelations())) {
            return;
        }

        if (empty($result['collection'][0])) {
            return;
        }

        $entity = $this->getRepository()->get($id);
        if (empty($entity)) {
            return;
        }

        $relationName = $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'links', $link, 'relationName']);
        if (empty($relationName)) {
            return;
        }

        $parentsIds = $entity->getLinkMultipleIdList('parents');
        if (empty($parentsIds)) {
            return;
        }

        $relationEntityName = ucfirst($relationName);
        $ids = array_column($result['collection']->toArray(), 'id');
        $keySet = $this->getRepository()->getMapper()->getKeys($entity, $link);

        $parentsCollection = $this->getEntityManager()->getRepository($relationEntityName)
            ->where([
                $keySet['nearKey']    => $parentsIds,
                $keySet['distantKey'] => $ids
            ])
            ->find();

        $parentsDistantIds = array_column($parentsCollection->toArray(), $keySet['distantKey']);

        $itemCollection = $this->getEntityManager()->getRepository($relationEntityName)
            ->where([
                $keySet['nearKey']    => $entity->get('id'),
                $keySet['distantKey'] => $ids
            ])
            ->find();

        $additionalFields = $this->getAdditionalFieldsNames($entity->getEntityType(), $link);

        $skipIds = [];
        foreach ($itemCollection as $item) {
            if (!in_array($item->get($keySet['distantKey']), $parentsDistantIds)) {
                $skipIds[] = $item->get($keySet['distantKey']);
                continue;
            }

            foreach ($parentsCollection as $parentItem) {
                if ($parentItem->get($keySet['distantKey']) !== $item->get($keySet['distantKey'])) {
                    continue;
                }
                foreach ($additionalFields as $additionalFieldName) {
                    if ($item->get($additionalFieldName) !== $parentItem->get($additionalFieldName)) {
                        $skipIds[] = $item->get($keySet['distantKey']);
                        break;
                    }
                }
            }
        }

        foreach ($result['collection'] as $item) {
            $item->isInherited = !in_array($item->get('id'), $skipIds);
        }
    }

    protected function getAdditionalFieldsNames(string $entityType, string $link): array
    {
        $relationName = $this->getMetadata()->get(['entityDefs', $entityType, 'links', $link, 'relationName']);

        return $this
            ->getEntityManager()
            ->getRepository(ucfirst($relationName))
            ->getAdditionalFieldsNames();
    }

    protected function duplicateParents($entity, $duplicatingEntity): void
    {
        $defs = $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'links', 'parents'], []);

        if (!array_key_exists('relationName', $defs) || empty($defs['relationName'])) {
            return;
        }

        $relationEntityName = ucfirst($defs['relationName']);

        $children = $this->getEntityManager()->getRepository($relationEntityName)->where([
            'entityId' => $duplicatingEntity->get('id')
        ])->find();

        if (count($children) > 0) {
            $service = $this->getInjection('serviceFactory')->create($relationEntityName);

            foreach ($children->toArray() as $child) {
                $data = $service->getDuplicateAttributes($child['id']);
                $data->entityId = $entity->get('id');

                $service->createEntity($data);
            }
        }
    }

    protected function duplicateChildren($entity, $duplicatingEntity): void
    {
        // ignore duplicating for link 'children'
    }

    protected function afterUpdateEntity(Entity $entity, $data)
    {
        $entity->set('inheritedFields', $this->getInheritedFields($entity));
    }

    public function createPseudoTransactionJobs(array $parent, \stdClass $data, string $parentTransactionId = null): void
    {
        $children = $this->getRepository()->getChildrenArray($parent['id']);
        foreach ($children as $childArray) {
            $child = $this->getRepository()->get();
            $child->set($childArray);
            $childData = Util::arrayKeysToUnderScore($child->toArray());
            $this->getRepository()->pushLinkMultipleFields($childData);
            $inputData = $this->createInputDataForPseudoTransactionJob($parent, $childData, clone $data);
            if (!empty((array)$inputData)) {
                $inputData->_fieldValueInheritance = true;
                $transactionId = $this->getPseudoTransactionManager()->pushUpdateEntityJob($this->entityType, $childData['id'], $inputData, $parentTransactionId);
                if ($childArray['childrenCount'] > 0) {
                    $this->createPseudoTransactionJobs($childData, clone $inputData, $transactionId);
                }
            }
        }
    }

    public function createPseudoTransactionLinkJobs(string $id, string $link, string $foreignId, string $parentTransactionId = null): void
    {
        if (empty($this->getMetadata()->get(['scopes', $this->entityType, 'relationInheritance']))) {
            return;
        }

        if (in_array($link, $this->getRepository()->getUnInheritedRelations())) {
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

    public function createPseudoTransactionUnlinkJobs(string $id, string $link, string $foreignId, string $parentTransactionId = null): void
    {
        if (in_array($link, $this->getRepository()->getUnInheritedRelations())) {
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

            $parentValue = $parent[$underScoredField];
            $childValue = $child[$underScoredField];

            if ($this->areValuesEqual($this->getRepository()->get(), $field, $parentValue, $childValue)) {
                $inputData->$field = $value;
            }
        }

        return $inputData;
    }

    protected function getInheritedFromParentFields(Entity $parent, Entity $child): array
    {
        $inheritedFields = [];
        foreach ($this->getRepository()->getInheritableFields() as $field) {
            $fieldDefs = $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', $field]);
            switch ($fieldDefs['type']) {
                case 'asset':
                case 'image':
                case 'link':
                    if ($this->areValuesEqual($this->getRepository()->get(), $field . 'Id', $parent->get($field . 'Id'), $child->get($field . 'Id'))) {
                        $inheritedFields[] = $field;
                    }
                    break;
                case 'linkMultiple':
                    if (!in_array($field, $this->getRepository()->getUnInheritedFields())) {
                        $parentIds = $parent->getLinkMultipleIdList($field);
                        sort($parentIds);
                        $entityIds = $child->getLinkMultipleIdList($field);
                        sort($entityIds);
                        if ($this->areValuesEqual($this->getRepository()->get(), $field . 'Ids', $parentIds, $entityIds)) {
                            $inheritedFields[] = $field;
                        }
                    }
                    break;
                default:
                    if ($this->areValuesEqual($this->getRepository()->get(), $field, $parent->get($field), $child->get($field))) {
                        $inheritedFields[] = $field;
                    }
                    break;
            }
        }

        return $inheritedFields;
    }

    protected function getInheritedFields(Entity $entity): array
    {
        // exit if children link does not exist
        if (!$this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'links', 'children'])) {
            return [];
        }

        $parents = $this->getRepository()
            ->join('children')
            ->where(['children.id' => $entity->get('id')])
            ->find();

        if (empty($parents[0])) {
            return [];
        }


        $inheritedFields = [];
        foreach ($parents as $parent) {
            $this->getServiceFactory()->create($parent->getEntityType())->prepareEntityForOutput($parent);
            $inheritedFields = array_merge($inheritedFields, $this->getInheritedFromParentFields($parent, $entity));
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
