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

namespace Espo\Listeners;

use Espo\Core\EventManager\Event;
use Espo\Core\Utils\Util;

class Metadata extends AbstractListener
{
    public const VIRTUAL_FIELD_DELIMITER = \Espo\Core\Templates\Services\Relationship::VIRTUAL_FIELD_DELIMITER;

    public function modify(Event $event): void
    {
        $data = $event->getArgument('data');

        // add owner
        $data = $this->addOwner($data);

        // add onlyActive bool filter
        $data = $this->addOnlyActiveFilter($data);

        // add archive
        $data = $this->addArchive($data);
        // set thumbs sizes to options of asset field type
        $data = $this->setAssetThumbSize($data);

        // prepare multi-lang
        $data = $this->prepareMultiLang($data);

        $data = $this->setForeignName($data);

        $data = $this->showConnections($data);

        $data = $this->prepareHierarchyEntities($data);

        $this->prepareRelationshipsEntities($data);

        $this->prepareRanges($data);

        $this->prepareUnit($data);

        $this->prepareLanguageField($data);

        $this->prepareScriptField($data);

        $event->setArgument('data', $data);
    }

    protected function prepareLanguageField(array &$data): void
    {
        $languages = ['main'];
        if ($this->getConfig()->get('isMultilangActive')) {
            $languages = array_merge($languages, $this->getConfig()->get('inputLanguageList', []));
        }

        foreach ($data['entityDefs'] as $entityType => $entityDefs) {
            if (empty($entityDefs['fields'])) {
                continue 1;
            }
            foreach ($entityDefs['fields'] as $field => $fieldDefs) {
                if (!empty($fieldDefs['type']) && $fieldDefs['type'] === 'language') {
                    $data['entityDefs'][$entityType]['fields'][$field]['optionsIds'] = $languages;
                    $data['entityDefs'][$entityType]['fields'][$field]['options'] = $languages;
                }
            }
        }
    }

    protected function prepareScriptField(array &$data): void
    {
        foreach ($data['entityDefs'] as $entityType => $entityDefs) {
            if (empty($entityDefs['fields'])) {
                continue 1;
            }
            foreach ($entityDefs['fields'] as $field => $fieldDefs) {
                if (!empty($fieldDefs['type']) && $fieldDefs['type'] === 'script') {
                    $data['entityDefs'][$entityType]['fields'][$field]['notStorable'] = true;
                    $data['entityDefs'][$entityType]['fields'][$field]['readOnly'] = true;
                    $data['entityDefs'][$entityType]['fields'][$field]['importDisabled'] = true;
                    $data['entityDefs'][$entityType]['fields'][$field]['massUpdateDisabled'] = true;
                    $data['entityDefs'][$entityType]['fields'][$field]['filterDisabled'] = true;
                }
            }
        }
    }

    protected function prepareUnit(array &$data): void
    {
        foreach ($data['entityDefs'] as $entityType => $entityDefs) {
            if (empty($entityDefs['fields'])) {
                continue 1;
            }
            foreach ($entityDefs['fields'] as $field => $fieldDefs) {
                if (empty($fieldDefs['measureId'])) {
                    continue;
                }
                if (!empty($fieldDefs['relationVirtualField'])) {
                    continue;
                }
                if (empty($fieldDefs['type']) || !in_array($fieldDefs['type'], ['int', 'float', 'rangeInt', 'rangeFloat'])) {
                    continue;
                }

                if (in_array($fieldDefs['type'], ['rangeInt', 'rangeFloat'])) {
                    $data['entityDefs'][$entityType]['fields'][$field . 'From']['measureId'] = $fieldDefs['measureId'];
                    $data['entityDefs'][$entityType]['fields'][$field . 'To']['measureId'] = $fieldDefs['measureId'];
                }

                $data['entityDefs'][$entityType]['fields'][$field . 'Unit'] = [
                    "type"        => "link",
                    "view"        => "views/fields/unit-link",
                    "measureId"   => $fieldDefs['measureId'],
                    "unitIdField" => true,
                    "mainField"   => $field,
                    "required"    => !empty($fieldDefs['required']),
                    "audited"     => !empty($fieldDefs['audited']),
                    "emHidden"    => true
                ];

                $data['entityDefs'][$entityType]['links'][$field . 'Unit'] = [
                    "type"   => "belongsTo",
                    "entity" => "Unit"
                ];

                if (in_array($fieldDefs['type'], ['int', 'float'])) {
                    $data['entityDefs'][$entityType]['fields'][$field]['labelField'] = 'unit' . ucfirst($field);
                    $data['entityDefs'][$entityType]['fields']['unit' . ucfirst($field)] = [
                        "type"               => "varchar",
                        "notStorable"        => true,
                        "view"               => "views/fields/unit-{$fieldDefs['type']}",
                        "measureId"          => $fieldDefs['measureId'],
                        "mainField"          => $field,
                        "unitField"          => true,
                        "required"           => !empty($fieldDefs['required']),
                        "audited"            => false,
                        "filterDisabled"     => true,
                        "massUpdateDisabled" => true,
                        "emHidden"           => true
                    ];
                } else {
                    $data['entityDefs'][$entityType]['fields'][$field]['unitField'] = true;
                }

                foreach (in_array($fieldDefs['type'], ['int', 'float']) ? [$field] : [$field . 'From', $field . 'To'] as $v) {
                    $data['entityDefs'][$entityType]['fields'][$v . 'AllUnits'] = [
                        "type"                      => "jsonObject",
                        "notStorable"               => true,
                        "mainField"                 => $field,
                        "required"                  => false,
                        "audited"                   => false,
                        "layoutListDisabled"        => true,
                        "layoutListSmallDisabled"   => true,
                        "layoutDetailDisabled"      => true,
                        "layoutDetailSmallDisabled" => true,
                        "massUpdateDisabled"        => true,
                        "filterDisabled"            => true,
                        "exportDisabled"            => true,
                        "importDisabled"            => true,
                        "emHidden"                  => true
                    ];
                }
            }
        }
    }

