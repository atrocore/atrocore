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

namespace Espo\ORM\Repositories;

use Espo\Core\Utils\Json;
use Espo\Core\Utils\Util;
use Espo\ORM\EntityManager;
use Espo\ORM\EntityFactory;
use Espo\ORM\EntityCollection;
use Espo\ORM\Entity;
use Espo\Core\Exceptions\Forbidden;
use Symfony\Component\Workflow\Exception\LogicException;


class RDB extends \Espo\ORM\Repository
{
    /**
     * @var Object Mapper.
     */
    protected $mapper;

    /**
     * @var array Where clause array. To be used in further find operation.
     */
    protected $whereClause = [];

    /**
     * @var array Having clause array.
     */
    protected $havingClause = [];

    /**
     * @var array Parameters to be used in further find operations.
     */
    protected $listParams = [];

    public function __construct($entityType, EntityManager $entityManager, EntityFactory $entityFactory)
    {
        $this->entityType = $entityType;
        $this->entityName = $entityType;

        $this->entityFactory = $entityFactory;
        $this->seed = $this->entityFactory->create($entityType);
        $this->entityClassName = is_object($this->seed) ? get_class($this->seed) : null;
        $this->entityManager = $entityManager;
    }

    protected function getMapper()
    {
        if (empty($this->mapper)) {
            $this->mapper = $this->getEntityManager()->getMapper('RDB');
        }
        return $this->mapper;
    }

    public function handleSelectParams(&$params)
    {
    }

    protected function getEntityFactory()
    {
        return $this->entityFactory;
    }

    protected function getEntityManager()
    {
        return $this->entityManager;
    }

    public function reset()
    {
        $this->whereClause = [];
        $this->havingClause = [];
        $this->listParams = [];
    }

    protected function getNewEntity()
    {
        $entity = $this->entityFactory->create($this->entityType);
        if ($entity) {
            $entity->setIsNew(true);
            $entity->populateDefaults();
            return $entity;
        }
    }

    protected function getEntityById($id)
    {
        $entity = $this->entityFactory->create($this->entityType);

        if (!$entity) return null;

        $params = [];
        $this->handleSelectParams($params);
        if ($this->getMapper()->selectById($entity, $id, $params)) {
            return $entity;
        }

        return null;
    }

    public function get($id = null)
    {
        if (empty($id)) {
            return $this->getNewEntity();
        }
        return $this->getEntityById($id);
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
    }

    public function save(Entity $entity, array $options = [])
    {
        $entity->setAsBeingSaved();

        if (empty($options['skipBeforeSave']) && empty($options['skipAll'])) {
            $this->beforeSave($entity, $options);
        }
        if ($entity->isNew() && !$entity->isSaved()) {
            // check workflow init states if it needs
            $this->workflowInitStates($entity);

            $result = $this->getMapper()->insert($entity);
        } else {
            // run workflow method "can()" if it needs
            $this->workflowCan($entity);

            $result = $this->getMapper()->update($entity);

            if ($result) {
                // run workflow method "apply()" if it needs
                $this->workflowApply($entity);
            }
        }
        if ($result) {
            $entity->setIsSaved(true);

            if (empty($options['skipAfterSave']) && empty($options['skipAll'])) {
                $this->afterSave($entity, $options);
            }
            if ($entity->isNew()) {
                if (empty($options['keepNew'])) {
                    $entity->setIsNew(false);
                }
            } else {
                if ($entity->isFetched()) {
                    $entity->updateFetchedValues();
                }
            }
        }
        $entity->setAsNotBeingSaved();

        return $result;
    }

