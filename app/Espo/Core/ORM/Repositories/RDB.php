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
 */

namespace Espo\Core\ORM\Repositories;

use Espo\Core\PseudoTransactionManager;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Json;
use Espo\ORM\EntityManager;
use Espo\ORM\EntityFactory;
use Espo\ORM\Entity;
use Espo\Core\Interfaces\Injectable;
use Espo\Core\Utils\Util;
use Treo\Core\EventManager\Event;

class RDB extends \Espo\ORM\Repositories\RDB implements Injectable
{
    protected $dependencies = array(
        'container',
        'metadata',
        'config',
        'fieldManagerUtil',
        'eventManager',
        'workflow',
        'pseudoTransactionManager'
    );

    protected $injections = [];

    private $restoreData = null;

    protected $hooksDisabled = false;

    protected $processFieldsAfterSaveDisabled = false;

    protected $processFieldsBeforeSaveDisabled = false;

    protected $processFieldsAfterRemoveDisabled = false;

    protected function addDependency($name)
    {
        $this->dependencies[] = $name;
    }

    protected function addDependencyList(array $list)
    {
        foreach ($list as $item) {
            $this->addDependency($item);
        }
    }

    public function inject($name, $object)
    {
        $this->injections[$name] = $object;
    }

    protected function getInjection($name)
    {
        return $this->injections[$name];
    }

    public function getDependencyList()
    {
        return $this->dependencies;
    }

    protected function getMetadata()
    {
        return $this->getInjection('metadata');
    }

    protected function getConfig()
    {
        return $this->getInjection('config');
    }

    protected function getFieldManagerUtil()
    {
        return $this->getInjection('fieldManagerUtil');
    }

    protected function getPseudoTransactionManager(): PseudoTransactionManager
    {
        return $this->getInjection('pseudoTransactionManager');
    }

    public function __construct($entityType, EntityManager $entityManager, EntityFactory $entityFactory)
    {
        parent::__construct($entityType, $entityManager, $entityFactory);
        $this->init();
    }

    /**
     * Init
     */
    protected function init()
    {
    }

    public function handleSelectParams(&$params)
    {
        if (empty($params['skipCurrencyConvertedParams'])) {
            $this->handleCurrencyParams($params);
        }
    }

    protected function handleCurrencyParams(&$params)
    {
        $entityType = $this->entityType;

        $metadata = $this->getMetadata();

        if (!$metadata) {
            return;
        }

        $fields = $metadata->get(['entityDefs', $entityType, 'fields'], []);

        foreach ($fields as $field => $d) {
            if (isset($d['type']) && $d['type'] == 'currency') {
                if (!empty($d['notStorable'])) continue;
                if (empty($params['leftJoins'])) $params['leftJoins'] = [];
                $alias = $field . 'CurrencyRate';

                $params['leftJoins'][] = ['Currency', $alias, [
                    $alias . '.id:' => $field . 'Currency'
                ]];
            }
        }
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        parent::beforeRemove($entity, $options);

        // dispatch an event
        $this->dispatch('beforeRemove', $entity, $options);

        $nowString = date('Y-m-d H:i:s', time());
        if ($entity->hasAttribute('modifiedAt')) {
            $entity->set('modifiedAt', $nowString);
        }
        if ($entity->hasAttribute('modifiedById')) {
            $entity->set('modifiedById', $this->getEntityManager()->getUser()->id);
        }
    }

    /**
     * @inheritDoc
     */
    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        if (!$this->processFieldsAfterRemoveDisabled) {
            $this->processArrayFieldsRemove($entity);
        }

