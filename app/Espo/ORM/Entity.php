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

namespace Espo\ORM;

abstract class Entity implements IEntity
{
    public $id = null;

    private $isNew = false;

    private $isSaved = false;

    protected array $virtualFields = [];

    /**
     * Entity type.
     *
     * @var string
     */
    protected $entityType;

    /**
     * @var array Defenition of fields.
     * @todo make protected
     */
    public $fields = array();

    /**
     * @var array Defenition of relations.
     * @todo make protected
     */
    public $relations = array();

    /**
     * @var array Field-Value pairs.
     */
    protected $valuesContainer = array();

    /**
     * @var array Field-Value pairs of initial values (fetched from DB).
     */
    protected $fetchedValuesContainer = array();

    protected array $relationsContainer = [];

    /**
     * @var \Espo\Core\ORM\EntityManager Entity Manager.
     */
    protected $entityManager;

    protected $isFetched = false;

    protected $isBeingSaved = false;

    /**
     * @var array
     */
    protected $skipValidations = [];

    public function __construct($defs = array(), EntityManager $entityManager = null, $entityType = null)
    {
        if (empty($this->entityType)) {
            if (empty($entityType)) {
                $classNames = explode('\\', get_class($this));
                $this->entityType = end($classNames);
            } else {
                $this->entityType = $entityType;
            }
        }

        $this->entityManager = $entityManager;

        if (!empty($defs['fields'])) {
            $this->fields = $defs['fields'];
        }

        if (!empty($defs['relations'])) {
            $this->relations = $defs['relations'];
        }

        foreach ($entityManager->getEspoMetadata()->get(['entityDefs', $this->entityType, 'fields'], []) as $field => $fieldDefs) {
            if (!empty($fieldDefs['virtualField'])) {
                $this->virtualFields[] = $field;
            }
        }
    }

    public function clear($name = null)
    {
        if (is_null($name)) {
            $this->reset();
        }
        unset($this->valuesContainer[$name]);
    }

    public function reset()
    {
        $this->valuesContainer = array();
    }

    public function set($p1, $p2 = null)
    {
        if (is_array($p1) || is_object($p1)) {
            if (is_object($p1)) {
                $p1 = get_object_vars($p1);
            }
            if ($p2 === null) {
                $p2 = false;
            }
            $this->populateFromArray($p1, $p2);
        } else {
            if (is_string($p1)) {
                $name = $p1;
                $value = $p2;
                if ($name == 'id') {
                    $this->id = $value;
                }
                if ($this->hasAttribute($name)) {
                    $method = '_set' . ucfirst($name);
                    if (method_exists($this, $method)) {
                        $this->$method($value);
                    } else {
                        $this->setFieldValue($name, $value);
                    }
                }
            }
        }
    }

    public function get($name, $params = array())
    {
        if ($name == 'id') {
            return $this->id;
        }
        $method = '_get' . ucfirst($name);
        if (method_exists($this, $method)) {
            return $this->$method();
        }

        if (in_array($name, $this->virtualFields)) {
            return $this->getVirtualField($name);
        }

        if ($this->hasAttribute($name) && array_key_exists($name, $this->valuesContainer)) {
            return $this->getFieldValue($name);
        }

        if ($this->hasRelation($name) && $this->id) {
            if (!array_key_exists($name, $this->relationsContainer)) {
                $relationType = $this->getRelationType($name);
                if (empty($params) && $relationType === IEntity::BELONGS_TO) {
                    $this->relationsContainer[$name] = null;
                    $id = $this->get("{$name}Id");
                    if ($id !== null) {
                        $relationEntityType = $this->getRelationParam($name, 'entity');
                        $this->relationsContainer[$name] = $this->getEntityManager()->getRepository($relationEntityType)->get($id);
                    }
                } else {
                    $this->relationsContainer[$name] = $this->getEntityManager()->getRepository($this->getEntityType())->findRelated($this, $name, $params);
                }
            }
            return $this->relationsContainer[$name];
        }

        return null;
    }

    public function has($name)
    {
        if ($name == 'id') {
            return !!$this->id;
        }
        $method = '_has' . ucfirst($name);
        if (method_exists($this, $method)) {
            return $this->$method();
        }

        if (array_key_exists($name, $this->valuesContainer)) {
            return true;
        }
        return false;
    }