    protected function prepareRanges(array &$data): void
    {
        foreach ($data['entityDefs'] as $entity => $entityDefs) {
            if (empty($entityDefs['fields'])) {
                continue 1;
            }
            foreach ($entityDefs['fields'] as $field => $fieldDefs) {
                if (empty($fieldDefs['type']) || !in_array($fieldDefs['type'], ['rangeInt', 'rangeFloat'])) {
                    continue;
                }

                if (!empty($fieldDefs['unique'])) {
                    $data['entityDefs'][$entity]['uniqueIndexes']['unique_' . $field] = [
                        'deleted',
                        Util::toUnderScore($field) . '_from',
                        Util::toUnderScore($field) . '_to'
                    ];
                }

                $data['entityDefs'][$entity]['fields'][$field]['filterDisabled'] = true;
                $data['entityDefs'][$entity]['fields'][$field]['notStorable'] = true;
                $data['entityDefs'][$entity]['fields'][$field]['exportDisabled'] = true;
                $data['entityDefs'][$entity]['fields'][$field]['importDisabled'] = true;

                $fieldFrom = $field . 'From';
                $fieldTo = $field . 'To';

                $data['entityDefs'][$entity]['fields'][$fieldFrom]['mainField'] = $field;
                $data['entityDefs'][$entity]['fields'][$fieldTo]['mainField'] = $field;
                $data['entityDefs'][$entity]['fields'][$fieldFrom]['required'] = !empty($fieldDefs['required']);
                $data['entityDefs'][$entity]['fields'][$fieldTo]['required'] = !empty($fieldDefs['required']);
                $data['entityDefs'][$entity]['fields'][$fieldFrom]['readOnly'] = !empty($fieldDefs['readOnly']);
                $data['entityDefs'][$entity]['fields'][$fieldTo]['readOnly'] = !empty($fieldDefs['readOnly']);
                if (isset($fieldDefs['defaultFrom'])) {
                    $data['entityDefs'][$entity]['fields'][$fieldFrom]['default'] = $fieldDefs['defaultFrom'];
                }
                if (isset($fieldDefs['defaultTo'])) {
                    $data['entityDefs'][$entity]['fields'][$fieldTo]['default'] = $fieldDefs['defaultTo'];
                }
                if (isset($fieldDefs['minFrom'])) {
                    $data['entityDefs'][$entity]['fields'][$fieldFrom]['min'] = $fieldDefs['minFrom'];
                }
                if (isset($fieldDefs['minTo'])) {
                    $data['entityDefs'][$entity]['fields'][$fieldTo]['min'] = $fieldDefs['minTo'];
                }
                if (isset($fieldDefs['maxFrom'])) {
                    $data['entityDefs'][$entity]['fields'][$fieldFrom]['max'] = $fieldDefs['maxFrom'];
                }
                if (isset($fieldDefs['maxTo'])) {
                    $data['entityDefs'][$entity]['fields'][$fieldTo]['max'] = $fieldDefs['maxTo'];
                }

                if (!empty($fieldDefs['audited'])) {
                    $data['entityDefs'][$entity]['fields'][$fieldFrom]['audited'] = true;
                    $data['entityDefs'][$entity]['fields'][$fieldTo]['audited'] = true;
                }

                if ($fieldDefs['type'] === 'rangeFloat' && isset($fieldDefs['amountOfDigitsAfterComma'])) {
                    $data['entityDefs'][$entity]['fields'][$fieldFrom]['amountOfDigitsAfterComma'] = $fieldDefs['amountOfDigitsAfterComma'];
                    $data['entityDefs'][$entity]['fields'][$fieldTo]['amountOfDigitsAfterComma'] = $fieldDefs['amountOfDigitsAfterComma'];
                }

                if (!empty($data['clientDefs'][$entity]['dynamicLogic']['fields'][$field])) {
                    $data['clientDefs'][$entity]['dynamicLogic']['fields'][$fieldFrom] = $data['clientDefs'][$entity]['dynamicLogic']['fields'][$field];
                    $data['clientDefs'][$entity]['dynamicLogic']['fields'][$fieldTo] = $data['clientDefs'][$entity]['dynamicLogic']['fields'][$field];
                }
            }
        }
    }

