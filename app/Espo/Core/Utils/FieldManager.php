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

namespace Espo\Core\Utils;

use Espo\Core\EventManager\Event;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error;
use Espo\Core\Injectable;
use Espo\Core\Utils\FieldManager\Hooks\Base as BaseHook;
use Espo\Core\Utils\Metadata\Helper;

/**
 * Class FieldManager
 */
class FieldManager extends Injectable
{
    public const UNNECESSARY_FIELDS
        = [
            'name',
            'label',
            'translatedOptions',
            'dynamicLogicVisible',
            'dynamicLogicReadOnly',
            'dynamicLogicRequired',
            'dynamicLogicOptions',
            'lingualFields'
        ];

    protected $isChanged = null;

    protected $forbiddenFieldNameList = ['id', 'deleted'];

    public function __construct()
    {
        $this->addDependency('container');
    }

    /**
     * @return Metadata
     */
    protected function getMetadata()
    {
        return $this->getInjection('container')->get('metadata');
    }

    protected function getLanguage()
    {
        return $this->getInjection('container')->get('language');
    }

    protected function getBaseLanguage()
    {
        return $this->getInjection('container')->get('baseLanguage');
    }

    /**
     * @return Helper
     */
    protected function getMetadataHelper()
    {
        return new Helper($this->getMetadata());
    }

    protected function getDefaultLanguage()
    {
        return $this->getInjection('container')->get('defaultLanguage');
    }

    public function read($scope, $name)
    {
        $fieldDefs = $this->getFieldDefs($scope, $name);

        $fieldDefs['label'] = $this->getLanguage()->translate($name, 'fields', $scope);

        $type = $this->getMetadata()->get(['entityDefs', $scope, 'fields', $name, 'type']);

        $this->processHook('onRead', $type, $scope, $name, $fieldDefs);

        return $fieldDefs;
    }

    public function create($scope, $name, $fieldDefs)
    {
        if (empty($name)) {
            throw new BadRequest();
        }

        if (strlen($name) > 100) {
            throw new Error('Field name should not be longer than 100.');
        }

        if (is_numeric($name[0])) {
            throw new Error('Bad field name.');
        }

        $existingField = $this->getFieldDefs($scope, $name);
        if (isset($existingField)) {
            throw new Conflict('Field [' . $name . '] exists in ' . $scope);
        }
        if ($this->getMetadata()->get(['entityDefs', $scope, 'links', $name])) {
            throw new Conflict('Link with name [' . $name . '] exists in ' . $scope);
        }
        if (in_array($name, $this->forbiddenFieldNameList)) {
            throw new Conflict('Field [' . $name . '] is not allowed');
        }

        $firstLatter = $name[0];
        if (is_numeric($firstLatter)) {
            throw new Conflict('Field name should begin with a letter');
        }

        return $this->update($scope, $name, $fieldDefs, true);
    }

    public function update($scope, $name, $fieldDefs, $isNew = false)
    {
        $name = trim($name);
        $this->isChanged = false;

        if (!$this->isCore($scope, $name)) {
            $fieldDefs['isCustom'] = true;
        }

        $isCustom = false;
        if (!empty($fieldDefs['isCustom'])) {
            $isCustom = true;
        }

        $result = true;
        $isLabelChanged = false;

        if (isset($fieldDefs['label']) && empty($fieldDefs['multilangField'])) {
            $this->setLabel($scope, $name, $fieldDefs['label'], $isNew, $isCustom);
            $isLabelChanged = true;
        }

        foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
            $label = Util::toCamelCase('label_' . strtolower($locale));
            $fieldKey = empty($fieldDefs['multilangField']) ? $name : $fieldDefs['multilangField'];
            if (isset($fieldDefs[$label])) {
                $languageObj = new Language($this->getInjection('container'), $locale);
                $languageObj->set($scope, 'fields', $fieldKey, $fieldDefs[$label]);
                $languageObj->save();
                unset($fieldDefs[$label]);
            }
        }

        if (isset($fieldDefs['tooltipText'])) {
            $this->setTooltipText($scope, $name, $fieldDefs['tooltipText'], $isNew, $isCustom);
            $isLabelChanged = true;
        }

        $type = isset($fieldDefs['type']) ? $fieldDefs['type'] : $type = $this->getMetadata()->get(['entityDefs', $scope, 'fields', $name, 'type']);

        $this->processHook('beforeSave', $type, $scope, $name, $fieldDefs, array('isNew' => $isNew));

