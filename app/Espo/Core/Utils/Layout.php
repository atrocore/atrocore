<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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

use Atro\Core\Container;
use Espo\Core\Injectable;

/**
 * Class Layout
 */
class Layout extends Injectable
{
    protected array $changedData = [];

    public function __construct()
    {
        $this->addDependency('container');
    }

    public function isCustom(string $scope, string $name): bool
    {
        return file_exists($this->concatPath($this->getCustomPath($scope), $name . '.json'));
    }

    /**
     * Get a full path of the file
     *
     * @param string | array $folderPath - Folder path, Ex. myfolder
     * @param string         $filePath   - File path, Ex. file.json
     *
     * @return string
     */
    public function concatPath($folderPath, $filePath = null)
    {
        return Util::concatPath($folderPath, $filePath);
    }

    /**
     * Get Layout context
     *
     * @param string $scope
     * @param string $name
     *
     * @return json|string
     */
    public function get($scope, $name)
    {
        // prepare scope
        $scope = $this->sanitizeInput($scope);

        // prepare name
        $name = $this->sanitizeInput($name);

        // cache
        if (isset($this->changedData[$scope][$name])) {
            return Json::encode($this->changedData[$scope][$name]);
        }

        // compose
        $layout = $this->compose($scope, $name);

        // remove fields from layout if this fields not exist in metadata
        $layout = $this->disableNotExistingFields($scope, $name, $layout);

        if (in_array($name, ['list', 'listSmall'])) {
            foreach ($layout as $k => $row) {
                if (!empty($row['name']) && empty($row['notSortable']) && !empty($this->getMetadata()->get(['entityDefs', $scope, 'fields', $row['name'], 'notStorable']))) {
                    $layout[$k]['notSortable'] = true;
                }
            }
        }

        return Json::encode($layout);
    }

    /**
     * Set Layout data
     * Ex. $scope = Account, $name = detail then will be created a file layoutFolder/Account/detail.json
     *
     * @param array|string $data
     * @param string       $scope - ex. Account
     * @param string       $name  - detail
     *
     * @return void
     */
    public function set($data, $scope, $name)
    {
        $scope = $this->sanitizeInput($scope);
        $name = $this->sanitizeInput($name);

        if (empty($scope) || empty($name)) {
            return;
        }

        $this->changedData[$scope][$name] = $data;
    }

    /**
     * @param string $scope
     * @param string $name
     *
     * @return json|string
     */
    public function resetToDefault($scope, $name)
    {
        $scope = $this->sanitizeInput($scope);
        $name = $this->sanitizeInput($name);

        $filePath = 'custom/Espo/Custom/Resources/layouts/' . $scope . '/' . $name . '.json';
        if ($this->getFileManager()->isFile($filePath)) {
            $this->getFileManager()->removeFile($filePath);
        }
        if (!empty($this->changedData[$scope]) && !empty($this->changedData[$scope][$name])) {
            unset($this->changedData[$scope][$name]);
        }
        $this->getContainer()->get('dataManager')->clearCache();

        return $this->get($scope, $name);
    }

    public function resetAllToDefault(): bool
    {
        Util::removeDir('custom/Espo/Custom/Resources/layouts');
        $this->getContainer()->get('dataManager')->clearCache();

        return true;
    }