    protected function prepareRelationshipsEntities(array &$data): void
    {
        foreach ($data['entityDefs'] as $scope => $scopeData) {
            if (empty($scopeData['fields'])) {
                continue;
            }

            if (isset($scopeData['relationsVirtualFields']) && $scopeData['relationsVirtualFields'] === false) {
                continue;
            }

            if (!isset($data['scopes'][$scope]['type']) || $data['scopes'][$scope]['type'] !== 'Relationship') {
                continue;
            }

            foreach ($scopeData['fields'] as $field => $fieldDefs) {
                if (!empty($fieldDefs['notStorable']) || empty($fieldDefs['type']) || $fieldDefs['type'] !== 'link' || empty($fieldDefs['relationshipField'])) {
                    continue;
                }

                if (empty($data['entityDefs'][$scope]['links'][$field]['entity'])) {
                    continue;
                }

                $foreignEntity = $data['entityDefs'][$scope]['links'][$field]['entity'];

                if (empty($data['entityDefs'][$foreignEntity]['fields'])) {
                    continue;
                }

                foreach ($data['entityDefs'][$foreignEntity]['fields'] as $foreignField => $foreignFieldDefs) {
                    if (empty($foreignFieldDefs['type'])) {
                        continue;
                    }

                    if (!in_array($foreignFieldDefs['type'], ['attachmentMultiple', 'linkMultiple'])) {
                        $data['entityDefs'][$scope]['fields'][$field . self::VIRTUAL_FIELD_DELIMITER . $foreignField] = array_merge($foreignFieldDefs, [
                            "notStorable"          => true,
                            "relationVirtualField" => true,
                            "entity"               => $foreignEntity,
                            "required"             => false,
                            "unique"               => false,
                            "filterDisabled"       => true,
                            "massUpdateDisabled"   => true,
                            "exportDisabled"       => true,
                            "importDisabled"       => true,
                            "emHidden"             => true
                        ]);

                        if ($foreignFieldDefs['type'] === 'link') {
                            if (!empty($data['entityDefs'][$foreignEntity]['links'][$foreignField]['entity'])) {
                                $linkEntity = $data['entityDefs'][$foreignEntity]['links'][$foreignField]['entity'];
                                $data['entityDefs'][$scope]['fields'][$field . self::VIRTUAL_FIELD_DELIMITER . $foreignField]['entity'] = $linkEntity;
                            }
                        }
                    }
                }
            }
        }
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
                "massUpdateDisabled"        => true,
                "filterDisabled"            => true,
                "importDisabled"            => true,
                "exportDisabled"            => true,
                "emHidden"                  => true
            ];

            $data['entityDefs'][$scope]['fields']['hasChildren'] = [
                "type"                      => "bool",
                "notStorable"               => true,
                "layoutListDisabled"        => true,
                "layoutListSmallDisabled"   => true,
                "layoutDetailDisabled"      => true,
                "layoutDetailSmallDisabled" => true,
                "massUpdateDisabled"        => true,
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
                "massUpdateDisabled"        => true,
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
                "massUpdateDisabled"        => true,
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
                    $newFields[$field]['lingualFields'] = [];
                    foreach ($locales as $locale) {
                        // prepare locale
                        $preparedLocale = ucfirst(Util::toCamelCase(strtolower($locale)));

                        // prepare multi-lang field
                        $mField = $field . $preparedLocale;

                        $newFields[$field]['lingualFields'][$mField] = $mField;

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
                                $index = array_key_exists('default', $params) ? array_search($params['default'], $params['options']) : false;
                                $mParams['default'] = $index !== false ? $mParams['options'][$index] : null;
                            } else {
                                $mParams['default'] = null;
                            }
                            $mParams['required'] = false;
                            $mParams['emHidden'] = true;
                        }

                        if (isset($data['entityDefs'][$scope]['fields'][$mField])) {
                            $mParams = array_merge($mParams, $data['entityDefs'][$scope]['fields'][$mField]);
                            $toSkip[] = $mField;
                        }

                        $newFields[$mField] = $mParams;
                    }
                    $newFields[$field]['lingualFields'] = array_values($newFields[$field]['lingualFields']);
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


    public function addArchive(array $data)
    {
        foreach ($data['scopes'] as $scope => $row) {
            if (!empty($row['hasArchive'])) {
                $data['entityDefs'][$scope]['fields']['isArchived']['type'] = 'bool';
                $data['entityDefs'][$scope]['fields']['isArchived']['notNull'] = true;
                $data['clientDefs'][$entity]['filterList'][] = 'withArchived';
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