    /**
     * Push validation name that should skipped
     *
     * @param string $key
     *
     * @return Entity
     */
    public function skipValidation(string $key)
    {
        $this->skipValidations[] = $key;

        return $this;
    }

    /**
     * Is validation with such name should skipped?
     *
     * @param string $key
     *
     * @return bool
     */
    public function isSkippedValidation(string $key): bool
    {
        return in_array($key, $this->skipValidations);
    }

    /**
     * Get validations names that should skipped
     *
     * @return array
     */
    public function getSkippedValidation(): array
    {
        return $this->skipValidations;
    }

    public function populateFromArray(array $arr, $onlyAccessible = true, $reset = false)
    {
        if ($reset) {
            $this->reset();
        }

        foreach ($this->getAttributes() as $field => $fieldDefs) {
            if (array_key_exists($field, $arr)) {
                if ($field == 'id') {
                    $this->id = $arr[$field];
                    continue;
                }
                if ($onlyAccessible) {
                    if (isset($fieldDefs['notAccessible']) && $fieldDefs['notAccessible'] == true) {
                        continue;
                    }
                }

                $value = $arr[$field];

                if (!is_null($value)) {
                    switch ($fieldDefs['type']) {
                        case self::VARCHAR:
                            break;
                        case self::BOOL:
                            $value = ($value === 'true' || $value === '1' || $value === true || $value === 1);
                            break;
                        case self::INT:
                            $value = intval($value);
                            break;
                        case self::FLOAT:
                            $value = floatval($value);
                            break;
                        case self::JSON_ARRAY:
                            $value = is_string($value) ? json_decode($value) : $value;
                            if (!is_array($value)) {
                                $value = null;
                            }
                            break;
                        case self::JSON_OBJECT:
                            $value = is_string($value) ? json_decode($value) : $value;
                            if (!($value instanceof \stdClass) && !is_array($value)) {
                                $value = null;
                            }
                            break;
                        default:
                            break;
                    }
                }

                $method = '_set' . ucfirst($field);
                if (method_exists($this, $method)) {
                    $this->$method($value);
                } else {
                    $this->setFieldValue($field, $value);
                }
            }
        }
    }

    public function isNew()
    {
        return $this->isNew;
    }

    public function setIsNew($isNew)
    {
        $this->isNew = $isNew;
    }

    public function isSaved()
    {
        return $this->isSaved;
    }

    public function setIsSaved($isSaved)
    {
        $this->isSaved = $isSaved;
    }

    public function getEntityName()
    {
        return $this->getEntityType();
    }

    public function getEntityType()
    {
        return $this->entityType;
    }

    public function hasField($fieldName)
    {
        return isset($this->fields[$fieldName]);
    }

    public function hasAttribute($name)
    {
        return isset($this->fields[$name]);
    }

    public function hasRelation($relationName)
    {
        return isset($this->relations[$relationName]);
    }

    public function getAttributeList()
    {
        return array_keys($this->getAttributes());
    }

    public function getRelationList()
    {
        return array_keys($this->getRelations());
    }

    public function getValues()
    {
        return $this->toArray();
    }

    public function toArray()
    {
        $arr = array();
        if (isset($this->id)) {
            $arr['id'] = $this->id;
        }
        foreach ($this->fields as $field => $defs) {
            if ($field == 'id') {
                continue;
            }
            if ($this->has($field)) {
                $arr[$field] = $this->get($field);
            }
        }

        if (property_exists($this, 'isInherited')) {
            $arr['isInherited'] = $this->isInherited;
        }

        foreach ($this->getVirtualFields() as $name => $value) {
            $arr[$name] = $value;
        }

        return $arr;
    }

    public function getVirtualFields(): array
    {
        if (empty($this->virtualFields)) {
            return [];
        }

        $data = empty($this->get('data')) ? [] : json_decode(json_encode($this->get('data')), true);

        return isset($data['field']) && is_array($data['field']) ? $data['field'] : [];
    }

    public function setVirtualField(string $name, $value): void
    {
        if (empty($this->virtualFields)) {
            return;
        }

        $data = empty($this->get('data')) ? [] : json_decode(json_encode($this->get('data')), true);
        $data['field'][$name] = $value;
        $this->set('data', $data);

        $this->valuesContainer[$name] = $value;
    }

    public function getVirtualField(string $name)
    {
        $data = $this->getVirtualFields();

        return isset($data[$name]) ? $data[$name] : null;
    }

