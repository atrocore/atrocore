<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Listeners;

use Atro\Core\EventManager\Event;
use Espo\Core\Utils\Util;
use Espo\Core\Templates\Services\Relationship;

class Metadata extends AbstractListener
{
    public function modify(Event $event): void
    {
        $data = $event->getArgument('data');

        // add owner
        $data = $this->addOwner($data);

        // add onlyActive bool filter
        $data = $this->addOnlyActiveFilter($data);

        $data = $this->addOnlyDeletedFilter($data);
        // add archive
        $data = $this->addArchive($data);
        // set thumbs sizes to options of asset field type
        $data = $this->setAssetThumbSize($data);

        // prepare multi-lang
        $data = $this->prepareMultiLang($data);

        $data = $this->setForeignName($data);

        $data = $this->showConnections($data);

        $data = $this->prepareHierarchyEntities($data);

        $this->prepareRanges($data);

        $this->prepareUnit($data);

        $this->prepareLanguageField($data);

        $this->setTranslationRequiredLanguage($data);

        $this->prepareScriptField($data);

        $event->setArgument('data', $data);
    }

    public function afterInit(Event $event): void
    {
        $data = $event->getArgument('data');

        $this->prepareRelationshipsEntities($data);

        $event->setArgument('data', $data);
    }

