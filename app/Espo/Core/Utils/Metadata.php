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

declare(strict_types=1);

namespace Espo\Core\Utils;

use Espo\Core\DataManager;
use Espo\Core\EventManager\Event;
use Espo\Core\EventManager\Manager as EventManager;
use Espo\Core\Exceptions\Error;
use Espo\Core\Utils\File\Unifier;
use Atro\Core\ModuleManager\Manager as ModuleManager;

/**
 * Class Metadata
 */
class Metadata
{
    /**
     * @var File\Manager
     */
    private $fileManager;

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var EventManager
     */
    private $eventManager;

    /**
     * @var DataManager
     */
    private $dataManager;

    /**
     * @var null|array
     */
    private $data = null;

    /**
     * @var null|array
     */
    private $objData = null;

    /**
     * @var Unifier|null
     */
    private $unifier;

    /**
     * @var Unifier|null
     */
    private $objUnifier;

    /**
     * @var Metadata\Helper|null
     */
    private $metadataHelper;

    /**
     * @var string
     */
    private $customPath = 'custom/Espo/Custom/Resources/metadata';

    /**
     * @var array
     */
    private $deletedData = [];

    /**
     * @var array
     */
    private $changedData = [];

    /**
     * Metadata constructor.
     *
     * @param File\Manager  $fileManager
     * @param DataManager   $dataManager
     * @param ModuleManager $moduleManager
     * @param EventManager  $eventManager
     */
    public function __construct(File\Manager $fileManager, DataManager $dataManager, ModuleManager $moduleManager, EventManager $eventManager)
    {
        $this->fileManager = $fileManager;

        $this->dataManager = $dataManager;
        $this->moduleManager = $moduleManager;
        $this->eventManager = $eventManager;
    }

    public function isCached(): bool
    {
        return $this->dataManager->isCacheExist('metadata');
    }

    public function init(bool $reload = false): void
    {
        $this->data = json_decode(json_encode($this->getObjData($reload)), true);
    }

    public function getFieldList(string $scope, string $field): array
    {
        // prepare result
        $result = [];

        // get field data
        $fieldData = $this->get("entityDefs.$scope.fields.$field");

        if (!empty($fieldData)) {
            // prepare result
            $result[$field] = $fieldData;

            $additionalFields = $this
                ->getMetadataHelper()
                ->getAdditionalFieldList($field, $fieldData, $this->get("fields"));
            if (!empty($additionalFields)) {
                // prepare result
                $result = $result + $additionalFields;
            }
        }

        return $result;
    }

    /**
     * Get Metadata
     *
     * @param mixed string|array $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function get($key = null, $default = null)
    {
        return Util::getValueByKey($this->getData(), $key, $default);
    }

    /**
     * Get All Metadata context
     *
     * @param      $isJSON
     * @param bool $reload
     *
     * @return string|array
     */
    public function getAll($isJSON = false, $reload = false)
    {
        if ($reload) {
            $this->init($reload);
        }

        if ($isJSON) {
            return Json::encode($this->data);
        }
        return $this->data;
    }

    /**
     * Get Object Metadata
     *
     * @param mixed string|array $key
     * @param mixed $default
     *
     * @return object
     */
    public function getObjects($key = null, $default = null)
    {
        $objData = $this->getObjData();

        return Util::getValueByKey($objData, $key, $default);
    }

    public function getAllObjects($isJSON = false, $reload = false)
    {
        $objData = $this->getObjData($reload);

        if ($isJSON) {
            return Json::encode($objData);
        }

        return $objData;
    }

    public function getAllForFrontend()
    {
        $data = $this->getAllObjects();

        $frontendHiddenPathList = $data->app->frontendHiddenPathList;
        $frontendHiddenPathList[] = ['app', 'frontendHiddenPathList'];

        foreach ($frontendHiddenPathList as $row) {
            $p =& $data;
            $path = [&$p];
            foreach ($row as $i => $item) {
                if (is_array($item)) {
                    break;
                }
                if (!property_exists($p, $item)) {
                    break;
                }
                if ($i == count($row) - 1) {
                    unset($p->$item);
                    $o =& $p;
                    for ($j = $i - 1; $j > 0; $j--) {
                        if (is_object($o) && !count(get_object_vars($o))) {
                            $o =& $path[$j];
                            $k = $row[$j];
                            unset($o->$k);
                        } else {
                            break;
                        }
                    }
                } else {
                    $p =& $p->$item;
                    $path[] = &$p;
                }
            }
        }

        if (property_exists($data, 'entityDefs')) {
            foreach ($data->entityDefs as $entityType => $entityDefs) {
                if (!property_exists($entityDefs, 'fields') || property_exists($entityDefs->fields, 'id')) {
                    continue;
                }
                $data->entityDefs->{$entityType}->fields->id = new \stdClass();
                $data->entityDefs->{$entityType}->fields->id->type = 'varchar';
            }
        }

        return $data;
    }