    /**
     * @param Entity $entity
     * @param array $options
     */
    protected function beforeRemove(Entity $entity, array $options = [])
    {
        $uniques = $this->getUniqueFields($entity);

        if (!empty($uniques)) {
            $dbTable = Util::toUnderScore($entity->getEntityType());

            foreach ($uniques as $key => $unique) {
                if (is_array($unique)) {
                    $sqlCondition = [];
                    foreach ($unique as $field) {
                        $value = $entity->get($field);

                        $sqlCondition[] = $this->prepareWhereConditions($field, $value);
                    }
                    $uniques[$key] = '(' . implode(' AND ', $sqlCondition) . ')';
                } else {
                    $value = $entity->get($unique);

                    $uniques[$key] = $this->prepareWhereConditions($unique, $value);
                }
            }

            $where = implode(' OR ', $uniques);

            $this
                ->getEntityManager()
                ->nativeQuery("DELETE FROM `$dbTable` WHERE deleted=1 AND id!='$entity->id' AND ($where)");
        }
    }

    /**
     * @param string $field
     * @param $value
     *
     * @return string
     */
    protected function prepareWhereConditions(string $field, $value): string
    {
        if (is_null($value)) {
            $result = "`" . Util::toUnderScore($field) . "` IS NULL";
        } else {
            $result = "`" . Util::toUnderScore($field) . "`=" . $this->getPDO()->quote($value);
        }

        return $result;
    }

    /**
     * @return array
     */
    protected function getUniqueFields(Entity $entity): array
    {
        $result = [];

        $metadata = $this->getEntityManager()->getEspoMetadata();

        foreach ($metadata->get(['entityDefs', $entity->getEntityType(), 'fields'], []) as $field => $defs) {
            if (!empty($defs['unique'])) {
                $actualFields = $metadata->get(['fields', $defs['type'], 'actualFields'], []);

                if (!empty($actualFields)) {
                    $actualUniqueFields = [];

                    foreach ($actualFields as $actualField) {
                        $actualUniqueFields[] = $field . ucfirst($actualField);
                    }

                    $result[] = $actualUniqueFields;
                } else {
                    $result[] = $field;
                }
            }
        }

        return $result;
    }

    /**
     * @param Entity $entity
     * @param array  $options
     */
    protected function afterRemove(Entity $entity, array $options = [])
    {
    }

