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

namespace Espo\Listeners;

use Espo\Core\EventManager\Event;
use Espo\Core\Utils\Util;

/**
 * Class Metadata
 */
class Metadata extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function modify(Event $event)
    {
        // get data
        $data = $event->getArgument('data');

        // add owner
        $data = $this->addOwner($data);

        // add onlyActive bool filter
        $data = $this->addOnlyActiveFilter($data);

        // set thumbs sizes to options of asset field type
        $data = $this->setAssetThumbSize($data);

        // prepare multi-lang
        $data = $this->prepareMultiLang($data);

        $data = $this->setForeignName($data);

        $data = $this->showConnections($data);

        $data = $this->prepareHierarchyEntities($data);

        // set data
        $event->setArgument('data', $data);
    }

    protected function prepareHierarchyEntities(array $data): array
    {
        foreach ($data['entityDefs'] as $scope => $scopeData) {
            if (empty($scopeData['fields'])) {
                continue;
            }

            if (!isset($data['scopes'][$scope]['type']) || $data['scopes'][$scope]['type'] !== 'Hierarchy') {
                continue;
            }

            if (!isset($data['entityDefs'][$scope]['fields']['parents']['view'])) {
                $data['entityDefs'][$scope]['fields']['parents']['view'] = 'views/fields/hierarchy-parents';
            }
            $data['entityDefs'][$scope]['fields']['parents']['layoutDetailDisabled'] = false;

            $data['entityDefs'][$scope]['fields']['isRoot'] = [
                "type"                      => "bool",
                "notStorable"               => true,
                "layoutListDisabled"        => true,
                "layoutListSmallDisabled"   => true,
                "layoutDetailDisabled"      => true,
                "layoutDetailSmallDisabled" => true,
                "massUpdateDisabled"  => true,
                "filterDisabled"            => true,
                "importDisabled"            => true,
                "exportDisabled"            => true,
                "emHidden"                  => true
            ];

            $data['entityDefs'][$scope]['fields']['hierarchyRoute'] = [
                "type"                      => "jsonObject",
                "notStorable"               => true,
                "layoutListDisabled"        => true,
                "layoutListSmallDisabled"   => true,
                "layoutDetailDisabled"      => true,
                "layoutDetailSmallDisabled" => true,
                "massUpdateDisabled"  => true,
                "filterDisabled"            => true,
                "importDisabled"            => true,
                "exportDisabled"            => true,
                "emHidden"                  => true
            ];

            $data['entityDefs'][$scope]['fields']['inheritedFields'] = [
                "type"                      => "array",
                "notStorable"               => true,
                "layoutListDisabled"        => true,
                "layoutListSmallDisabled"   => true,
                "layoutDetailDisabled"      => true,
                "layoutDetailSmallDisabled" => true,
                "massUpdateDisabled"  => true,
                "filterDisabled"            => true,
                "importDisabled"            => true,
                "exportDisabled"            => true,
                "emHidden"                  => true
            ];

            foreach ($scopeData['fields'] as $fieldName => $fieldData) {
                if (empty($fieldData['type'])) {
                    continue 1;
                }

                if (in_array($fieldData['type'], ['currencyConverted', 'autoincrement'])) {
                    if (!isset($data['scopes'][$scope]['mandatoryUnInheritedFields'])) {
                        $data['scopes'][$scope]['mandatoryUnInheritedFields'] = [];
                    }
                    $data['scopes'][$scope]['mandatoryUnInheritedFields'][] = $fieldName;
                }
            }
        }

        return $data;
    }

    protected function showConnections(array $data): array
    {
        if (
            !empty($data['scopes']['Connection']['showInAdminPanel'])
            && !empty($data['app']['adminPanel']['system']['itemList'])
            && is_array($data['app']['adminPanel']['system']['itemList'])
        ) {
            $new = [];
            foreach ($data['app']['adminPanel']['system']['itemList'] as $v) {
                $new[] = $v;
                if ($v['label'] == 'Authentication') {
                    $new[] = [
                        "url"         => "#Connection",
                        "label"       => "Connection",
                        "description" => "connection"
                    ];
                }
            }
            $data['app']['adminPanel']['system']['itemList'] = $new;
        }

        return $data;
    }

    protected function setForeignName(array $data): array
    {
        foreach ($data['entityDefs'] as $scope => $scopeData) {
            if (empty($scopeData['fields'])) {
                continue;
            }

            foreach ($scopeData['fields'] as $fieldName => $fieldData) {
                if (!empty($fieldData['foreignName'])) {
                    $data['entityDefs'][$scope]['links'][$fieldName]['foreignName'] = $fieldData['foreignName'];
                }
            }
        }

        return $data;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function prepareMultiLang(array $data): array
    {
        // is multi-lang activated
        if (empty($this->getConfig()->get('isMultilangActive'))) {
            return $data;
        }

        // get locales
        if (empty($locales = $this->getConfig()->get('inputLanguageList', []))) {
            return $data;
        }

        $defaultParams = [];
        foreach ($locales as $locale) {
            $defaultParams[] = ['name' => 'label' . ucfirst(Util::toCamelCase(strtolower($locale))), 'type' => 'varchar'];
        }

        foreach ($data['fields'] as $field => $v) {
            $params = $defaultParams;
            if (!empty($v['multilingual'])) {
                $params[] = ['name' => 'isMultilang', 'type' => 'bool', 'tooltip' => true];
            }

            if (!empty($data['fields'][$field]['params']) && is_array($data['fields'][$field]['params'])) {
                $data['fields'][$field]['params'] = array_merge($params, $data['fields'][$field]['params']);
            }
        }

        /**
         * Set multi-lang fields to entity defs
         */
        foreach ($data['entityDefs'] as $scope => $rows) {
            if (!isset($rows['fields']) || !is_array($rows['fields'])) {
                continue 1;
            }
            $toSkip = [];
            $newFields = [];
            foreach ($rows['fields'] as $field => $params) {
                if (in_array($field, $toSkip)) {
                    continue 1;
                }
                if (empty($params['type'])) {
                    continue 1;
                }

                $newFields[$field] = $params;
                if (!empty($data['fields'][$params['type']]['multilingual']) && !empty($params['isMultilang'])) {
                    foreach ($locales as $locale) {
                        // prepare locale
                        $preparedLocale = ucfirst(Util::toCamelCase(strtolower($locale)));

                        // prepare multi-lang field
                        $mField = $field . $preparedLocale;

                        // prepare params
                        $mParams = $params;
                        $mParams['isMultilang'] = false;
                        $mParams['hideParams'] = ['isMultilang'];
                        $mParams['multilangField'] = $field;
                        $mParams['multilangLocale'] = $locale;
                        $mParams['isCustom'] = false;
                        if (isset($params['requiredForMultilang'])) {
                            $mParams['required'] = $params['requiredForMultilang'];
                        }
                        if (in_array($mParams['type'], ['enum', 'multiEnum'])) {
                            $mParams['notStorable'] = true;
                            $mParams['optionsOriginal'] = $params['options'];
                            if (!empty($mParams['options' . $preparedLocale])) {
                                $mParams['options'] = $mParams['options' . $preparedLocale];
                            }
                            if ($mParams['type'] == 'enum' && !empty($params['options'])) {
                                $index = array_search($params['default'], $params['options']);
                                $mParams['default'] = $index !== false ? $mParams['options'][$index] : null;
                            } else {
                                $mParams['default'] = null;
                            }
                            $mParams['required'] = false;
                            $mParams['hideParams'] = array_merge(
                                $mParams['hideParams'], ['options', 'default', 'required', 'isSorted', 'audited', 'readOnly', 'prohibitedEmptyValue']
                            );
                            $mParams['massUpdateDisabled'] = true;
                        }

                        if (isset($data['entityDefs'][$scope]['fields'][$mField])) {
                            $mParams = array_merge($mParams, $data['entityDefs'][$scope]['fields'][$mField]);
                            $toSkip[] = $mField;
                        }

                        $newFields[$mField] = $mParams;
                    }
                }
            }
            $data['entityDefs'][$scope]['fields'] = $newFields;
        }

        return $data;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function setAssetThumbSize(array $data): array
    {
        foreach ($data['fields']['asset']['params'] as $k => $row) {
            if ($row['name'] === 'previewSize') {
                $data['fields']['asset']['params'][$k]['options'] = empty($data['app']['imageSizes']) ? [] : array_keys($data['app']['imageSizes']);
                break;
            }
        }

        return $data;
    }

    /**
     * Add owner, assigned user, team if it needs
     *
     * @param array $data
     *
     * @return array
     */
    protected function addOwner(array $data): array
    {
        foreach ($data['scopes'] as $scope => $row) {
            // for owner user
            if (!empty($row['hasOwner'])) {
                if (!isset($data['entityDefs'][$scope]['fields']['ownerUser']['type'])) {
                    $data['entityDefs'][$scope]['fields']['ownerUser']['type'] = 'link';
                }

                if (!isset($data['entityDefs'][$scope]['fields']['ownerUser']['required'])) {
                    $data['entityDefs'][$scope]['fields']['ownerUser']['required'] = true;
                }

                if (!isset($data['entityDefs'][$scope]['fields']['ownerUser']['view'])) {
                    $data['entityDefs'][$scope]['fields']['ownerUser']['view'] = 'views/fields/owner-user';
                }

                if (!isset($data['entityDefs'][$scope]['links']['ownerUser']['type'])) {
                    $data['entityDefs'][$scope]['links']['ownerUser']['type'] = 'belongsTo';
                }

                if (!isset($data['entityDefs'][$scope]['links']['ownerUser']['entity'])) {
                    $data['entityDefs'][$scope]['links']['ownerUser']['entity'] = 'User';
                }

                if (!isset($data['entityDefs'][$scope]['indexes']['ownerUser']['columns'])) {
                    $data['entityDefs'][$scope]['indexes']['ownerUser']['columns'] = ["ownerUserId", "deleted"];
                }
            }

            // for assigned user
            if (!empty($row['hasAssignedUser'])) {
                if (!isset($data['entityDefs'][$scope]['fields']['assignedUser']['type'])) {
                    $data['entityDefs'][$scope]['fields']['assignedUser']['type'] = 'link';
                }

                if (!isset($data['entityDefs'][$scope]['fields']['assignedUser']['required'])) {
                    $data['entityDefs'][$scope]['fields']['assignedUser']['required'] = true;
                }

                if (!isset($data['entityDefs'][$scope]['fields']['assignedUser']['view'])) {
                    $data['entityDefs'][$scope]['fields']['assignedUser']['view'] = 'views/fields/owner-user';
                }

                if (!isset($data['entityDefs'][$scope]['links']['assignedUser']['type'])) {
                    $data['entityDefs'][$scope]['links']['assignedUser']['type'] = 'belongsTo';
                }

                if (!isset($data['entityDefs'][$scope]['links']['assignedUser']['entity'])) {
                    $data['entityDefs'][$scope]['links']['assignedUser']['entity'] = 'User';
                }

                if (!isset($data['entityDefs'][$scope]['indexes']['assignedUser']['columns'])) {
                    $data['entityDefs'][$scope]['indexes']['assignedUser']['columns'] = ["assignedUserId", "deleted"];
                }
            }

            // for teams
            if (!empty($row['hasTeam'])) {
                if (!isset($data['entityDefs'][$scope]['fields']['teams']['type'])) {
                    $data['entityDefs'][$scope]['fields']['teams']['type'] = 'linkMultiple';
                }

                if (!isset($data['entityDefs'][$scope]['fields']['teams']['view'])) {
                    $data['entityDefs'][$scope]['fields']['teams']['view'] = 'views/fields/teams';
                }

                if (!isset($data['entityDefs'][$scope]['links']['teams']['type'])) {
                    $data['entityDefs'][$scope]['links']['teams']['type'] = 'hasMany';
                }

                if (!isset($data['entityDefs'][$scope]['links']['teams']['entity'])) {
                    $data['entityDefs'][$scope]['links']['teams']['entity'] = 'Team';
                }

                if (!isset($data['entityDefs'][$scope]['links']['teams']['relationName'])) {
                    $data['entityDefs'][$scope]['links']['teams']['relationName'] = 'EntityTeam';
                }

                if (!isset($data['entityDefs'][$scope]['links']['teams']['layoutRelationshipsDisabled'])) {
                    $data['entityDefs'][$scope]['links']['teams']['layoutRelationshipsDisabled'] = true;
                }
            }

            // for accounts
            if (!empty($row['hasAccount'])) {
                $field = 'assignedAccounts';
                $foreign = "assigned{$scope}s";
                $relationName = lcfirst($scope) . 'AssignedAccount';

                if (!isset($data['entityDefs'][$scope]['fields'][$field]['type'])) {
                    $data['entityDefs'][$scope]['fields'][$field]['type'] = 'linkMultiple';
                    $data['entityDefs'][$scope]['fields'][$field]['layoutDetailDisabled'] = true;
                    $data['entityDefs'][$scope]['fields'][$field]['layoutDetailSmallDisabled'] = true;
                    $data['entityDefs']['Account']['fields'][$foreign]['type'] = 'linkMultiple';
                    $data['entityDefs']['Account']['fields'][$foreign]['layoutDetailDisabled'] = true;
                    $data['entityDefs']['Account']['fields'][$foreign]['layoutDetailSmallDisabled'] = true;
                }

                if (!isset($data['entityDefs'][$scope]['links'][$field]['type'])) {
                    $data['entityDefs'][$scope]['links'][$field]['type'] = 'hasMany';
                    $data['entityDefs']['Account']['links'][$foreign]['type'] = 'hasMany';
                }

                if (!isset($data['entityDefs'][$scope]['links'][$field]['entity'])) {
                    $data['entityDefs'][$scope]['links'][$field]['entity'] = 'Account';
                    $data['entityDefs']['Account']['links'][$foreign]['entity'] = $scope;
                }

                if (!isset($data['entityDefs'][$scope]['links'][$field]['relationName'])) {
                    $data['entityDefs'][$scope]['links'][$field]['relationName'] = $relationName;
                    $data['entityDefs']['Account']['links'][$foreign]['relationName'] = $relationName;
                }

                if (!isset($data['entityDefs'][$scope]['links'][$field]['foreign'])) {
                    $data['entityDefs'][$scope]['links'][$field]['foreign'] = $foreign;
                    $data['entityDefs']['Account']['links'][$foreign]['foreign'] = $field;
                }

                if (!isset($data['entityDefs'][$scope]['links'][$field]['layoutRelationshipsDisabled'])) {
                    $data['entityDefs'][$scope]['links'][$field]['layoutRelationshipsDisabled'] = true;
                    $data['entityDefs']['Account']['links'][$foreign]['layoutRelationshipsDisabled'] = true;
                }
            }
        }

        return $data;
    }

    /**
     * Remove field from index
     *
     * @param array  $indexes
     * @param string $fieldName
     *
     * @return array
     */
    protected function removeFieldFromIndex(array $indexes, string $fieldName): array
    {
        foreach ($indexes as $indexName => $fields) {
            // search field in index
            $key = array_search($fieldName, $fields['columns']);
            // remove field if exists
            if ($key !== false) {
                unset($indexes[$indexName]['columns'][$key]);
            }
        }

        return $indexes;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function addOnlyActiveFilter(array $data): array
    {
        foreach ($data['entityDefs'] as $entity => $row) {
            if (isset($row['fields']['isActive']['type']) && $row['fields']['isActive']['type'] == 'bool') {
                // push
                $data['clientDefs'][$entity]['boolFilterList'][] = 'onlyActive';
            }
        }

        return $data;
    }
}