        if ($isNew) {
            $subFieldsDefs = $this->getMetadata()->get(['fields', $type, 'fields']);
            if ($subFieldsDefs) {
                foreach ($subFieldsDefs as $partField => $partFieldData) {
                    $partLabel = $this->getLanguage()->get('FieldManager.fieldParts.' . $type . '.' . $partField);
                    if ($partLabel) {
                        if ($this->getMetadata()->get(['fields', $type, 'fields', 'naming']) === 'prefix') {
                            $subFieldName = $partField . ucfirst($name);
                            $subFieldLabel = $partLabel . ' ' . $fieldDefs['label'];
                        } else {
                            $subFieldName = $name . ucfirst($partField);
                            $subFieldLabel = $fieldDefs['label'] . ' ' . $partLabel;
                        }
                        $this->setLabel($scope, $subFieldName, $subFieldLabel, $isNew, $isCustom);
                        $isLabelChanged = true;
                    }
                }
            }
        }

        if ($isLabelChanged) {
            $this->getLanguage()->save();
            if ($isNew || $isCustom) {
                if ($this->getBaseLanguage()->getLanguage() !== $this->getLanguage()->getLanguage()) {
                    $this->getBaseLanguage()->save();
                }
            }
        }

        $metadataToBeSaved = false;
        $clientDefsToBeSet = false;

        $clientDefs = array();

        if (array_key_exists('dynamicLogicVisible', $fieldDefs)) {
            if (!is_null($fieldDefs['dynamicLogicVisible'])) {
                $this->prepareClientDefsFieldsDynamicLogic($clientDefs, $name);
                $clientDefs['dynamicLogic']['fields'][$name]['visible'] = $fieldDefs['dynamicLogicVisible'];
                $metadataToBeSaved = true;
                $clientDefsToBeSet = true;
            } else {
                if ($this->getMetadata()->get(['clientDefs', $scope, 'dynamicLogic', 'fields', $name, 'visible'])) {
                    $this->prepareClientDefsFieldsDynamicLogic($clientDefs, $name);
                    $clientDefs['dynamicLogic']['fields'][$name]['visible'] = null;
                    $metadataToBeSaved = true;
                    $clientDefsToBeSet = true;
                }
            }
        }

        if (array_key_exists('dynamicLogicReadOnly', $fieldDefs)) {
            if (!is_null($fieldDefs['dynamicLogicReadOnly'])) {
                $this->prepareClientDefsFieldsDynamicLogic($clientDefs, $name);
                $clientDefs['dynamicLogic']['fields'][$name]['readOnly'] = $fieldDefs['dynamicLogicReadOnly'];
                $metadataToBeSaved = true;
                $clientDefsToBeSet = true;
            } else {
                if ($this->getMetadata()->get(['clientDefs', $scope, 'dynamicLogic', 'fields', $name, 'readOnly'])) {
                    $this->prepareClientDefsFieldsDynamicLogic($clientDefs, $name);
                    $clientDefs['dynamicLogic']['fields'][$name]['readOnly'] = null;
                    $metadataToBeSaved = true;
                    $clientDefsToBeSet = true;
                }
            }
        }

        if (array_key_exists('dynamicLogicRequired', $fieldDefs)) {
            if (!is_null($fieldDefs['dynamicLogicRequired'])) {
                $this->prepareClientDefsFieldsDynamicLogic($clientDefs, $name);
                $clientDefs['dynamicLogic']['fields'][$name]['required'] = $fieldDefs['dynamicLogicRequired'];
                $metadataToBeSaved = true;
                $clientDefsToBeSet = true;
            } else {
                if ($this->getMetadata()->get(['clientDefs', $scope, 'dynamicLogic', 'fields', $name, 'required'])) {
                    $this->prepareClientDefsFieldsDynamicLogic($clientDefs, $name);
                    $clientDefs['dynamicLogic']['fields'][$name]['required'] = null;
                    $metadataToBeSaved = true;
                    $clientDefsToBeSet = true;
                }
            }
        }

        if (array_key_exists('dynamicLogicOptions', $fieldDefs)) {
            if (!is_null($fieldDefs['dynamicLogicOptions'])) {
                $this->prepareClientDefsOptionsDynamicLogic($clientDefs, $name);
                $clientDefs['dynamicLogic']['options'][$name] = $fieldDefs['dynamicLogicOptions'];
                $metadataToBeSaved = true;
                $clientDefsToBeSet = true;
            } else {
                if ($this->getMetadata()->get(['clientDefs', $scope, 'dynamicLogic', 'options', $name])) {
                    $this->prepareClientDefsOptionsDynamicLogic($clientDefs, $name);
                    $clientDefs['dynamicLogic']['options'][$name] = null;
                    $metadataToBeSaved = true;
                    $clientDefsToBeSet = true;
                }
            }
        }