    /**
     * Save changes
     *
     * @return bool
     */
    public function save()
    {
        $result = true;

        if (!empty($this->changedData)) {
            foreach ($this->changedData as $scope => $rowData) {
                foreach ($rowData as $layoutName => $layoutData) {
                    if (empty($scope) || empty($layoutName)) {
                        continue;
                    }
                    $layoutPath = $this->getCustomPath($scope);
                    $data = Json::encode($layoutData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                    $result &= $this->getFileManager()->putContents(array($layoutPath, $layoutName . '.json'), $data);
                }
            }
        }

        if ($result == true) {
            $this->clearChanges();
        }

        return (bool)$result;
    }

    /**
     * Clear unsaved changes
     *
     * @return void
     */
    public function clearChanges(): void
    {
        $this->changedData = [];
    }

    /**
     * @param JSON string $data
     * @param string $scope - ex. Account
     * @param string $name  - detail
     *
     * @return bool
     */
    public function merge($data, $scope, $name)
    {
        $scope = $this->sanitizeInput($scope);
        $name = $this->sanitizeInput($name);

        $prevData = $this->get($scope, $name);

        $prevDataArray = Json::getArrayData($prevData);
        $dataArray = Json::getArrayData($data);

        $data = Util::merge($prevDataArray, $dataArray);
        $data = Json::encode($data);

        return $this->set($data, $scope, $name);
    }

    protected function getCustomPath(string $entityType): string
    {
        return Util::concatPath('custom/Espo/Custom/Resources/layouts', $entityType);
    }

    protected function compose(string $scope, string $name): array
    {
        // from custom data
        if ($this->isCustom($scope, $name)) {
            $customLayout = Json::decode($this->getFileManager()->getContents($this->concatPath($this->getCustomPath($scope), $name . '.json')), true);
            if (!empty($customLayout) && is_array($customLayout)) {
                return $customLayout;
            }
        }

        // prepare data
        $data = [];

        $filePath = $this->concatPath(CORE_PATH . '/Atro/Resources/layouts', $scope);
        $fileFullPath = $this->concatPath($filePath, $name . '.json');
        if (file_exists($fileFullPath)) {
            // get file data
            $fileData = $this->getFileManager()->getContents($fileFullPath);

            // prepare data
            $data = Json::decode($fileData, true);
        }

        // from modules data
        foreach ($this->getMetadata()->getModules() as $module) {
            $module->loadLayouts($scope, $name, $data);
        }

        // default by method
        if (empty($data)) {
            $type = $this->getMetadata()->get(['scopes', $scope, 'type']);
            $method = "getDefaultFor{$type}EntityType";
            if (method_exists($this, $method)) {
                $data = $this->$method($scope, $name);
            }
        }

        if (empty($data)) {
            // prepare file path
            $fileFullPath = $this->concatPath($this->concatPath(CORE_PATH . '/Espo/Core/defaults', 'layouts'), $name . '.json');

            if (file_exists($fileFullPath)) {
                // get file data
                $fileData = $this->getFileManager()->getContents($fileFullPath);

                // prepare data
                $data = Json::decode($fileData, true);
            }
        }

        if (in_array($name, ['detail', 'detailSmall'])) {
            $data = $this->injectMultiLanguageFields($data, $scope);
        }

        return $data;
    }

    protected function getDefaultForRelationEntityType(string $scope, string $name): array
    {
        $relationFields = [];
        foreach ($this->getMetadata()->get(['entityDefs', $scope, 'fields']) as $field => $fieldDefs) {
            if (!empty($fieldDefs['relationField'])) {
                $relationFields[] = $field;
            }
        }

        $data = [];

        switch ($name) {
            case 'list':
            case 'listSmall':
                $data = [
                    [
                        'name' => $relationFields[0]
                    ],
                    [
                        'name' => $relationFields[1]
                    ],
                ];
                break;
            case 'detail':
            case 'detailSmall':
                $data = [
                    [
                        "label" => "Overview",
                        "rows"  => [
                            [
                                [
                                    "name" => $relationFields[0]
                                ],
                                [
                                    "name" => $relationFields[1]
                                ]
                            ]
                        ]
                    ]
                ];
                break;
        }

        return $data;
    }

    protected function injectMultiLanguageFields(array $data, string $scope): array
    {
        if (empty($multiLangFields = $this->getMultiLangFields($scope))) {
            return $data;
        }

        $exists = [];
        foreach ($data as $k => $panel) {
            // skip if no rows
            if (empty($panel['rows'])) {
                continue 1;
            }
            foreach ($panel['rows'] as $row) {
                foreach ($row as $field) {
                    if (!empty($field['name'])) {
                        $exists[] = $field['name'];
                    }
                }
            }
        }

        $result = [];
        foreach ($data as $k => $panel) {
            $result[$k] = $panel;

            if (isset($panel['rows']) || !empty($panel['rows'])) {
                $rows = [];
                $skip = false;

                foreach ($panel['rows'] as $key => $row) {
                    if ($skip) {
                        $skip = false;
                        continue;
                    }

                    $newRow = [];
                    $fullWidthRow = count($row) == 1;

                    foreach ($row as $field) {
                        $newRow[] = $field;

                        if (is_array($field) && in_array($field['name'], $multiLangFields)) {
                            $localeFields = $this->getMultiLangLocalesFields($field['name']);

                            if (!empty($needToAdd = array_diff($localeFields, $exists))) {
                                $nextRow = $key != count($panel['rows']) - 1 ? $panel['rows'][$key + 1] : null;

                                if (!$fullWidthRow && !empty($nextRow)) {
                                    if (in_array(false, $nextRow)) {
                                        $item = null;
                                        foreach ($nextRow as $f) {
                                            if (is_array($f)) {
                                                $item = $f;
                                            }
                                        }

                                        if (in_array($item['name'], $localeFields)) {
                                            $newField = $field;
                                            $newField['name'] = array_shift($needToAdd);
                                            $newRow[] = $newField;
                                            $newRow[] = $item;

                                            $skip = true;
                                        }
                                    }
                                }

                                foreach ($needToAdd as $item) {
                                    $newField = $field;
                                    $newField['name'] = $item;
                                    $newRow[] = $newField;
                                }
                            }
                        }
                    }

                    if (!$fullWidthRow && count($newRow) % 2 != 0) {
                        if ($newRow[count($newRow) - 1] === false) {
                            array_pop($newRow);
                        } else {
                            $newRow[] = false;
                        }
                    }

                    $rows = array_merge($rows, array_chunk($newRow, $fullWidthRow ? 1 : 2));
                }

                $result[$k]['rows'] = $rows;
            }
        }

        return $result;
    }

    protected function getMultiLangFields(string $scope): array
    {
        $result = [];

        foreach ($this->getMetadata()->get(['entityDefs', $scope, 'fields'], []) as $field => $data) {
            if (!empty($data['isMultilang'])) {
                $result[] = $field;
            }
        }

        return $result;
    }

    protected function getPreparedLocalesCodes(): array
    {
        $result = [];

        foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
            $result[] = ucfirst(Util::toCamelCase(strtolower($locale)));
        }

        return $result;
    }