    /**
     * Get metadata array
     *
     * @return array
     */
    protected function getData()
    {
        if (empty($this->data) || !is_array($this->data)) {
            $this->init();
        }

        return $this->data;
    }

    protected function objInit(bool $reload = false): void
    {
        $this->objData = $this->dataManager->getCacheData('metadata');
        if ($this->objData === null || $reload) {
            $this->objData = Json::decode(Json::encode($this->loadData()), true);
            $this->dataManager->setCacheData('metadata', $this->objData);
        }

        $data = $this->getEventManager()->dispatch('Metadata', 'modify', new Event(['data' => $this->objData]))->getArgument('data');
        $data = $this->getEventManager()->dispatch('Metadata', 'afterInit', new Event(['data' => $data]))->getArgument('data');

        $this->clearMetadata($data);

        // set object data
        $this->objData = Json::decode(Json::encode($data));

        // clearing metadata
        $this->clearingMetadata();
    }

    protected function loadData()
    {
        // load core
        $content = $this->unify(CORE_PATH . '/Atro/Resources/metadata');

        // load modules
        foreach ($this->getModules() as $module) {
            $module->loadMetadata($content);
        }

        // load custom
        $content = DataUtil::merge($content, $this->unify('custom/Espo/Custom/Resources/metadata'));

        return $this->addAdditionalFieldsObj($content);
    }

    protected function clearingMetadata(): void
    {
        foreach ($this->objData->entityDefs as $scope => $rows) {
            if (isset($rows->fields)) {
                foreach ($rows->fields as $field => $params) {
                    if (!isset($params->type)) {
                        unset($this->objData->entityDefs->$scope->fields->$field);
                    }
                }
            }
        }
    }

    protected function unify(string $path): \stdClass
    {
        return $this->getObjUnifier()->unify('metadata', $path, true);
    }

    /**
     * @return EventManager
     */
    public function getEventManager(): EventManager
    {
        return $this->eventManager;
    }

    protected function getObjData($reload = false)
    {
        if (!isset($this->objData) || $reload) {
            $this->objInit($reload);
        }

        return $this->objData;
    }

