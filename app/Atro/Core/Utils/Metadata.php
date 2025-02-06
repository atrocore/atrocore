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

namespace Atro\Core\Utils;

use Atro\Core\Container;
use Atro\Core\DataManager;
use Atro\Core\EventManager\Event;
use Atro\Core\EventManager\Manager as EventManager;
use Atro\Core\Exceptions\Error;
use Atro\Core\ModuleManager\Manager as ModuleManager;
use Espo\Core\Utils\File\Unifier;
use Espo\Core\Utils\File\Manager as FileManager;
use Espo\Core\Utils\Metadata\Helper;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\DataUtil;

class Metadata
{
    private Container $container;
    private FileManager $fileManager;
    private ModuleManager $moduleManager;
    private EventManager $eventManager;
    private DataManager $dataManager;
    private Unifier $objUnifier;
    private Helper $helper;

    private $data = null;
    private $objData = null;
    private string $customPath = 'data/metadata';
    private array $deletedData = [];
    private array $changedData = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->fileManager = $container->get('fileManager');
        $this->dataManager = $container->get('dataManager');
        $this->moduleManager = $container->get('moduleManager');
        $this->eventManager = $container->get('eventManager');
        $this->objUnifier = new Unifier($this->fileManager, $this, true);
        $this->helper = new Helper($this);
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

            $additionalFields = $this->helper->getAdditionalFieldList($field, $fieldData, $this->get("fields"));
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
     * @param bool $isJSON
     * @param bool $reload
     *
     * @return string|array
     */
    public function getAll(bool $isJSON = false, bool $reload = false)
    {
        if ($reload) {
            $this->init($reload);
        }

        if ($isJSON) {
            return Json::encode($this->data);
        }

        return $this->data;
    }

    public function getAllForFrontend()
    {
        $data = $this->getObjData();

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
                if (!is_object($entityDefs) || !property_exists($entityDefs,
                        'fields') || !is_object($entityDefs->fields) || property_exists($entityDefs->fields, 'id')) {
                    continue;
                }

                $id = new \stdClass();
                $id->type = 'varchar';
                $id->emDisabled = true;

                $data->entityDefs->{$entityType}->fields = (object)array_merge(['id' => $id],
                    (array)$data->entityDefs->{$entityType}->fields);
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

            $this->objData = $this
                ->getEventManager()
                ->dispatch('Metadata', 'loadData', new Event(['data' => $this->objData]))
                ->getArgument('data');

            $this->dataManager->setCacheData('metadata', $this->objData);
        }

        $data = $this
            ->getEventManager()
            ->dispatch('Metadata', 'modify', new Event(['data' => $this->objData]))
            ->getArgument('data');

        $data = $this
            ->getEventManager()
            ->dispatch('Metadata', 'afterInit', new Event(['data' => $data]))
            ->getArgument('data');

        $this->loadUiHandlers($data);
        $this->clearMetadata($data);

        // set object data
        $this->objData = Json::decode(Json::encode($data));