        if ($clientDefsToBeSet) {
            $this->getMetadata()->set('clientDefs', $scope, $clientDefs);
        }

        $oldFieldDefs = $this->getMetadata()->get(['entityDefs', $scope, 'fields', $name]);

        $entityDefs = $this->normalizeDefs($scope, $name, $fieldDefs);

        if (!empty($entityDefs)) {
            $this->getMetadata()->set('entityDefs', $scope, $entityDefs);
            $metadataToBeSaved = true;
            $this->isChanged = true;
        }

        if ($metadataToBeSaved) {
            $result &= $this->getMetadata()->save();

            if (isset($oldFieldDefs['isMultilang']) && $oldFieldDefs['isMultilang'] == true && !$this->getMetadata()->get(['entityDefs', $scope, 'fields', $name, 'isMultilang'], false)) {
                (new \Espo\Jobs\TreoCleanup($this->getInjection('container')))->run();
            }

            $event = new Event(['scope' => $scope, 'field' => $name, 'oldFieldDefs' => $oldFieldDefs]);

            $this->dispatch('FieldManager', 'afterSave', $event);

            $this->processHook('afterSave', $type, $scope, $name, $fieldDefs, array('isNew' => $isNew));
        }

        return (bool)$result;
    }

    protected function prepareClientDefsFieldsDynamicLogic(&$clientDefs, $name)
    {
        if (!array_key_exists('dynamicLogic', $clientDefs)) {
            $clientDefs['dynamicLogic'] = array();
        }
        if (!array_key_exists('fields', $clientDefs['dynamicLogic'])) {
            $clientDefs['dynamicLogic']['fields'] = array();
        }
        if (!array_key_exists($name, $clientDefs['dynamicLogic']['fields'])) {
            $clientDefs['dynamicLogic']['fields'][$name] = array();
        }
    }

    protected function prepareClientDefsOptionsDynamicLogic(&$clientDefs, $name)
    {
        if (!array_key_exists('dynamicLogic', $clientDefs)) {
            $clientDefs['dynamicLogic'] = array();
        }
        if (!array_key_exists('options', $clientDefs['dynamicLogic'])) {
            $clientDefs['dynamicLogic']['options'] = array();
        }
        if (!array_key_exists($name, $clientDefs['dynamicLogic']['options'])) {
            $clientDefs['dynamicLogic']['options'][$name] = array();
        }
    }

    public function delete($scope, $name)
    {
        if ($this->isCore($scope, $name)) {
            throw new Error('Cannot delete core field [' . $name . '] in ' . $scope);
        }

        $type = $this->getMetadata()->get(['entityDefs', $scope, 'fields', $name, 'type']);

        $this->processHook('beforeRemove', $type, $scope, $name);

        $unsets = array(
            'fields.' . $name,
            'links.' . $name,
        );

        $this->getMetadata()->delete('entityDefs', $scope, $unsets);

        $this->getMetadata()->delete(
            'clientDefs', $scope, [
                'dynamicLogic.fields.' . $name,
                'dynamicLogic.options.' . $name
            ]
        );

        $res = $this->getMetadata()->save();
        $this->deleteLabel($scope, $name);

        $subFieldsDefs = $this->getMetadata()->get(['fields', $type, 'fields']);
        if ($subFieldsDefs) {
            foreach ($subFieldsDefs as $partField => $partFieldData) {
                if ($this->getMetadata()->get(['fields', $type, 'fields', 'naming']) === 'prefix') {
                    $subFieldName = $partField . ucfirst($name);
                } else {
                    $subFieldName = $name . ucfirst($partField);
                }
                $this->deleteLabel($scope, $subFieldName);
            }
        }

        $this->getLanguage()->save();
        if ($this->getBaseLanguage()->getLanguage() !== $this->getLanguage()->getLanguage()) {
            $this->getBaseLanguage()->save();
        }

        $this->processHook('afterRemove', $type, $scope, $name);

        return (bool)$res;
    }

    public function resetToDefault($scope, $name)
    {
        if (!$this->isCore($scope, $name)) {
            throw new Error('Cannot reset to default custom field [' . $name . '] in ' . $scope);
        }

        if (!$this->getMetadata()->get(['entityDefs', $scope, 'fields', $name])) {
            throw new Error('Not found field [' . $name . '] in ' . $scope);
        }

        $this->getMetadata()->delete('entityDefs', $scope, ['fields.' . $name]);
        $this->getMetadata()->delete(
            'clientDefs', $scope, [
                'dynamicLogic.fields.' . $name,
                'dynamicLogic.options.' . $name
            ]
        );
        $this->getMetadata()->save();

        $this->getLanguage()->delete($scope, 'fields', $name);
        $this->getLanguage()->delete($scope, 'tooltips', $name);

        $this->getLanguage()->save();
    }

    protected function setLabel($scope, $name, $value, $isNew, $isCustom)
    {
        if ($isNew || $isCustom) {
            $this->getBaseLanguage()->set($scope, 'fields', $name, $value);
        }

        $this->getLanguage()->set($scope, 'fields', $name, $value);
    }

    protected function setTooltipText($scope, $name, $value, $isNew, $isCustom)
    {
        if ($value && $value !== '') {
            $this->getLanguage()->set($scope, 'tooltips', $name, $value);
            $this->getBaseLanguage()->set($scope, 'tooltips', $name, $value);
        } else {
            $this->getLanguage()->delete($scope, 'tooltips', $name);
            $this->getBaseLanguage()->delete($scope, 'tooltips', $name);
        }
    }

    protected function deleteLabel($scope, $name)
    {
        $this->getLanguage()->delete($scope, 'fields', $name);
        $this->getLanguage()->delete($scope, 'tooltips', $name);

        $this->getBaseLanguage()->delete($scope, 'fields', $name);
        $this->getBaseLanguage()->delete($scope, 'tooltips', $name);
    }

    protected function getFieldDefs($scope, $name)
    {
        return $this->getMetadata()->get('entityDefs' . '.' . $scope . '.fields.' . $name);
    }

    protected function getLinkDefs($scope, $name)
    {
        return $this->getMetadata()->get('entityDefs' . '.' . $scope . '.links.' . $name);
    }

    /**
     * Prepare input fieldDefs, remove unnecessary fields
     *
     * @param string $fieldName
     * @param array  $fieldDefs
     * @param string $scope
     *
     * @return array
     */
    protected function prepareFieldDefs($scope, $name, $fieldDefs)
    {
        foreach (self::UNNECESSARY_FIELDS as $fieldName) {
            if (isset($fieldDefs[$fieldName])) {
                unset($fieldDefs[$fieldName]);
            }
        }

        $currentOptionList = array_keys((array)$this->getFieldDefs($scope, $name));
        foreach ($fieldDefs as $defName => $defValue) {
            if ((!isset($defValue) || $defValue === '') && !in_array($defName, $currentOptionList)) {
                unset($fieldDefs[$defName]);
            }
        }

        return $fieldDefs;
    }

    /**
     * Add all needed block for a field defenition
     *
     * @param string $scope
     * @param string $fieldName
     * @param array  $fieldDefs
     *
     * @return array
     */
    protected function normalizeDefs($scope, $fieldName, array $fieldDefs)
    {
        $fieldDefs = $this->prepareFieldDefs($scope, $fieldName, $fieldDefs);

        $metaFieldDefs = $this->getMetadataHelper()->getFieldDefsInFieldMeta($fieldDefs);
        if (isset($metaFieldDefs)) {
            $fieldDefs = Util::merge($metaFieldDefs, $fieldDefs);
        }

        if (isset($fieldDefs['linkDefs'])) {
            $linkDefs = $fieldDefs['linkDefs'];
            unset($fieldDefs['linkDefs']);
        }

        $defs = array();

        $currentFieldDefs = (array)$this->getFieldDefs($scope, $fieldName);

        $diffFieldDefs = $this->getDiffDefs($currentFieldDefs, $fieldDefs);
        if (!empty($diffFieldDefs)) {
            $defs['fields'] = array(
                $fieldName => $diffFieldDefs,
            );
        }

        /** Save links for a field. */
        $metaLinkDefs = $this->getMetadataHelper()->getLinkDefsInFieldMeta($scope, $fieldDefs);
        if (isset($linkDefs) || isset($metaLinkDefs)) {

            $metaLinkDefs = isset($metaLinkDefs) ? $metaLinkDefs : array();
            $linkDefs = isset($linkDefs) ? $linkDefs : array();

            $normalizedLinkdDefs = Util::merge($metaLinkDefs, $linkDefs);
            if (!empty($normalizedLinkdDefs)) {
                $defs['links'] = array(
                    $fieldName => $normalizedLinkdDefs,
                );
            }
        }

        return $defs;
    }

    protected function getDiffDefs($defs, $newDefs)
    {
        $diff = array();

        foreach ($newDefs as $optionName => $data) {
            if (!array_key_exists($optionName, $defs)) {
                $diff[$optionName] = $data;
                continue;
            }

            if (is_object($data) || is_array($data)) {
                $diff[$optionName] = $data;
                continue;
            }

            if ($data !== $defs[$optionName]) {
                $diff[$optionName] = $data;
            }
        }

        return $diff;
    }

    /**
     * Check if changed metadata defenition for a field except 'label'
     *
     * @param string $scope
     * @param string $name
     * @param array  $fieldDefs
     *
     * @return boolean
     */
    protected function isDefsChanged($scope, $name, $fieldDefs)
    {
        $fieldDefs = $this->prepareFieldDefs($scope, $name, $fieldDefs);
        $currentFieldDefs = $this->getFieldDefs($scope, $name);

        $diffDefs = Util::arrayDiff($currentFieldDefs, $fieldDefs);

        $this->isChanged = empty($diffDefs) ? false : true;

        return $this->isChanged;
    }


    protected function isLabelChanged($scope, $category, $name, $newLabel)
    {
        $currentLabel = $this->getLanguage()->get([$scope, $category, $name]);

        if ($newLabel != $currentLabel) {
            return true;
        }

        return false;
    }


    public function isChanged()
    {
        return $this->isChanged;
    }

    /**
     * Check if a field is core field
     *
     * @param string $name
     * @param string $scope
     *
     * @return boolean
     */
    protected function isCore($scope, $name)
    {
        $existingField = $this->getFieldDefs($scope, $name);
        if (isset($existingField) && (!isset($existingField['isCustom']) || !$existingField['isCustom'])) {
            return true;
        }

        return false;
    }

    /**
     * Get attribute list by type
     *
     * @param string $scope
     * @param string $name
     * @param string $type
     *
     * @return array
     */
    protected function getAttributeListByType(string $scope, string $name, string $type): array
    {
        $fieldType = $this->getMetadata()->get('entityDefs.' . $scope . '.fields.' . $name . '.type');

        if (!$fieldType) {
            return [];
        }

        $defs = $this->getMetadata()->get('fields.' . $fieldType);
        if (!$defs) {
            return [];
        }

        if (is_object($defs)) {
            $defs = get_object_vars($defs);
        }

        $fieldList = [];
        if (isset($defs[$type . 'Fields'])) {
            $list = $defs[$type . 'Fields'];
            $naming = 'suffix';
            if (isset($defs['naming'])) {
                $naming = $defs['naming'];
            }
            if ($naming == 'prefix') {
                foreach ($list as $f) {
                    $fieldList[] = $f . ucfirst($name);
                }
            } else {
                foreach ($list as $f) {
                    $fieldList[] = $name . ucfirst($f);
                }
            }
        } else {
            if ($type == 'actual') {
                $fieldList[] = $name;
            }
        }

        return $fieldList;
    }

    /**
     * Get actual attribute list
     *
     * @param string $scope
     * @param string $name
     *
     * @return array
     */
    public function getActualAttributeList($scope, $name)
    {
        return $this->getAttributeListByType($scope, $name, 'actual');
    }

    /**
     * Get not actual attribute list
     *
     * @param string $scope
     * @param string $name
     *
     * @return array
     */
    public function getNotActualAttributeList($scope, $name)
    {
        return $this->getAttributeListByType($scope, $name, 'notActual');
    }

    /**
     * Get attribute list
     *
     * @param string $scope
     * @param string $name
     *
     * @return array
     */
    public function getAttributeList($scope, $name)
    {
        // prepare data
        $actualAttributeList = $this->getActualAttributeList($scope, $name);
        $notActualAttributeList = $this->getNotActualAttributeList($scope, $name);

        return array_merge($actualAttributeList, $notActualAttributeList);
    }

    protected function processHook($methodName, $type, $scope, $name, &$defs = null, $options = array())
    {
        $hook = $this->getHook($type);
        if (!$hook) {
            return;
        }

        if (!method_exists($hook, $methodName)) {
            return;
        }

        $hook->$methodName($scope, $name, $defs, $options);
    }

    /**
     * Get hook for fields
     *
     * @param string $type
     *
     * @return BaseHook|null
     */
    protected function getHook($type)
    {
        // prepare hook
        $hook = null;

        // get class name
        $className = $this->getMetadata()->get(['fields', $type, 'hookClassName']);

        if (!empty($className) && class_exists($className)) {
            // create hook
            $hook = new $className();

            // inject dependencies
            foreach ($hook->getDependencyList() as $name) {
                $hook->inject($name, $this->getInjection('container')->get($name));
            }
        }

        return $hook;
    }

    protected function getConfig():Config
    {
        return $this->getInjection('container')->get('config');
    }

    protected function dispatch(string $target, string $action, Event $event): Event
    {
        return $this->getInjection('container')->get('eventManager')->dispatch($target, $action, $event);
    }
}