    public function getValueMap()
    {
        $array = $this->toArray();
        return (object)$array;
    }

    public function getFields()
    {
        return $this->fields;
    }

    public function getAttributes()
    {
        return $this->fields;
    }

    public function getRelations()
    {
        return $this->relations;
    }

    public function getAttributeType($attribute)
    {
        if (isset($this->fields[$attribute]) && isset($this->fields[$attribute]['type'])) {
            return $this->fields[$attribute]['type'];
        }
        return null;
    }

    public function getRelationType($relation)
    {
        if (isset($this->relations[$relation]) && isset($this->relations[$relation]['type'])) {
            return $this->relations[$relation]['type'];
        }
        return null;
    }

    public function getAttributeParam($attribute, $name)
    {
        if (isset($this->fields[$attribute]) && isset($this->fields[$attribute][$name])) {
            return $this->fields[$attribute][$name];
        }
        return null;
    }

    public function getRelationParam($relation, $name)
    {
        if (isset($this->relations[$relation]) && isset($this->relations[$relation][$name])) {
            return $this->relations[$relation][$name];
        }
        return null;
    }

    public function isFetched()
    {
        return $this->isFetched;
    }

    public function isFieldChanged($name)
    {
        return $this->has($name) && ($this->get($name) != $this->getFetched($name));
    }

    public function isAttributeChanged($name)
    {
        if (!$this->has($name)) {
            return false;
        }

        if (!$this->hasFetched($name)) {
            return true;
        }
        return !self::areValuesEqual(
            $this->getAttributeType($name),
            $this->get($name),
            $this->getFetched($name),
            $this->getAttributeParam($name, 'isUnordered')
        );

        return $this->get($name) != $this->getFetched($name);
    }

    public static function areValuesEqual($type, $v1, $v2, $isUnordered = false)
    {
        if ($type === self::JSON_ARRAY) {
            if (is_array($v1) && is_array($v2)) {
                if ($isUnordered) {
                    sort($v1);
                    sort($v2);
                }
                if ($v1 != $v2) {
                    return false;
                }
                foreach ($v1 as $i => $itemValue) {
                    if (is_object($v1[$i]) && is_object($v2[$i])) {
                        if (!self::areValuesEqual(self::JSON_OBJECT, $v1[$i], $v2[$i])) {
                            return false;
                        }
                        continue;
                    }
                    if ($v1[$i] !== $v2[$i]) {
                        return false;
                    }
                }
                return true;
            }
        } else {
            if ($type === self::JSON_OBJECT) {
                if (is_object($v1) && is_object($v2)) {
                    if ($v1 != $v2) {
                        return false;
                    }
                    $a1 = get_object_vars($v1);
                    $a2 = get_object_vars($v2);
                    foreach ($v1 as $key => $itemValue) {
                        if (is_object($a1[$key]) && is_object($a2[$key])) {
                            if (!self::areValuesEqual(self::JSON_OBJECT, $a1[$key], $a2[$key])) {
                                return false;
                            }
                            continue;
                        }
                        if (is_array($a1[$key]) && is_array($a2[$key])) {
                            if (!self::areValuesEqual(self::JSON_ARRAY, $a1[$key], $a2[$key])) {
                                return false;
                            }
                            continue;
                        }
                        if ($a1[$key] !== $a2[$key]) {
                            return false;
                        }
                    }
                    return true;
                }
            }
        }

        return $v1 === $v2;
    }

    public function setFetched($name, $value)
    {
        $this->fetchedValuesContainer[$name] = $value;
    }

    public function getFetched($name)
    {
        if (isset($this->fetchedValuesContainer[$name])) {
            return $this->fetchedValuesContainer[$name];
        }
        return null;
    }

    public function hasFetched($attributeName)
    {
        return array_key_exists($attributeName, $this->fetchedValuesContainer);
    }

    public function resetFetchedValues()
    {
        $this->fetchedValuesContainer = array();
    }

    public function updateFetchedValues()
    {
        $this->fetchedValuesContainer = $this->valuesContainer;
    }

    public function setAsFetched()
    {
        $this->isFetched = true;
        $this->fetchedValuesContainer = $this->valuesContainer;
    }

    public function setAsNotFetched()
    {
        $this->isFetched = false;
        $this->resetFetchedValues();
    }

    public function isBeingSaved()
    {
        return $this->isBeingSaved;
    }