    protected function deleteLinkedRelationshipEntities(Entity $entity): void
    {
        foreach ($this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'links'], []) as $link => $linkDefs) {
            if (!empty($linkDefs['entity']) && !empty($linkDefs['foreign'])) {
                if (!empty($this->getMetadata()->get(['entityDefs', $linkDefs['entity'], 'fields', $linkDefs['foreign'], 'relationshipField']))) {
                    $this
                        ->getEntityManager()
                        ->getRepository($linkDefs['entity'])
                        ->where([
                            $linkDefs['foreign'] . 'Id' => $entity->get('id')
                        ])
                        ->removeCollection();
                }
            }
        }
    }

    public function remove(Entity $entity, array $options = [])
    {
        $this->beforeRemove($entity, $options);
        $result = $this->getMapper()->delete($entity);
        if ($result) {
            $this->deleteLinkedRelationshipEntities($entity);
            $this->afterRemove($entity, $options);
        }
        return $result;
    }

    public function deleteFromDb($id, $onlyDeleted = false)
    {
        return $this->getMapper()->deleteFromDb($this->entityType, $id, $onlyDeleted);
    }

    /**
     * @param array $params
     * @return EntityCollection
     */
    public function find(array $params = [])
    {
        $params = $this->getSelectParams($params);

        if (empty($params['skipAdditionalSelectParams'])) {
            $this->handleSelectParams($params);
        }

        $dataArr = !empty($this->seed) ? $this->getMapper()->select($this->seed, $params) : [];

        $collection = new EntityCollection($dataArr, $this->entityType, $this->entityFactory);
        $collection->setAsFetched();

        $this->reset();

        return $collection;
    }

    /**
     * Remove collection of entities
     *
     * @param array $options
     */
    public function removeCollection(array $options = [])
    {
        // get collection
        $collection = $this->find();

        if (count($collection) > 0) {
            foreach ($collection as $item) {
                $this->remove($item, $options);
            }
        }
    }

    /**
     * @param array $params
     * @return Entity|null
     */
    public function findOne(array $params = [])
    {
        $collection = $this->limit(0, 1)->find($params);
        if (count($collection)) {
            return $collection[0];
        }
        return null;
    }

    public function findByQuery($sql)
    {
        $dataArr = $this->getMapper()->selectByQuery($this->seed, $sql);

        $collection = new EntityCollection($dataArr, $this->entityType, $this->entityFactory);
        $this->reset();

        return $collection;
    }

    public function findRelated(Entity $entity, $relationName, array $params = [])
    {
        if (!$entity->id) {
            return [];
        }

        $relationType = $entity->getRelationType($relationName);

        if ($relationType === Entity::BELONGS_TO_PARENT) {
            $entityType = $entity->get($relationName . 'Type');
        } else {
            $entityType = $entity->getRelationParam($relationName, 'entity');
        }

        /**
         * Set default sort order
         */
        if (empty($params['orderBy'])) {
            $defaultOrderBy = $this->getMetadata()->get(['clientDefs', $entity->getEntityType(), 'relationshipPanels', $relationName, 'sortBy']);
            if (!empty($defaultOrderBy)) {
                $defaultOrderByAsc = !empty($this->getMetadata()->get(['clientDefs', $entity->getEntityType(), 'relationshipPanels', $relationName, 'asc']));
                $params['orderBy'] = $defaultOrderBy;
                $params['order'] = $defaultOrderByAsc ? 'ASC' : 'DESC';
            }
        }

        /**
         * Set additional relation columns
         */
        if (empty($params['additionalColumns'])) {
            $additionalColumns = $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'links', $relationName, 'additionalColumns']);
            if (!empty($additionalColumns)) {
                foreach ($additionalColumns as $column => $columnData) {
                    $params['additionalColumns'][$column] = $column;
                }
            }
        }

        if ($entityType) {
            if (empty($params['skipAdditionalSelectParams'])) {
                $this->getEntityManager()->getRepository($entityType)->handleSelectParams($params);
            }
        }

        $result = $this->getMapper()->selectRelated($entity, $relationName, $params);

        if (is_array($result)) {
            $collection = new EntityCollection($result, $entityType, $this->entityFactory);
            $collection->setAsFetched();
            return $collection;
        } elseif ($result instanceof EntityCollection) {
            return $result;
        } elseif ($result instanceof Entity) {
            if (!empty($params['collectionOnly'])) {
                $collection = new EntityCollection([$result], $entityType, $this->entityFactory);
                $collection->setAsFetched();
                return $collection;
            } else {
                return $result;
            }
        }

        if ($relationType === Entity::HAS_MANY && $entityType) {
            return new EntityCollection([], $entityType, $this->entityFactory);
        }

        return $result;
    }

    public function countRelated(Entity $entity, $relationName, array $params = [])
    {
        if (!$entity->id) {
            return;
        }
        $entityType = $entity->relations[$relationName]['entity'];
        if (empty($params['skipAdditionalSelectParams'])) {
            $this->getEntityManager()->getRepository($entityType)->handleSelectParams($params);
        }

        return intval($this->getMapper()->countRelated($entity, $relationName, $params));
    }

    public function isRelated(Entity $entity, $relationName, $foreign)
    {
        if (!$entity->id) {
            return;
        }

        if ($foreign instanceof Entity) {
            $id = $foreign->id;
        } else if (is_string($foreign)) {
            $id = $foreign;
        } else {
            return;
        }

        if (!$id) return;

        return !!$this->countRelated($entity, $relationName, array(
            'whereClause' => array(
                'id' => $id
            )
        ));
    }

    public function relate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
        if (!$entity->id) {
            return;
        }

        $this->beforeRelate($entity, $relationName, $foreign, $data, $options);
        $beforeMethodName = 'beforeRelate' . ucfirst($relationName);
        if (method_exists($this, $beforeMethodName)) {
            $this->$beforeMethodName($entity, $foreign, $data, $options);
        }

        $result = false;
        $methodName = 'relate' . ucfirst($relationName);
        if (method_exists($this, $methodName)) {
            $result = $this->$methodName($entity, $foreign, $data, $options);
        } else {
            $d = $data;
            if ($d instanceof \stdClass) {
                $d = get_object_vars($d);
            }
            if ($foreign instanceof Entity) {
                $result = $this->getMapper()->relate($entity, $relationName, $foreign, $d);
            }
            if (is_string($foreign)) {
                $result = $this->getMapper()->addRelation($entity, $relationName, $foreign, null, $d);
            }
        }

        if ($result) {
            $this->afterRelate($entity, $relationName, $foreign, $data, $options);
            $afterMethodName = 'afterRelate' . ucfirst($relationName);
            if (method_exists($this, $afterMethodName)) {
                $this->$afterMethodName($entity, $foreign, $data, $options);
            }
        }

        return $result;
    }

    public function updateRelationData(string $relationName, array $setData, string $re1, string $re1Id, string $re2, string $re2Id): void
    {
        $setPart = [];
        foreach ($setData as $field => $value) {
            if (is_array($value)) {
                $value = Json::encode($value);
            }
            $setPart[] = Util::toUnderScore($field) . '=' . $this->getMapper()->quote($value);
        }

        if (empty($setPart)) {
            return;
        }

        $query = "UPDATE `" . Util::toUnderScore($relationName) . "` SET " . implode(',', $setPart) . " WHERE deleted=0";
        $query .= " AND " . Util::toUnderScore(lcfirst($re1)) . "=" . $this->getPDO()->quote($re1Id);
        $query .= " AND " . Util::toUnderScore(lcfirst($re2)) . "=" . $this->getPDO()->quote($re2Id);

        $this->getPDO()->exec($query);
    }

    public function unrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
        if (!$entity->id) {
            return;
        }

        $this->beforeUnrelate($entity, $relationName, $foreign, $options);
        $beforeMethodName = 'beforeUnrelate' . ucfirst($relationName);
        if (method_exists($this, $beforeMethodName)) {
            $this->$beforeMethodName($entity, $foreign, $options);
        }

        $result = false;
        $methodName = 'unrelate' . ucfirst($relationName);
        if (method_exists($this, $methodName)) {
            $result = $this->$methodName($entity, $foreign, $options);
        } else {
            if ($foreign instanceof Entity) {
                $result = $this->getMapper()->unrelate($entity, $relationName, $foreign, true);
            }
            if (is_string($foreign)) {
                $result = $this->getMapper()->removeRelation($entity, $relationName, $foreign, false, null, true);
            }
            if ($foreign === true) {
                $result = $this->getMapper()->removeAllRelations($entity, $relationName, true);
            }
        }

        if ($result) {
            $this->afterUnrelate($entity, $relationName, $foreign, $options);
            $afterMethodName = 'afterUnrelate' . ucfirst($relationName);
            if (method_exists($this, $afterMethodName)) {
                $this->$afterMethodName($entity, $foreign, $options);
            }
        }

        return $result;
    }

    /**
     * @param Entity $entity
     * @param string $relationName
     * @param mixed  $foreign
     * @param array  $options
     *
     * @return false|void
     */
    public function unrelateForce(Entity $entity, $relationName, $foreign, array $options = [])
    {
        $options['force'] = true;

        return $this->unrelate($entity, $relationName, $foreign, $options);
    }

    protected function beforeRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
    }

    protected function afterRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
    }

    protected function beforeUnrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
    }

    protected function afterUnrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
    }

    protected function beforeMassRelate(Entity $entity, $relationName, array $params = [], array $options = [])
    {
    }

    protected function afterMassRelate(Entity $entity, $relationName, array $params = [], array $options = [])
    {
    }

    public function updateRelation(Entity $entity, $relationName, $foreign, $data)
    {
        if (!$entity->id) {
            return;
        }
        if ($data instanceof \stdClass) {
            $data = get_object_vars($data);
        }
        if ($foreign instanceof Entity) {
            $id = $foreign->id;
        } else {
            $id = $foreign;
        }
        if (is_string($foreign)) {
            return $this->getMapper()->updateRelation($entity, $relationName, $id, $data);
        }
        return null;
    }

    public function massRelate(Entity $entity, $relationName, array $params = [], array $options = [])
    {
        if (!$entity->id) {
            return;
        }
        $this->beforeMassRelate($entity, $relationName, $params, $options);

        $result = $this->getMapper()->massRelate($entity, $relationName, $params);
        if ($result) {
            $this->afterMassRelate($entity, $relationName, $params, $options);
        }
        return $result;
    }

    public function getAll()
    {
        $this->reset();
        return $this->find();
    }

    public function count(array $params = [])
    {
        if (empty($params['skipAdditionalSelectParams'])) {
            $this->handleSelectParams($params);
        }

        $params = $this->getSelectParams($params);
        $count = $this->getMapper()->count($this->seed, $params);
        $this->reset();
        return intval($count);
    }

    public function max($field)
    {
        $params = $this->getSelectParams();
        return $this->getMapper()->max($this->seed, $params, $field);
    }

    public function min($field)
    {
        $params = $this->getSelectParams();
        return $this->getMapper()->min($this->seed, $params, $field);
    }

    public function sum($field)
    {
        $params = $this->getSelectParams();
        return $this->getMapper()->sum($this->seed, $params, $field);
    }

    public function join()
    {
        $args = func_get_args();

        if (empty($this->listParams['joins'])) {
            $this->listParams['joins'] = [];
        }

        foreach ($args as &$param) {
            if (is_array($param)) {
                foreach ($param as $k => $v) {
                    $this->listParams['joins'][] = $v;
                }
            } else {
                $this->listParams['joins'][] = $param;
            }
        }

        return $this;
    }

    public function leftJoin()
    {
        $args = func_get_args();

        if (empty($this->listParams['leftJoins'])) {
            $this->listParams['leftJoins'] = [];
        }

        foreach ($args as &$param) {
            if (is_array($param)) {
                foreach ($param as $k => $v) {
                    $this->listParams['leftJoins'][] = $v;
                }
            } else {
                $this->listParams['leftJoins'][] = $param;
            }
        }

        return $this;
    }

    public function distinct()
    {
        $this->listParams['distinct'] = true;
        return $this;
    }

    public function where($param1 = [], $param2 = null)
    {
        if (is_array($param1)) {
            $this->whereClause = $param1 + $this->whereClause;

        } else {
            if (!is_null($param2)) {
                $this->whereClause[$param1] = $param2;
            }
        }

        return $this;
    }

    public function having($param1 = [], $param2 = null)
    {
        if (is_array($param1)) {
            $this->havingClause = $param1 + $this->havingClause;
        } else {
            if (!is_null($param2)) {
                $this->havingClause[$param1] = $param2;
            }
        }

        return $this;
    }

    public function order($field = 'id', $direction = "ASC")
    {
        $this->listParams['orderBy'] = $field;
        $this->listParams['order'] = $direction;

        return $this;
    }

    public function limit($offset, $limit)
    {
        $this->listParams['offset'] = $offset;
        $this->listParams['limit'] = $limit;

        return $this;
    }

    public function select($select)
    {
        $this->listParams['select'] = $select;
        return $this;
    }

    public function groupBy($groupBy)
    {
        $this->listParams['groupBy'] = $groupBy;
        return $this;
    }

    public function setListParams(array $params = [])
    {
        $this->listParams = $params;
    }

    public function getListParams()
    {
        return $this->listParams;
    }

    protected function getSelectParams(array $params = [])
    {
        if (isset($params['whereClause'])) {
            $params['whereClause'] = $params['whereClause'];
            if (!empty($this->whereClause)) {
                $params['whereClause'][] = $this->whereClause;
            }
        } else {
            $params['whereClause'] = $this->whereClause;
        }
        if (!empty($params['havingClause'])) {
            $params['havingClause'] = $params['havingClause'];
            if (!empty($this->havingClause)) {
                $params['havingClause'][] = $this->havingClause;
            }
        } else {
            $params['havingClause'] = $this->havingClause;
        }

        if (!empty($params['leftJoins']) && !empty($this->listParams['leftJoins'])) {
            foreach ($this->listParams['leftJoins'] as $j) {
                $params['leftJoins'][] = $j;
            }
        }

        if (!empty($params['joins']) && !empty($this->listParams['joins'])) {
            foreach ($this->listParams['joins'] as $j) {
                $params['joins'][] = $j;
            }
        }

        $withDeleted = !empty($params['withDeleted']);
        $params = array_replace_recursive($this->listParams, $params);
        $params['withDeleted'] = $withDeleted;

        return $params;
    }

    protected function getPDO()
    {
        return $this->getEntityManager()->getPDO();
    }

    /**
     * Check workflow init states if it needs
     *
     * @param Entity $entity
     *
     * @throws Forbidden
     */
    protected function workflowInitStates(Entity $entity): void
    {
        // get workflow settings
        $workflowSettings = $this->getEntityManager()->getEspoMetadata()->get(['workflow', $entity->getEntityType()], []);

        if (!empty($workflowSettings)) {
            foreach ($workflowSettings as $field => $settings) {
                if (!empty($settings['initStates']) && !in_array($entity->get($field), $settings['initStates'])) {
                    throw new Forbidden(
                        sprintf(
                            'Init state "%s" is not defined for workflow "%s".',
                            $entity->get($field),
                            $entity->getEntityType() . '_' . $field
                        )
                    );
                }
            }
        }
    }

    /**
     * Run workflow method "can()" if it needs
     *
     * @param Entity $to
     *
     * @throws Forbidden
     */
    protected function workflowCan(Entity $to): void
    {
        // workflow check only for not new items
        if (!$to->isNew()) {
            // prepare name
            $name = $to->getEntityType();

            // get workflow settings
            $workflowSettings = $this->getEntityManager()->getEspoMetadata()->get(['workflow', $name], []);

            // make clone of Entity for "from" place
            $from = clone $to;

            if (!empty($workflowSettings)) {
                foreach ($workflowSettings as $field => $settings) {
                    // set fetched value for "from" place
                    $from->set([$field => $to->getFetched($field)]);

                    if ($from->get($field) != $to->get($field)) {
                        try {
                            $can = $this
                                ->getInjection('workflow')
                                ->get($from, $name . '_' . $field)
                                ->can($from, $from->get($field) . '_' . $to->get($field));
                        } catch (LogicException $e) {
                            throw new Forbidden($e->getMessage());
                        }

                        // if transition can not be applied
                        if (!$can) {
                            // try to find blockers
                            try {
                                $transitionBlockerList = $this
                                    ->getInjection('workflow')
                                    ->get($from, $name . '_' . $field)
                                    ->buildTransitionBlockerList($from, $from->get($field) . '_' . $to->get($field));
                            } catch (LogicException $e) {
                                throw new Forbidden($e->getMessage());
                            }
                            if (!empty($transitionBlockerList)) {
                                // if there are blockers then show blocker reason
                                foreach ($transitionBlockerList as $transitionBlocker) {
                                    throw new Forbidden($transitionBlocker->getMessage());
                                }
                            } else {
                                // if there aren't blockers then show transition error
                                throw new Forbidden(sprintf(
                                    'Transition "%s" is not defined for workflow "%s".',
                                    $from->get($field) . '_' . $to->get($field),
                                    $name . '_' . $field));
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Run workflow method "apply()" if it needs
     *
     * @param Entity $to
     *
     * @throws Forbidden
     */
    protected function workflowApply(Entity $to): void
    {
        // workflow check only for not new items
        if (!$to->isNew()) {
            // prepare name
            $name = $to->getEntityType();

            // get workflow settings
            $workflowSettings = $this->getEntityManager()->getEspoMetadata()->get(['workflow', $name], []);

            // make clone of Entity for "from" place
            $from = clone $to;

            if (!empty($workflowSettings)) {
                foreach ($workflowSettings as $field => $settings) {
                    // set fetched value for "from" place
                    $from->set([$field => $to->getFetched($field)]);

                    if ($from->get($field) != $to->get($field)) {
                        try {
                            $this
                                ->getInjection('workflow')
                                ->get($from, $name . '_' . $field)
                                ->apply($from, $from->get($field) . '_' . $to->get($field));
                        } catch (LogicException $e) {
                            throw new Forbidden($e->getMessage());
                        }
                    }
                }
            }
        }
    }
}