    protected function getMultiLangLocalesFields(string $fieldName): array
    {
        $result = [];

        foreach ($this->getPreparedLocalesCodes() as $locale) {
            $result[] = $fieldName . $locale;
        }

        return $result;
    }

    /**
     * Disable fields from layout if this fields not exist in metadata
     *
     * @param string $scope
     * @param string $name
     * @param array  $data
     *
     * @return array
     */
    protected function disableNotExistingFields($scope, $name, $data): array
    {
        // get entityDefs
        $entityDefs = $this->getMetadata()->get('entityDefs')[$scope] ?? [];

        // check if entityDefs exists
        if (!empty($entityDefs)) {
            // get fields for entity
            $fields = array_keys($entityDefs['fields']);
            $fields[] = 'id';

            // remove fields from layout if this fields not exist in metadata
            switch ($name) {
                case 'filters':
                case 'massUpdate':
                    $data = array_values(array_intersect($data, $fields));
                    break;
                case 'detail':
                case 'detailSmall':
                    for ($key = 0; $key < count($data[0]['rows']); $key++) {
                        foreach ($data[0]['rows'][$key] as $fieldKey => $fieldData) {
                            if (isset($fieldData['name']) && !in_array($fieldData['name'], $fields)) {
                                $data[0]['rows'][$key][$fieldKey] = false;
                            }
                        }
                    }
                    break;
                case 'list':
                case 'listSmall':
                    foreach ($data as $key => $row) {
                        if (isset($row['name']) && !in_array($row['name'], $fields)) {
                            array_splice($data, $key, 1);
                        }
                    }
                    break;
            }
        }

        return $data;
    }

    protected function sanitizeInput(string $name): string
    {
        return preg_replace("([\.]{2,})", '', $name);
    }

    protected function getContainer(): Container
    {
        return $this->getInjection('container');
    }

    protected function getFileManager(): File\Manager
    {
        return $this->getContainer()->get('fileManager');
    }

    protected function getMetadata(): Metadata
    {
        return $this->getContainer()->get('metadata');
    }

    protected function getConfig(): Config
    {
        return $this->getContainer()->get('config');
    }

    protected function getUser(): \Espo\Entities\User
    {
        return $this->getContainer()->get('user');
    }
}