    public function setTranslationRequiredLanguage(array &$data)
    {
        $language = Util::toCamelCase(strtolower($this->getConfig()->get('mainLanguage', 'en_Us')));
        if (is_array($data['entityDefs']['Translation']['fields'][$language])) {
            $data['entityDefs']['Translation']['fields'][$language]['required'] = true;
        }
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

                    switch ($fieldDefs['outputType']) {
                        case 'int':
                            $data['entityDefs'][$entityType]['fields'][$field]['view'] = "views/fields/int";
                            break;
                        case 'float':
                            $data['entityDefs'][$entityType]['fields'][$field]['view'] = "views/fields/float";
                            break;
                        case 'bool':
                            $data['entityDefs'][$entityType]['fields'][$field]['view'] = "views/fields/bool";
                            break;
                        case 'date':
                            $data['entityDefs'][$entityType]['fields'][$field]['view'] = "views/fields/date";
                            break;
                        case 'datetime':
                            $data['entityDefs'][$entityType]['fields'][$field]['view'] = "views/fields/datetime";
                            break;
                        default:
                            $data['entityDefs'][$entityType]['fields'][$field]['view'] = "views/fields/text";
                            $data['entityDefs'][$entityType]['fields'][$field]['useDisabledTextareaInViewMode'] = true;
                    }
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
                if (empty($fieldDefs['type']) || !in_array($fieldDefs['type'], ['int', 'float', 'rangeInt', 'rangeFloat', 'varchar'])) {
                    continue;
                }

                if (in_array($fieldDefs['type'], ['rangeInt', 'rangeFloat'])) {
                    $data['entityDefs'][$entityType]['fields'][$field . 'From']['measureId'] = $fieldDefs['measureId'];
                    $data['entityDefs'][$entityType]['fields'][$field . 'To']['measureId'] = $fieldDefs['measureId'];
                }
                $unitFieldName = $field . 'Unit';
                $data['entityDefs'][$entityType]['fields'][$unitFieldName] = [
                    "type"        => "link",
                    "view"        => "views/fields/unit-link",
                    "measureId"   => $fieldDefs['measureId'],
                    "unitIdField" => true,
                    "mainField"   => $field,
                    "required"    => !empty($fieldDefs['required']),
                    "audited"     => !empty($fieldDefs['audited']),
                    "emHidden"    => true
                ];

                if (isset($fieldDefs['multilangLocale'])) {
                    $data['entityDefs'][$entityType]['fields'][$unitFieldName]['multilangLocale'] = $fieldDefs['multilangLocale'];
                }

                $data['entityDefs'][$entityType]['links'][$unitFieldName] = [
                    "type"   => "belongsTo",
                    "entity" => "Unit"
                ];

                if ($visibleLogic = $this->getMetadata()->get(['clientDefs', $entityType, 'dynamicLogic', 'fields', $field, 'visible'])) {
                    $data['clientDefs'][$entityType]['dynamicLogic']['fields'][$unitFieldName]['visible'] = $visibleLogic;
                }

                if (($readOnly = $this->getMetadata()->get(['clientDefs', $entityType, 'dynamicLogic', 'fields', $field, 'readOnly']))) {
                    $data['clientDefs'][$entityType]['dynamicLogic']['fields'][$unitFieldName]['readOnly'] = $readOnly;
                }

                if ($requireLogic = $this->getMetadata()->get(['clientDefs', $entityType, 'dynamicLogic', 'fields', $field, 'required'])) {
                    $data['clientDefs'][$entityType]['dynamicLogic']['fields'][$unitFieldName]['required'] = $requireLogic;
                }

                if (in_array($fieldDefs['type'], ['int', 'float', 'varchar'])) {
                    $virtualFieldName = 'unit' . ucfirst($field);
                    $data['entityDefs'][$entityType]['fields'][$field]['labelField'] = $virtualFieldName;
                    $data['entityDefs'][$entityType]['fields'][$virtualFieldName] = [
                        "type"               => "varchar",
                        "notStorable"        => true,
                        "view"               => "views/fields/unit-{$fieldDefs['type']}",
                        "measureId"          => $fieldDefs['measureId'],
                        "mainField"          => $field,
                        "unitField"          => true,
                        "required"           => false,
                        "audited"            => false,
                        "filterDisabled"     => true,
                        "massUpdateDisabled" => true,
                        "emHidden"           => true
                    ];

                    if (isset($fieldDefs['multilangLocale']) && isset($data['entityDefs'][$entityType]['fields'][$field]['labelField'])) {
                        $data['entityDefs'][$entityType]['fields'][$field]['labelField']['multilangLocale'] = $fieldDefs['multilangLocale'];
                    }

                    if ($visibleLogic = $this->getMetadata()->get(['clientDefs', $entityType, 'dynamicLogic', 'fields', $field, 'visible'])) {
                        $data['clientDefs'][$entityType]['dynamicLogic']['fields'][$virtualFieldName]['visible'] = $visibleLogic;
                    }

                    if (($readOnly = $this->getMetadata()->get(['clientDefs', $entityType, 'dynamicLogic', 'fields', $field, 'readOnly']))) {
                        $data['clientDefs'][$entityType]['dynamicLogic']['fields'][$virtualFieldName]['readOnly'] = $readOnly;
                    }

                    if ($requireLogic = $this->getMetadata()->get(['clientDefs', $entityType, 'dynamicLogic', 'fields', $field, 'required'])) {
                        $data['clientDefs'][$entityType]['dynamicLogic']['fields'][$virtualFieldName]['required'] = $requireLogic;
                    }
                } else {
                    $data['entityDefs'][$entityType]['fields'][$field]['unitField'] = true;
                }

                foreach (in_array($fieldDefs['type'], ['int', 'float', 'varchar']) ? [$field] : [$field . 'From', $field . 'To'] as $v) {
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

                    if (isset($fieldDefs['multilangLocale'])) {
                        $data['entityDefs'][$entityType]['fields'][$v . 'AllUnits']['multilangLocale'] = $fieldDefs['multilangLocale'];
                    }
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

                if (!empty($fieldDefs['index'])) {
                    $data['entityDefs'][$entity]['indexes'][$field] = [
                        "columns" => [
                            'deleted',
                            Util::toUnderScore($field) . '_from',
                            Util::toUnderScore($field) . '_to'
                        ]
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

                if (!empty($fieldDefs['index'])) {
                    $data['entityDefs'][$entity]['fields'][$fieldFrom]['index'] = true;
                    $data['entityDefs'][$entity]['fields'][$fieldTo]['index'] = true;
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
        foreach ($data['scopes'] as $scope => $scopeData) {
            if (empty($scopeData['type']) || $scopeData['type'] !== 'Relationship' || empty($data['entityDefs'][$scope]['fields'])) {
                continue;
            }
            $linkedFields = array_filter($data['entityDefs'][$scope]['fields'], function ($data) {
                return $data['type'] === 'link';
            });
            if (count($linkedFields) < 2) {
                continue;
            }
            $linkRelationshipFields = [];
            foreach ($data['entityDefs'][$scope]['fields'] as $field => $fieldDefs) {
                if (!empty($fieldDefs['relationshipField'])) {
                    if (!isset($data['entityDefs'][$scope]['fields'][$field]['required'])) {
                        $data['entityDefs'][$scope]['fields'][$field]['required'] = true;
                    }
                    if ($fieldDefs['type'] === 'link') {
                        $linkRelationshipFields[] = $field;

                        if (
                            !isset($data['entityDefs'][$scope]['uniqueIndexes']['unique_relationship'])
                            || !in_array(Util::toUnderScore($field) . '_id', $data['entityDefs'][$scope]['uniqueIndexes']['unique_relationship'])
                        ) {
                            $data['entityDefs'][$scope]['uniqueIndexes']['unique_relationship'][] = Util::toUnderScore($field) . '_id';
                        }

                        $foreignEntity = $data['entityDefs'][$scope]['links'][$field]['entity'];
                        $foreignField = $data['entityDefs'][$scope]['links'][$field]['foreign'];
                        $data['entityDefs'][$foreignEntity]['fields'][$foreignField]['massUpdateDisabled'] = true;
                        $data['entityDefs'][$foreignEntity]['fields'][$foreignField]['importDisabled'] = true;
                        if (!isset($data['clientDefs'][$foreignEntity]['relationshipPanels'][$foreignField]['view'])) {
                            $data['clientDefs'][$foreignEntity]['relationshipPanels'][$foreignField]['view'] = "treo-core:views/record/panels/for-relationship-type";
                        }
                    } else {
                        if (
                            !isset($data['entityDefs'][$scope]['uniqueIndexes']['unique_relationship'])
                            || !in_array(Util::toUnderScore($field), $data['entityDefs'][$scope]['uniqueIndexes']['unique_relationship'])
                        ) {
                            $data['entityDefs'][$scope]['uniqueIndexes']['unique_relationship'][] = Util::toUnderScore($field);
                        }
                    }
                }
            }

            // add deleted field to unique index if it needs
            if (
                !empty($data['entityDefs'][$scope]['uniqueIndexes']['unique_relationship'])
                && !in_array('deleted', $data['entityDefs'][$scope]['uniqueIndexes']['unique_relationship'])
            ) {
                array_unshift($data['entityDefs'][$scope]['uniqueIndexes']['unique_relationship'], 'deleted');
            }

            if (count($linkRelationshipFields) === 2) {
                $foreignEntity1 = $data['entityDefs'][$scope]['links'][$linkRelationshipFields[0]]['entity'];
                $foreignField1 = $data['entityDefs'][$scope]['links'][$linkRelationshipFields[0]]['foreign'];
                $entityType1 = $data['entityDefs'][$scope]['links'][$linkRelationshipFields[1]]['entity'];

                if (!isset($data['entityDefs'][$foreignEntity1]['links'][$foreignField1]['addRelationCustomDefs']['link'])) {
                    $data['entityDefs'][$foreignEntity1]['links'][$foreignField1]['addRelationCustomDefs']['link'] = lcfirst($entityType1) . 's';
                }

                $data['entityDefs'][$foreignEntity1]['links'][$foreignField1]['addRelationCustomDefs']['entity'] = $entityType1;

                $foreignEntity2 = $data['entityDefs'][$scope]['links'][$linkRelationshipFields[1]]['entity'];
                $foreignField2 = $data['entityDefs'][$scope]['links'][$linkRelationshipFields[1]]['foreign'];
                $entityType2 = $data['entityDefs'][$scope]['links'][$linkRelationshipFields[0]]['entity'];

                if (!isset($data['entityDefs'][$foreignEntity2]['links'][$foreignField2]['addRelationCustomDefs']['link'])) {
                    $data['entityDefs'][$foreignEntity2]['links'][$foreignField2]['addRelationCustomDefs']['link'] = lcfirst($entityType2) . 's';
                }

                $data['entityDefs'][$foreignEntity2]['links'][$foreignField2]['addRelationCustomDefs']['entity'] = $entityType2;

                $data['entityDefs'][$foreignEntity1]['fields'][$foreignField1 . '_' . $linkRelationshipFields[1]] = [
                    'type'                           => 'linkMultiple',
                    'entity'                         => $foreignEntity2,
                    'relationshipFilterField'        => $foreignField1,
                    'relationshipFilterForeignField' => $linkRelationshipFields[1],
                    'notStorable'                    => true,
                    'filterDisabled'                 => false,
                    'layoutListDisabled'             => true,
                    'layoutListSmallDisabled'        => true,
                    'layoutDetailDisabled'           => true,
                    'layoutDetailSmallDisabled'      => true,
                    'massUpdateDisabled'             => true,
                    'exportDisabled'                 => false,
                    'importDisabled'                 => true,
                    'emHidden'                       => true,
                ];

                $data['entityDefs'][$foreignEntity1]['links'][$foreignField1 . '_' . $linkRelationshipFields[1]] = [
                    'type'                        => 'hasMany',
                    'notStorable'                 => true,
                    'entity'                      => $foreignEntity2,
                    'layoutRelationshipsDisabled' => true
                ];

                $data['entityDefs'][$foreignEntity2]['fields'][$foreignField2 . '_' . $linkRelationshipFields[0]] = [
                    'type'                           => 'linkMultiple',
                    'entity'                         => $foreignEntity1,
                    'relationshipFilterField'        => $foreignField2,
                    'relationshipFilterForeignField' => $linkRelationshipFields[0],
                    'notStorable'                    => true,
                    'filterDisabled'                 => false,
                    'layoutListDisabled'             => true,
                    'layoutListSmallDisabled'        => true,
                    'layoutDetailDisabled'           => true,
                    'layoutDetailSmallDisabled'      => true,
                    'massUpdateDisabled'             => true,
                    'exportDisabled'                 => false,
                    'importDisabled'                 => true,
                    'emHidden'                       => true,
                ];

                $data['entityDefs'][$foreignEntity2]['links'][$foreignField2 . '_' . $linkRelationshipFields[0]] = [
                    'type'                        => 'hasMany',
                    'notStorable'                 => true,
                    'entity'                      => $foreignEntity1,
                    'layoutRelationshipsDisabled' => true
                ];
            }

            $data['entityDefs'][$scope]['fields']['isInherited'] = [
                "type"               => "bool",
                "notStorable"        => true,
                "massUpdateDisabled" => true,
                "filterDisabled"     => true,
                "importDisabled"     => true,
                "emHidden"           => true
            ];
        }

        /**
         * Create virtual fields in relationship entities
         */
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

                    if (in_array($foreignFieldDefs['type'], ['attachmentMultiple', 'linkMultiple'])) {
                        continue;
                    }

                    if (!empty($foreignFieldDefs['translationMetadataField'])) {
                        continue;
                    }

                    $data['entityDefs'][$scope]['fields'][$field . Relationship::VIRTUAL_FIELD_DELIMITER . $foreignField] = array_merge($foreignFieldDefs, [
                        "notStorable"          => true,
                        "relationVirtualField" => true,
                        "entity"               => $foreignEntity,
                        "required"             => false,
                        "unique"               => false,
                        "index"                => false,
                        "filterDisabled"       => true,
                        "massUpdateDisabled"   => true,
                        "exportDisabled"       => true,
                        "importDisabled"       => true,
                        "emHidden"             => true
                    ]);

                    if ($foreignFieldDefs['type'] === 'link') {
                        if (!empty($data['entityDefs'][$foreignEntity]['links'][$foreignField]['entity'])) {
                            $linkEntity = $data['entityDefs'][$foreignEntity]['links'][$foreignField]['entity'];
                            $data['entityDefs'][$scope]['fields'][$field . Relationship::VIRTUAL_FIELD_DELIMITER . $foreignField]['entity'] = $linkEntity;
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
                        if ($mParams['type'] == 'script') {
                            $mParams['script'] = $mParams['script' . $preparedLocale];
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
                $data['clientDefs'][$scope]['boolFilterList'][] = 'onlyArchived';
                $data['clientDefs'][$scope]['boolFilterList'][] = 'withArchived';
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
     * @param array $indexes
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

    /**
     * @param array $data
     *
     * @return array
     */
    protected function addOnlyDeletedFilter(array $data): array
    {
        foreach ($data['entityDefs'] as $entity => $row) {
            $data['clientDefs'][$entity]['boolFilterList'][] = 'onlyDeleted';
        }

        return $data;
    }
}