    public function setAsBeingSaved()
    {
        $this->isBeingSaved = true;
    }

    public function setAsNotBeingSaved()
    {
        $this->isBeingSaved = false;
    }

    public function populateDefaults()
    {
        foreach ($this->fields as $field => $defs) {
            $fieldData = $this->getEntityManager()->getEspoMetadata()->get(['entityDefs', $this->entityType, 'fields', $field], []);
            if (!empty($fieldData['relationVirtualField'])) {
                continue;
            }

            if (array_key_exists('default', $defs)) {
                $default = $defs['default'];

                // default for enum and multiEnum
                if (!empty($fieldData['type'])) {
                    if ($fieldData['type'] === 'multiEnum') {
                        $default = $fieldData['default'];
                    }
                    if (!empty($fieldData['options']) && !empty($fieldData['optionsIds'])) {
                        switch ($fieldData['type']) {
                            case 'enum':
                                $key = array_search($default, $fieldData['options']);
                                if ($key !== false) {
                                    $default = $fieldData['optionsIds'][$key];
                                }
                                break;
                            case 'multiEnum':
                                if (!empty($default) && (is_array($default) || is_object($default))) {
                                    foreach ($default as $v) {
                                        $key = array_search($v, $fieldData['options']);
                                        if ($key !== false) {
                                            $ids[] = $fieldData['optionsIds'][$key];
                                        }
                                    }
                                    if (!empty($ids)) {
                                        $default = $ids;
                                    }
                                }
                                break;
                        }
                    }
                }

                $this->setFieldValue($field, $default);
            } else if (array_key_exists('default', $fieldData)) {
                $default = $fieldData['default'];
                if (!empty($default) && $fieldData['type'] === 'varchar') {
                    // if default value is twig template, default value is only present in espo metadata
                    if (strpos($default, '{{') >= 0 && strpos($default, '}}') >= 0) {
                        // use twig
                        $default = $this->getEntityManager()->getContainer()->get('twig')->renderTemplate($default, []);
                        $this->setFieldValue($field, $default);
                    }
                }
            } else if (!empty($fieldData['defaultId'])) {
                // load extensibleEnum default value
                $this->setFieldValue($field, $fieldData['defaultId']);
            }

            // default for unit
            if (isset($this->fields[$field . 'Unit'])) {
                if (!empty($fieldData['type']) && $fieldData['type'] === 'unit' && !empty($fieldData['defaultUnit'])) {
                    $this->setFieldValue($field . 'Unit', $fieldData['defaultUnit']);
                }
            }
        }
    }

    protected function setFieldValue(string $field, $value): void
    {
        if (in_array($field, $this->virtualFields)) {
            $this->setVirtualField($field, $value);
            return;
        }

        $this->valuesContainer[$field] = $value;
    }

    protected function getFieldValue(string $field)
    {
        if (!array_key_exists($field, $this->valuesContainer)) {
            return null;
        }

        return $this->valuesContainer[$field];
    }

    protected function getEntityManager()
    {
        return $this->entityManager;
    }

    protected function checkViaAcl($value)
    {
        // exit if empty
        if (empty($value) || ($value instanceof EntityCollection && count($value) === 0)) {
            return $value;
        }

        $container = $this->getEntityManager()->getContainer();

        if (empty($container->get('user'))) {
            return $value;
        }

        if ($value instanceof Entity) {
            if (!$container->get('acl')->check($value, 'read')) {
                return null;
            }
            foreach ($container->get('acl')->getScopeForbiddenAttributeList($value->getEntityType(), 'read') as $attribute) {
                $value->clear($attribute);
            }
            return $value;
        }

        if ($value instanceof EntityCollection) {
            foreach ($value as $key => $item) {
                if (!$container->get('acl')->check($item, 'read')) {
                    $item->offsetUnset($key);
                    continue 1;
                }

                foreach ($container->get('acl')->getScopeForbiddenAttributeList($item->getEntityType(), 'read') as $attribute) {
                    $item->clear($attribute);
                }
            }
            return $value;
        }

        return $value;
    }

    public function __isset($name)
    {
        return $this->hasAttribute($name);
    }

    public function __get($name)
    {
        if ($this->hasAttribute($name)) {
            return $this->checkViaAcl($this->get($name));
        }

        return null;
    }

    public function __toString()
    {
        return $this->hasAttribute('name') ? $this->get('name') : $this->get('id');
    }
}