        // dispatch an event
        $this->dispatch('afterRemove', $entity, $options);
    }

    protected function beforeMassRelate(Entity $entity, $relationName, array $params = [], array $options = [])
    {
        parent::beforeMassRelate($entity, $relationName, $params, $options);

        // dispatch an event
        $this->dispatch('beforeMassRelate', $entity, $options, $relationName, $params);
    }

    protected function afterMassRelate(Entity $entity, $relationName, array $params = [], array $options = [])
    {
        parent::afterMassRelate($entity, $relationName, $params, $options);

        // dispatch an event
        $this->dispatch('afterMassRelate', $entity, $options, $relationName, $params);
    }

    public function remove(Entity $entity, array $options = [])
    {
        $result = parent::remove($entity, $options);
        return $result;
    }

    protected function beforeRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
        parent::beforeRelate($entity, $relationName, $foreign, $data, $options);

        // dispatch an event
        $this->dispatch('beforeRelate', $entity, $options, $relationName, $data, $foreign);
    }

    protected function afterRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
        parent::afterRelate($entity, $relationName, $foreign, $data, $options);

        // dispatch an event
        $this->dispatch('afterRelate', $entity, $options, $relationName, $data, $foreign);
    }

    protected function beforeUnrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
        parent::beforeUnrelate($entity, $relationName, $foreign, $options);

        // dispatch an event
        $this->dispatch('beforeUnrelate', $entity, $options, $relationName, null, $foreign);
    }

    protected function afterUnrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
        parent::afterUnrelate($entity, $relationName, $foreign, $options);

        // dispatch an event
        $this->dispatch('afterUnrelate', $entity, $options, $relationName, null, $foreign);
    }

    protected function validateFieldsByType(Entity $entity): void
    {
        foreach ($this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'fields'], []) as $fieldName => $fieldData) {
            if (isset($fieldData['type'])) {
                $method = "validate" . ucfirst($fieldData['type']);
                if (method_exists($this, $method)) {
                    $this->$method($entity, $fieldName, $fieldData);
                }
            }
        }
    }

    protected function validateEmail(Entity $entity, string $fieldName, array $fieldData): void
    {
        if ($entity->isAttributeChanged($fieldName) && !empty($entity->get($fieldName))) {
            if (!filter_var($entity->get($fieldName), FILTER_VALIDATE_EMAIL)) {
                $language = $this->getInjection('container')->get('language');
                throw new BadRequest(sprintf($language->translate('emailIsInvalid', 'exceptions', 'Global'), $language->translate($fieldName, 'fields', $entity->getEntityType())));
            }
        }
    }

    protected function validateEnum(Entity $entity, string $fieldName, array $fieldData): void
    {
        if (!isset($fieldData['view']) && $entity->isAttributeChanged($fieldName) && !empty($entity->get($fieldName))) {
            $fieldOptions = empty($fieldData['options']) ? [] : $fieldData['options'];
            if (empty($fieldOptions) && $fieldData['type'] === 'multiEnum') {
                return;
            }

            $value = $entity->get($fieldName);

            if ($fieldData['type'] == 'enum') {
                $value = [$value];
            }

            if (!is_array($value)) {
                return;
            }

            foreach ($value as $v) {
                if (!in_array($v, $fieldOptions)) {
                    $language = $this->getInjection('container')->get('language');
                    throw new BadRequest(sprintf($language->translate('noSuchOptions', 'exceptions', 'Global'), $v, $language->translate($fieldName, 'fields', $entity->getEntityType())));
                }
            }
        }
    }

    protected function validateMultiEnum(Entity $entity, string $fieldName, array $fieldData): void
    {
        $this->validateEnum($entity, $fieldName, $fieldData);
    }

    protected function validateUnit(Entity $entity, string $fieldName, array $fieldData): void
    {
        $language = $this->getInjection('container')->get('language');

        $unitsOfMeasure = $this->getConfig()->get('unitsOfMeasure');
        $unitsOfMeasure = empty($unitsOfMeasure) ? [] : Json::decode(Json::encode($unitsOfMeasure), true);
        $measure = $this->getUnitFieldMeasure($fieldName, $entity);

        if (!isset($unitsOfMeasure[$measure]['unitList'])) {
            return;
        }

        $value = $entity->get($fieldName);
        $unit = $entity->get($fieldName . 'Unit');

        $fieldLabel = $language->translate($fieldName, 'fields', $entity->getEntityType());

        if ($value !== null && $value !== '' && empty($unit)) {
            throw new BadRequest(sprintf($language->translate('unitValueIsRequired', 'exceptions', 'Global'), $fieldLabel));
        }

        if (!empty($unit) && !in_array($unit, $unitsOfMeasure[$measure]['unitList'])) {
            throw new BadRequest(sprintf($language->translate('noSuchUnit', 'exceptions', 'Global'), $unit, $fieldLabel));
        }
    }

    protected function getUnitFieldMeasure(string $fieldName, Entity $entity): string
    {
        return $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'fields', $fieldName, 'measure'], '');
    }

    /**
     * @param Entity $entity
     * @param array  $options
     */
    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if (empty($options['skipAll'])) {
            $this->validateFieldsByType($entity);
        }

        // dispatch an event
        $this->dispatch('beforeSave', $entity, $options);

        if (!$this->processFieldsBeforeSaveDisabled) {
            $this->processCurrencyFieldsBeforeSave($entity);
        }
    }

    /**
     * @param Entity $entity
     * @param array  $options
     */
    protected function afterSave(Entity $entity, array $options = [])
    {
        if (!empty($this->restoreData)) {
            $entity->set($this->restoreData);
            $this->restoreData = null;
        }
        parent::afterSave($entity, $options);

        $this->assignmentNotifications($entity);

        if (!$this->processFieldsAfterSaveDisabled) {
            $this->processSpecifiedRelationsSave($entity);
            if (empty($entity->skipProcessFileFieldsSave)) {
                $this->processFileFieldsSave($entity);
            }
            $this->processArrayFieldsSave($entity);
            $this->processWysiwygFieldsSave($entity);
        }

        // dispatch an event
        $this->dispatch('afterSave', $entity, $options);
    }

    public function save(Entity $entity, array $options = [])
    {
        $nowString = date('Y-m-d H:i:s', time());
        $restoreData = [];

        if ($entity->isNew()) {
            if (!$entity->has('id')) {
                $entity->set('id', Util::generateId());
            }
        }

        if (empty($options['skipAll'])) {
            if ($entity->isNew()) {
                if ($entity->hasAttribute('createdAt')) {
                    if (empty($options['import']) || !$entity->has('createdAt')) {
                        $entity->set('createdAt', $nowString);
                    }
                }
                if ($entity->hasAttribute('modifiedAt')) {
                    $entity->set('modifiedAt', $nowString);
                }
                if ($entity->hasAttribute('createdById')) {
                    if (empty($options['skipCreatedBy']) && (empty($options['import']) || !$entity->has('createdById'))) {
                        if ($this->getEntityManager()->getUser()) {
                            $entity->set('createdById', $this->getEntityManager()->getUser()->id);
                        }
                    }
                }
            } else {
                if (empty($options['silent']) && empty($options['skipModifiedBy'])) {
                    if ($entity->hasAttribute('modifiedAt')) {
                        $entity->set('modifiedAt', $nowString);
                    }
                    if ($entity->hasAttribute('modifiedById')) {
                        if ($this->getEntityManager()->getUser()) {
                            $entity->set('modifiedById', $this->getEntityManager()->getUser()->id);
                            $entity->set('modifiedByName', $this->getEntityManager()->getUser()->get('name'));
                        }
                    }
                }
            }
        }

        $this->restoreData = $restoreData;

        $result = parent::save($entity, $options);

        return $result;
    }

    protected function getFieldByTypeList($type)
    {
        return $this->getFieldManagerUtil()->getFieldByTypeList($this->entityType, $type);
    }

    protected function processCurrencyFieldsBeforeSave(Entity $entity)
    {
        foreach ($this->getFieldByTypeList('currency') as $field) {
            $currencyAttribute = $field . 'Currency';
            $defaultCurrency = $this->getConfig()->get('defaultCurrency');
            if ($entity->isNew()) {
                if ($entity->get($field) && !$entity->get($currencyAttribute)) {
                    $entity->set($currencyAttribute, $defaultCurrency);
                }
            } else {
                if ($entity->isAttributeChanged($field) && $entity->has($currencyAttribute) && !$entity->get($currencyAttribute)) {
                    $entity->set($currencyAttribute, $defaultCurrency);
                }
            }
        }
    }

    protected function processFileFieldsSave(Entity $entity)
    {
        foreach ($entity->getRelations() as $name => $defs) {
            if (!isset($defs['type']) || !isset($defs['entity'])) continue;
            if (!($defs['type'] === $entity::BELONGS_TO && $defs['entity'] === 'Attachment')) continue;

            $attribute = $name . 'Id';
            if (!$entity->hasAttribute($attribute)) continue;
            if (!$entity->get($attribute)) continue;
            if (!$entity->isAttributeChanged($attribute)) continue;

            $attachment = $this->getEntityManager()->getEntity('Attachment', $entity->get($attribute));
            if (!$attachment || !empty($attachment->get('relatedId'))) continue;
            $attachment->set(array(
                'relatedId' => $entity->id,
                'relatedType' => $entity->getEntityType()
            ));
            $this->getEntityManager()->saveEntity($attachment);
        }
    }

    protected function processArrayFieldsSave(Entity $entity)
    {
        foreach ($entity->getAttributes() as $attribute => $defs) {
            if (!isset($defs['type']) || $defs['type'] !== Entity::JSON_ARRAY) continue;
            if (!$entity->has($attribute)) continue;
            if (!$entity->isAttributeChanged($attribute)) continue;
            if (!$entity->getAttributeParam($attribute, 'storeArrayValues')) continue;
            if ($entity->getAttributeParam($attribute, 'notStorable')) continue;
            $this->getEntityManager()->getRepository('ArrayValue')->storeEntityAttribute($entity, $attribute);
        }
    }

    protected function processWysiwygFieldsSave(Entity $entity)
    {
        if (!$entity->isNew()) return;

        $fieldsDefs = $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'fields'], []);
        foreach ($fieldsDefs as $field => $defs) {
            if (!empty($defs['type']) && $defs['type'] === 'wysiwyg') {
                $content = $entity->get($field);
                if (!$content) continue;
                if (preg_match_all("/\?entryPoint=attachment&amp;id=([^&=\"']+)/", $content, $matches)) {
                    if (!empty($matches[1]) && is_array($matches[1])) {
                        foreach ($matches[1] as $id) {
                            $attachment = $this->getEntityManager()->getEntity('Attachment', $id);
                            if ($attachment) {
                                if (!$attachment->get('relatedId') && !$attachment->get('sourceId')) {
                                    $attachment->set([
                                        'relatedId' => $entity->id,
                                        'relatedType' => $entity->getEntityType()
                                    ]);
                                    $this->getEntityManager()->saveEntity($attachment);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    protected function processArrayFieldsRemove(Entity $entity)
    {
        foreach ($entity->getAttributes() as $attribute => $defs) {
            if (!isset($defs['type']) || $defs['type'] !== Entity::JSON_ARRAY) continue;
            if (!$entity->getAttributeParam($attribute, 'storeArrayValues')) continue;
            if ($entity->getAttributeParam($attribute, 'notStorable')) continue;
            $this->getEntityManager()->getRepository('ArrayValue')->deleteEntityAttribute($entity, $attribute);
        }
    }

    protected function processSpecifiedRelationsSave(Entity $entity)
    {
        $relationTypeList = [$entity::HAS_MANY, $entity::MANY_MANY, $entity::HAS_CHILDREN];
        foreach ($entity->getRelations() as $name => $defs) {
            if (in_array($defs['type'], $relationTypeList)) {
                $fieldName = $name . 'Ids';
                $columnsFieldsName = $name . 'Columns';


                if ($entity->has($fieldName) || $entity->has($columnsFieldsName)) {
                    if ($this->getMetadata()->get("entityDefs." . $entity->getEntityType() . ".fields.{$name}.noSave")) {
                        continue;
                    }

                    if ($entity->has($fieldName)) {
                        $specifiedIds = $entity->get($fieldName);
                    } else {
                        $specifiedIds = [];
                        foreach ($entity->get($columnsFieldsName) as $id => $d) {
                            $specifiedIds[] = $id;
                        }
                    }
                    if (is_array($specifiedIds)) {
                        $toRemoveIds = [];
                        $existingIds = [];
                        $toUpdateIds = [];
                        $existingColumnsData = new \stdClass();

                        $defs = [];
                        $columns = $this->getMetadata()->get("entityDefs." . $entity->getEntityType() . ".fields.{$name}.columns");
                        if (!empty($columns)) {
                            $columnData = $entity->get($columnsFieldsName);
                            $defs['additionalColumns'] = $columns;
                        }

                        $foreignCollection = $entity->get($name, $defs);
                        if ($foreignCollection) {
                            foreach ($foreignCollection as $foreignEntity) {
                                $existingIds[] = $foreignEntity->id;
                                if (!empty($columns)) {
                                    $data = new \stdClass();
                                    foreach ($columns as $columnName => $columnField) {
                                        $foreignId = $foreignEntity->id;
                                        $data->$columnName = $foreignEntity->get($columnField);
                                    }
                                    $existingColumnsData->$foreignId = $data;
                                    if (!$entity->isNew()) {
                                        $entity->setFetched($columnsFieldsName, $existingColumnsData);
                                    }
                                }

                            }
                        }

                        if (!$entity->isNew()) {
                            if ($entity->has($fieldName)) {
                                $entity->setFetched($fieldName, $existingIds);
                            }
                            if ($entity->has($columnsFieldsName) && !empty($columns)) {
                                $entity->setFetched($columnsFieldsName, $existingColumnsData);
                            }
                        }

                        foreach ($existingIds as $id) {
                            if (!in_array($id, $specifiedIds)) {
                                $toRemoveIds[] = $id;
                            } else {
                                if (!empty($columns)) {
                                    foreach ($columns as $columnName => $columnField) {
                                        if (isset($columnData->$id) && is_object($columnData->$id)) {
                                            if (
                                                property_exists($columnData->$id, $columnName)
                                                &&
                                                (
                                                    !property_exists($existingColumnsData->$id, $columnName)
                                                    ||
                                                    $columnData->$id->$columnName !== $existingColumnsData->$id->$columnName
                                                )
                                            ) {
                                                $toUpdateIds[] = $id;
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        foreach ($specifiedIds as $id) {
                            if (!in_array($id, $existingIds)) {
                                $data = null;
                                if (!empty($columns) && isset($columnData->$id)) {
                                    $data = $columnData->$id;
                                }
                                $this->relate($entity, $name, $id, $data);
                            }
                        }
                        foreach ($toRemoveIds as $id) {
                            $this->unrelate($entity, $name, $id);
                        }
                        if (!empty($columns)) {
                            foreach ($toUpdateIds as $id) {
                                $data = $columnData->$id;
                                $this->updateRelation($entity, $name, $id, $data);
                            }
                        }
                    }
                }
            } else if ($defs['type'] === $entity::HAS_ONE) {
                if (empty($defs['entity']) || empty($defs['foreignKey'])) continue;

                if ($this->getMetadata()->get("entityDefs." . $entity->getEntityType() . ".fields.{$name}.noSave")) {
                    continue;
                }

                $foreignEntityType = $defs['entity'];
                $foreignKey = $defs['foreignKey'];
                $idFieldName = $name . 'Id';
                $nameFieldName = $name . 'Name';

                if (!$entity->has($idFieldName)) continue;

                $where = [];
                $where[$foreignKey] = $entity->id;
                $previousForeignEntity = $this->getEntityManager()->getRepository($foreignEntityType)->where($where)->findOne();
                if ($previousForeignEntity) {
                    if (!$entity->isNew()) {
                        $entity->setFetched($idFieldName, $previousForeignEntity->id);
                    }
                    if ($previousForeignEntity->id !== $entity->get($idFieldName)) {
                        $previousForeignEntity->set($foreignKey, null);
                        $this->getEntityManager()->saveEntity($previousForeignEntity);
                    }
                } else {
                    if (!$entity->isNew()) {
                        $entity->setFetched($idFieldName, null);
                    }
                }

                if ($entity->get($idFieldName)) {
                    $newForeignEntity = $this->getEntityManager()->getEntity($foreignEntityType, $entity->get($idFieldName));
                    if ($newForeignEntity) {
                        $newForeignEntity->set($foreignKey, $entity->id);
                        $this->getEntityManager()->saveEntity($newForeignEntity);
                    } else {
                        $entity->set($idFieldName, null);
                    }
                }
            }
        }
    }

    protected function assignmentNotifications(Entity $entity): void
    {
        if ($entity->getEntityType() === 'Notification') {
            return;
        }

        if (!$this->getConfig()->get('assignmentNotifications', true)) {
            return;
        }

        if ($entity->isAttributeChanged('ownerUserId')) {
            $this->createOwnNotification($entity, $entity->get('ownerUserId'));
        }

        if ($entity->isAttributeChanged('assignedUserId')) {
            $this->createAssignmentNotification($entity, $entity->get('assignedUserId'));
        }
    }

    protected function createOwnNotification(Entity $entity, ?string $userId): void
    {
        if (empty($userId)) {
            return;
        }

        $preferences = $this->getEntityManager()->getEntity('Preferences', $userId);
        if (empty($preferences->get('assignmentNotifications'))) {
            return;
        }

        $notification = $this->getEntityManager()->getEntity('Notification');
        $notification->set('type', 'Own');
        $notification->set('relatedType', $entity->getEntityType());
        $notification->set('relatedId', $entity->get('id'));
        $notification->set('userId', $userId);
        $notification->set(
            'data', [
                'entityName' => $entity->get('name'),
                'entityType' => $entity->getEntityType(),
                'entityId'   => $entity->get('id'),
                'changedBy'  => $this->getEntityManager()->getUser()->get('id'),
            ]
        );
        $this->getEntityManager()->saveEntity($notification);
    }

    protected function createAssignmentNotification(Entity $entity, ?string $userId): void
    {
        if (empty($userId)) {
            return;
        }

        $preferences = $this->getEntityManager()->getEntity('Preferences', $userId);
        if (empty($preferences->get('assignmentNotifications'))) {
            return;
        }

        $notification = $this->getEntityManager()->getEntity('Notification');
        $notification->set('type', 'Assign');
        $notification->set('relatedType', $entity->getEntityType());
        $notification->set('relatedId', $entity->get('id'));
        $notification->set('userId', $userId);
        $notification->set(
            'data', [
                'entityName' => $entity->get('name'),
                'entityType' => $entity->getEntityType(),
                'entityId'   => $entity->get('id'),
                'changedBy'  => $this->getEntityManager()->getUser()->get('id'),
            ]
        );
        $this->getEntityManager()->saveEntity($notification);
    }

    /**
     * Dispatch an event
     *
     * @param string $action
     * @param Entity $entity
     * @param array  $options
     * @param mixed  $arg1
     * @param mixed  $arg2
     * @param mixed  $arg3
     */
    private function dispatch(string $action, Entity $entity, $options, $arg1 = null, $arg2 = null, $arg3 = null)
    {
        $event = new Event(
            [
                'hooksDisabled'  => $this->hooksDisabled,
                'entityType'     => $this->entityType,
                'entity'         => $entity,
                'options'        => $options,
                'relationName'   => $arg1,
                'relationParams' => $arg2,
                'relationData'   => $arg2,
                'foreign'        => $arg3,
            ]
        );

        // dispatch an event
        $this->getInjection('eventManager')->dispatch('Entity', $action, $event);
    }
}