        // clearing metadata
        $this->clearingMetadata();
    }

    protected function loadUiHandlers(array &$metadata): void
    {
        /** @var Config $config */
        $config = $this->container->get('config');

        if (!$config->get('isInstalled', false)) {
            return;
        }

        if (!empty($this->container->get('memoryStorage')->get('ignorePushUiHandler'))) {
            return;
        }

        // remove dynamic logic from data
        foreach ($this->objData['clientDefs'] as $entity => $defs) {
            if (isset($defs['dynamicLogic'])) {
                unset($this->objData['clientDefs'][$entity]['dynamicLogic']);
            }
        }

        $mapper = [
            'ui_read_only'       => 'readOnly',
            'ui_visible'         => 'visible',
            'ui_required'        => 'required',
            'ui_set_value'       => 'setValue',
            'ui_update_by_ai'    => 'updateByAi',
            'ui_disable_options' => 'disableOptions'
        ];

        $data = [];
        foreach ($config->get('referenceData.UiHandler') ?? [] as $v) {
            if (!isset($mapper[$v['type']]) || empty($v['triggerAction']) || empty($v['isActive'])) {
                continue;
            }

            switch ($v['triggerAction']) {
                case 'ui_on_change':
                    $triggerAction = 'onChange';
                    break;
                case 'ui_on_focus':
                    $triggerAction = 'onFocus';
                    break;
                case 'ui_on_button_click':
                    $triggerAction = 'onActionButtonClick';
                    break;
                default:
                    continue 2;
            }

            $conditions = ['type' => $v['conditionsType']];
            if ($v['conditionsType'] === 'basic') {
                $val = @json_decode((string)$v['conditions'], true);
                if (is_array($val)) {
                    $conditions = array_merge($conditions, $val);
                }
            } else {
                $conditions['script'] = (string)$v['conditions'];
            }

            if (!array_key_exists('triggerFields', $v)) {
                $v['triggerFields'] = null;
            }

            $row = [];
            $row['type'] = $mapper[$v['type']];
            $row['triggerAction'] = $triggerAction;
            $row['triggerFields'] = @json_decode((string)$v['triggerFields'], true);
            $row['conditions'] = $conditions;

            switch ($row['type']) {
                case 'readOnly':
                case 'visible':
                case 'required':
                    $row['targetFields'] = is_array($v['fields']) ? $v['fields'] : @json_decode((string)$v['fields'], true);
                    $row['targetPanels'] = [];
                    if (!empty($v['relationships'])) {
                        $row['targetPanels'] = is_array($v['relationships']) ? $v['relationships'] : @json_decode((string)$v['relationships'], true);
                    }
                    break;
                case 'disableOptions':
                    $row['targetFields'] = is_array($v['fields']) ? $v['fields'] : @json_decode((string)$v['fields'], true);
                    $row['disabledOptions'] = is_array($v['disabledOptions']) ? $v['disabledOptions'] : @json_decode((string)$v['disabledOptions'], true);
                    break;
                case 'setValue':
                    $parsedData = is_array($v['data']) ? $v['data'] : @json_decode((string)$v['data'], true);
                    if (empty($parsedData['field']['updateType'])) {
                        continue 2;
                    }
                    $row['updateType'] = $parsedData['field']['updateType'];
                    $row['overwrite'] = !empty($parsedData['field']['overwrite']);
                    switch ($parsedData['field']['updateType']) {
                        case 'basic':
                            $row['updateData'] = $parsedData['fieldData'];
                            break;
                        case 'script':
                            $row['updateData'] = $parsedData['field']['updateScript'];
                            break;
                    }
                    break;
                case 'updateByAi':
                    $parsedData = is_array($v['data']) ? $v['data'] : @json_decode((string)$v['data'], true);
                    if (empty($parsedData['field']['aiEngine'])) {
                        continue 2;
                    }
                    $row['targetFields'] = is_array($v['fields']) ? $v['fields'] : @json_decode((string)$v['fields'], true);
                    $row['aiEngine'] = $parsedData['field']['aiEngine'];
                    $row['confirmPromptByPopup'] = !empty($parsedData['field']['confirmPromptByPopup']);
                    $row['prompt'] = $parsedData['field']['prompt'];
                    $row['buttonLabel'] = $parsedData['field']['buttonLabel'] ?? '';
                    break;
            }

            $data['clientDefs'][$v['entityType']]['uiHandler'][] = $row;
        }

        $metadata = Util::merge($metadata, $data);
    }

    public function loadData(bool $ignoreCustom = false): \stdClass
    {
        $content = $this->unify(CORE_PATH . '/Atro/Resources/metadata');
        foreach ($this->getModules() as $module) {
            $module->loadMetadata($content);
        }

        if (!$ignoreCustom) {
            $content = DataUtil::merge($content, $this->unify('data/metadata'));
        }

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
        return $this->objUnifier->unify('metadata', $path, true);
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
        if (!isset($data->entityDefs) || !is_object($data->entityDefs)) {
            return $data;
        }

        $fieldDefinitionList = Util::objectToArray($data->fields);

        foreach (get_object_vars($data->entityDefs) as $entityType => $entityDefsItem) {
            if (!isset($entityDefsItem->fields) || !is_object($entityDefsItem->fields)) {
                continue;
            }

            foreach (get_object_vars($entityDefsItem->fields) as $field => $fieldDefsItem) {
                $additionalFields = $this->helper->getAdditionalFieldList($field, Util::objectToArray($fieldDefsItem), $fieldDefinitionList);
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
     * @param mixed $default
     *
     * @return object|mixed
     */
    public function getCustom($key1, $key2, $default = null)
    {
        $filePath = [$this->customPath, $key1, $key2 . '.json'];
        $fileContent = $this->fileManager->getContents($filePath);

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
     * @param array $data
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

        $result = $this->fileManager->putContents($filePath, $changedData);

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
     * @param string $key1
     * @param string $key2
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

                        $additionalFields = $this->helper->getAdditionalFieldList($fieldName,
                            $this->get($fieldPath, []), $fieldDefinitionList);
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
     * @param array $data
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
                        $result &= $this->fileManager->mergeContents(array($path, $key1, $key2 . '.json'), $data, true);
                    }
                }
            }
        }

        if (!empty($this->deletedData)) {
            foreach ($this->deletedData as $key1 => $keyData) {
                foreach ($keyData as $key2 => $unsetData) {
                    if (!empty($unsetData)) {
                        $rowResult = $this->fileManager->unsetContents(array($path, $key1, $key2 . '.json'),
                            $unsetData, true);
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
        return $this->get("scopes.$scopeName.module");
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
            'emDisabled',
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
                            if (!empty($fieldDefs['type']) && $fieldDefs['type'] === 'bool' && $param === 'notNull') {
                                continue;
                            }
                            unset($data['entityDefs'][$entityType]['fields'][$field][$param]);
                        }
                    }
                }
            }
        }
    }
}