    protected function addAdditionalFieldsObj($data)
    {
        if (!isset($data->entityDefs)) {
            return $data;
        }

        $fieldDefinitionList = Util::objectToArray($data->fields);

        foreach (get_object_vars($data->entityDefs) as $entityType => $entityDefsItem) {
            if (!isset($entityDefsItem->fields)) {
                continue;
            }
            foreach (get_object_vars($entityDefsItem->fields) as $field => $fieldDefsItem) {
                $additionalFields = $this->getMetadataHelper()->getAdditionalFieldList($field, Util::objectToArray($fieldDefsItem), $fieldDefinitionList);

                if (!$additionalFields) {
                    continue;
                }
                foreach ($additionalFields as $subFieldName => $subFieldParams) {
                    if (isset($entityDefsItem->fields->$subFieldName)) {
                        $data->entityDefs->$entityType->fields->$subFieldName = DataUtil::merge(Util::arrayToObject($subFieldParams), $entityDefsItem->fields->$subFieldName);
                    } else {
                        $data->entityDefs->$entityType->fields->$subFieldName = Util::arrayToObject($subFieldParams);
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Get metadata definition in custom directory
     *
     * @param string|array $key
     * @param mixed        $default
     *
     * @return object|mixed
     */
    public function getCustom($key1, $key2, $default = null)
    {
        $filePath = [$this->customPath, $key1, $key2 . '.json'];
        $fileContent = $this->getFileManager()->getContents($filePath);

        if ($fileContent) {
            return Json::decode($fileContent, true);
        }

        return $default;
    }

    /**
     * Set and save metadata in custom directory. The data is not merging with existing data. Use getCustom() to get existing data.
     *
     * @param string $key1
     * @param string $key2
     * @param array  $data
     *
     * @return boolean
     */
    public function saveCustom($key1, $key2, $data)
    {
        if (is_object($data)) {
            foreach ($data as $key => $item) {
                if ($item == new \stdClass()) {
                    unset($data->$key);
                }
            }
        }

        $filePath = [$this->customPath, $key1, $key2 . '.json'];
        $changedData = Json::encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $result = $this->getFileManager()->putContents($filePath, $changedData);

        $this->init(true);

        return true;
    }

    /**
     * Set Metadata data
     * Ex. $key1 = menu, $key2 = Account then will be created a file metadataFolder/menu/Account.json
     *
     * @param string $key1
     * @param string $key2
     * @param JSON string $data
     *
     * @return bool
     */
    public function set($key1, $key2, $data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $item) {
                if (is_array($item) && empty($item)) {
                    unset($data[$key]);
                }
            }
        }

        $newData = array(
            $key1 => array(
                $key2 => $data,
            ),
        );

        $this->changedData = Util::merge($this->changedData, $newData);
        $this->data = Util::merge($this->getData(), $newData);

        $this->undelete($key1, $key2, $data);
    }

    /**
     * Unset some fields and other stuff in metadat
     *
     * @param string         $key1
     * @param string         $key2
     * @param array | string $unsets Ex. 'fields.name'
     *
     * @return bool
     */
    public function delete($key1, $key2, $unsets = null)
    {
        if (!is_array($unsets)) {
            $unsets = (array)$unsets;
        }

        switch ($key1) {
            case 'entityDefs':
                //unset related additional fields, e.g. a field with "address" type
                $fieldDefinitionList = $this->get('fields');

                $unsetList = $unsets;
                foreach ($unsetList as $unsetItem) {
                    if (preg_match('/fields\.([^\.]+)/', $unsetItem, $matches) && isset($matches[1])) {
                        $fieldName = $matches[1];
                        $fieldPath = [$key1, $key2, 'fields', $fieldName];

                        $additionalFields = $this->getMetadataHelper()->getAdditionalFieldList($fieldName, $this->get($fieldPath, []), $fieldDefinitionList);
                        if (is_array($additionalFields)) {
                            foreach ($additionalFields as $additionalFieldName => $additionalFieldParams) {
                                $unsets[] = 'fields.' . $additionalFieldName;
                            }
                        }
                    }
                }
                break;
        }

        $normalizedData = array(
            '__APPEND__',
        );
        $metadataUnsetData = array();
        foreach ($unsets as $unsetItem) {
            $normalizedData[] = $unsetItem;
            $metadataUnsetData[] = implode('.', array($key1, $key2, $unsetItem));
        }

        $unsetData = array(
            $key1 => array(
                $key2 => $normalizedData
            )
        );

        $this->deletedData = Util::merge($this->deletedData, $unsetData);
        $this->deletedData = Util::unsetInArrayByValue('__APPEND__', $this->deletedData, true);

        $this->data = Util::unsetInArray($this->getData(), $metadataUnsetData, true);
    }

    /**
     * Undelete the deleted items
     *
     * @param string $key1
     * @param string $key2
     * @param array  $data
     *
     * @return void
     */
    protected function undelete($key1, $key2, $data)
    {
        if (isset($this->deletedData[$key1][$key2])) {
            foreach ($this->deletedData[$key1][$key2] as $unsetIndex => $unsetItem) {
                $value = Util::getValueByKey($data, $unsetItem);
                if (isset($value)) {
                    unset($this->deletedData[$key1][$key2][$unsetIndex]);
                }
            }
        }
    }

    /**
     * Clear unsaved changes
     *
     * @return void
     */
    public function clearChanges()
    {
        $this->changedData = array();
        $this->deletedData = array();
        $this->init(true);
    }

    /**
     * Save changes
     *
     * @return bool
     */
    public function save()
    {
        $path = $this->customPath;

        $result = true;
        if (!empty($this->changedData)) {
            foreach ($this->changedData as $key1 => $keyData) {
                foreach ($keyData as $key2 => $data) {
                    if (!empty($data)) {
                        $result &= $this->getFileManager()->mergeContents(array($path, $key1, $key2 . '.json'), $data, true);
                    }
                }
            }
        }

        if (!empty($this->deletedData)) {
            foreach ($this->deletedData as $key1 => $keyData) {
                foreach ($keyData as $key2 => $unsetData) {
                    if (!empty($unsetData)) {
                        $rowResult = $this->getFileManager()->unsetContents(array($path, $key1, $key2 . '.json'), $unsetData, true);
                        if ($rowResult == false) {
                            $GLOBALS['log']->warning('Metadata items [' . $key1 . '.' . $key2 . '] can be deleted for custom code only.');
                        }
                        $result &= $rowResult;
                    }
                }
            }
        }

        if ($result == false) {
            throw new Error("Error saving metadata. See log file for details.");
        }

        $this->clearChanges();

        return (bool)$result;
    }

    public function getEntityPath(string $entityName, string $delim = '\\'): string
    {
        $path = $this->getScopePath($entityName, $delim);

        $path = implode($delim, [$path, 'Entities', Util::normilizeClassName(ucfirst($entityName))]);

        if (!class_exists($path)) {
            $path = implode($delim, ['Atro', 'Entities', Util::normilizeClassName(ucfirst($entityName))]);
        }

        if (!class_exists($path)) {
            $path = implode($delim, ['Espo', 'Entities', Util::normilizeClassName(ucfirst($entityName))]);
        }

        if (!class_exists($path)) {
            $type = $this->get('scopes.' . $entityName . '.type');
            $path = "\\Atro\\Core\\Templates\\Entities\\$type";
        }

        return $path;
    }

    public function getRepositoryPath(string $entityName, string $delim = '\\'): string
    {
        $path = $this->getScopePath($entityName, $delim);

        $path = implode($delim, [$path, 'Repositories', Util::normilizeClassName(ucfirst($entityName))]);

        if (!class_exists($path)) {
            $path = implode($delim, ['Atro', 'Repositories', Util::normilizeClassName(ucfirst($entityName))]);
        }

        if (!class_exists($path)) {
            $path = implode($delim, ['Espo', 'Repositories', Util::normilizeClassName(ucfirst($entityName))]);
        }

        if (!class_exists($path)) {
            $type = $this->get('scopes.' . $entityName . '.type');
            $path = "\\Atro\\Core\\Templates\\Repositories\\$type";
        }

        return $path;
    }

    public function getScopeModuleName(string $scopeName): ?string
    {
        return $this->get('scopes.' . $scopeName . '.module', 'Espo');
    }

    public function getModules(): array
    {
        return $this->moduleManager->getModules();
    }

    public function isModuleInstalled(string $id): bool
    {
        foreach ($this->getModules() as $name => $module) {
            if ($name == $id) {
                return true;
            }
        }

        return false;
    }

    public function getDataManager(): DataManager
    {
        return $this->dataManager;
    }

    public function getScopePath(string $scopeName, string $delim = '/'): string
    {
        $moduleName = $this->getScopeModuleName($scopeName);

        if ($moduleName == 'TreoCore') {
            $moduleName = 'Treo';
        }

        $path = ($moduleName !== false) ? $moduleName : 'Treo';

        if ($delim != '/') {
            $path = str_replace('/', $delim, $path);
        }

        return $path;
    }

    protected function clearMetadata(array &$data): void
    {
        $boolParameters = [
            'notNull',
            'required',
            'audited',
            'readOnly',
            'unique',
            'index',
            'tooltip',
            'notStorable',
            'emHidden',
            'importDisabled',
            'exportDisabled',
            'massUpdateDisabled',
            'filterDisabled',
            'relationVirtualField',
        ];

        foreach ($data['entityDefs'] as $entityType => $entityDefs) {
            if (!empty($entityDefs['fields'])) {
                foreach ($entityDefs['fields'] as $field => $fieldDefs) {
                    foreach ($fieldDefs as $param => $paramValue) {
                        if (in_array($param, $boolParameters) && $paramValue === false) {
                            unset($data['entityDefs'][$entityType]['fields'][$field][$param]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Clear metadata variables when reload meta
     *
     * @return void
     */
    protected function clearVars()
    {
        $this->data = null;
    }

    protected function getFileManager(): File\Manager
    {
        return $this->fileManager;
    }

    protected function getUnifier(): Unifier
    {
        if (!isset($this->unifier)) {
            $this->unifier = new Unifier($this->fileManager, $this, false);
        }

        return $this->unifier;
    }

    protected function getObjUnifier(): Unifier
    {
        if (!isset($this->objUnifier)) {
            $this->objUnifier = new Unifier($this->fileManager, $this, true);
        }

        return $this->objUnifier;
    }

    protected function getMetadataHelper(): Metadata\Helper
    {
        if (!isset($this->metadataHelper)) {
            $this->metadataHelper = new Metadata\Helper($this);
        }

        return $this->metadataHelper;
    }
}
