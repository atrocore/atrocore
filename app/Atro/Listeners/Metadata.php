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

declare(strict_types=1);

namespace Atro\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Core\KeyValueStorages\StorageInterface;
use Atro\Core\Templates\Repositories\Relation;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\Database\Orm\RelationManager;
use Espo\Core\Utils\Util;

class Metadata extends AbstractListener
{
    public function modify(Event $event): void
    {
        $data = $event->getArgument('data');

        $data = $this->addOwner($data);

        $data = $this->addOnlyActiveFilter($data);

        $data = $this->addOnlyDeletedFilter($data);

        $data = $this->addArchive($data);

        $data = $this->addActive($data);

        $data = $this->prepareMultiLang($data);

        // prepare multi-lang labels
        $data = $this->prepareMultiLangLabels($data);

        $data = $this->setForeignName($data);

        $data = $this->showConnections($data);

        $data = $this->prepareHierarchyEntities($data);

        $data = $this->prepareBoolFieldView($data);

        $this->prepareRanges($data);

        $this->prepareUnit($data);

        $this->prepareLanguageField($data);

        $this->setTranslationRequiredLanguage($data);

        $this->prepareScriptField($data);

        $this->prepareModifiedIntermediateEntities($data);

        $this->pushDynamicActions($data);

        $this->prepareExtensibleEnum($data);

        $event->setArgument('data', $data);
    }

    public function afterInit(Event $event): void
    {
        $data = $event->getArgument('data');

        $this->prepareRelationEntities($data);

        if (!empty($data['action']['types'])) {
            $data['entityDefs']['Action']['fields']['type']['optionsIds'] = array_keys($data['action']['types']);
            $data['entityDefs']['Action']['fields']['type']['options'] = array_keys($data['action']['types']);
        }

        $event->setArgument('data', $data);
    }

    protected function prepareExtensibleEnum(array &$data): void
    {
        foreach ($data['entityDefs'] as $entityType => $entityDefs) {
            if (empty($entityDefs['fields'])) {
                continue;
            }

            foreach ($entityDefs['fields'] as $field => $fieldDefs) {
                if (!empty($fieldDefs['type']) && in_array($fieldDefs['type'], ['extensibleEnum', 'extensibleMultiEnum', 'measure']) && empty($fieldDefs['view'])) {
                    if (!empty($fieldDefs['dropdown'])) {
                        if ($fieldDefs['type'] == 'extensibleMultiEnum') {
                            $data['entityDefs'][$entityType]['fields'][$field]['view'] = 'views/fields/extensible-multi-enum-dropdown';
                        } else {
                            if ($fieldDefs['type'] == 'extensibleEnum') {
                                $data['entityDefs'][$entityType]['fields'][$field]['view'] = 'views/fields/extensible-enum-dropdown';
                            } else {
                                $data['entityDefs'][$entityType]['fields'][$field]['view'] = 'views/fields/measure-dropdown';
                            }
                        }
                    }
                }
            }
        }
    }

    public function pushDynamicActions(array &$data): void
    {
        if (!$this->getConfig()->get('isInstalled', false)) {
            return;
        }

        $dataManager = $this->getContainer()->get('dataManager');

        $actions = $dataManager->getCacheData('dynamic_action');
        if ($actions === null) {
            $connection = $this->getEntityManager()->getConnection();
            try {
                $actions = $connection->createQueryBuilder()
                    ->select('t.*')
                    ->from($connection->quoteIdentifier('action'), 't')
                    ->where('t.deleted = :false')
                    ->andWhere('t.is_active = :true')
                    ->setParameter('true', true, ParameterType::BOOLEAN)
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->fetchAllAssociative();
            } catch (\Throwable $e) {
                $actions = [];
            }

            $dataManager->setCacheData('dynamic_action', $actions);
        }

        $this->getMemoryStorage()->set('dynamic_action', $actions);
    }

    protected function getMemoryStorage(): StorageInterface
    {
        return $this->getContainer()->get('memoryStorage');
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

    protected function prepareModifiedIntermediateEntities(array &$data): void
    {
        foreach ($data['scopes'] as $scope => $defs) {
            if (array_key_exists('modifiedExtendedRelations', $defs) && is_array($defs['modifiedExtendedRelations'])) {
                foreach ($defs['modifiedExtendedRelations'] as $relation) {
                    $relationDefs = $data['entityDefs'][$scope]['links'][$relation] ?? [];

                    if (is_array($relationDefs) && !empty($relationDefs['entity']) && !empty($relationDefs['foreign'])) {
                        if (!isset($data['scopes'][$relationDefs['entity']]['modifiedExtendedIntermediateRelations'])) {
                            $data['scopes'][$relationDefs['entity']]['modifiedExtendedIntermediateRelations'] = [];
                        }

                        $data['scopes'][$relationDefs['entity']]['modifiedExtendedIntermediateRelations'][] = $relationDefs['foreign'];
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
                    "notStorable" => !empty($fieldDefs['notStorable']),
                    "emHidden"    => true
                ];

                if (isset($fieldDefs['multilangLocale'])) {
                    $data['entityDefs'][$entityType]['fields'][$unitFieldName]['multilangLocale'] = $fieldDefs['multilangLocale'];
                }

                $data['entityDefs'][$entityType]['links'][$unitFieldName] = [
                    "type"        => "belongsTo",
                    "entity"      => "Unit",
                    "skipOrmDefs" => !empty($fieldDefs['notStorable']),
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

    protected function prepareRelationEntities(array &$data): void
    {
        if (empty($data['entityDefs'])) {
            return;
        }

        $relationManager = new RelationManager($data['entityDefs']);

        $relations = [];
        foreach ($data['entityDefs'] as $entityName => $entityDefs) {
            if (empty($entityDefs['links'])) {
                continue;
            }
            foreach ($entityDefs['links'] as $linkName => $linkParams) {
                if (isset($linkParams['skipOrmDefs']) && $linkParams['skipOrmDefs'] === true) {
                    continue;
                }
                $convertedLink = $relationManager->convert($linkName, $linkParams, $entityName, []);
                if (isset($convertedLink[$entityName]['relations'])) {
                    foreach ($convertedLink[$entityName]['relations'] as $k => $v) {
                        $relations[$entityName]['relations'][$k] = $v;
                    }
                }
            }
        }

        $res = [];

        foreach ($relations as $scope => $entityDefs) {
            foreach ($entityDefs['relations'] as $relationParams) {
                if (empty($relationParams['type']) || $relationParams['type'] !== 'manyMany') {
                    continue;
                }

                $entityName = ucfirst($relationParams['relationName']);

                if (isset($res[$entityName])) {
                    continue;
                }

                // MIDDLE columns
                if (!empty($relationParams['midKeys'])) {
                    $leftId = $relationParams['midKeys'][0];
                    $left = substr($leftId, 0, -2);

                    if ($entityName === 'EntityTeam') {
                        $res[$entityName]['fields'][$leftId] = [
                            'type'     => 'varchar',
                            'len'      => 24,
                            'required' => true
                        ];
                    } else {
                        $res[$entityName]['fields'][$left] = [
                            'type'          => 'link',
                            'required'      => true,
                            'relationField' => true
                        ];
                        $res[$entityName]['links'][$left] = [
                            'type'   => 'belongsTo',
                            'entity' => $scope
                        ];
                    }

                    $rightId = $relationParams['midKeys'][1];
                    $right = substr($rightId, 0, -2);

                    $res[$entityName]['fields'][$right] = [
                        'type'          => 'link',
                        'required'      => true,
                        'relationField' => true
                    ];
                    $res[$entityName]['links'][$right] = [
                        'type'   => 'belongsTo',
                        'entity' => $relationParams['entity']
                    ];

                    $res[$entityName]['uniqueIndexes']['unique_relation'] = ['deleted', Util::toUnderScore($leftId), Util::toUnderScore($rightId)];
                }

                // ADDITIONAL columns
                if (!empty($relationParams['additionalColumns'])) {
                    foreach ($relationParams['additionalColumns'] as $fieldName => $fieldParams) {
                        if (!isset($fieldParams['type'])) {
                            $fieldParams = array_merge($fieldParams, ['type' => 'varchar', 'len' => 255]);
                        }
                        $res[$entityName]['fields'][$fieldName] = $fieldParams;
                    }
                }

                if (!empty($relationParams['conditions'])) {
                    foreach ($relationParams['conditions'] as $fieldName => $fieldParams) {
                        $res[$entityName]['uniqueIndexes']['unique_relation'][] = Util::toUnderScore($fieldName);
                    }
                }
            }
        }

        $defaultClientDefs = json_decode(file_get_contents(dirname(__DIR__) . '/Core/Templates/Metadata/Relation/clientDefs.json'), true);
        $defaultEntityDefs = json_decode(file_get_contents(dirname(__DIR__) . '/Core/Templates/Metadata/Relation/entityDefs.json'), true);
        $defaultScopes = json_decode(file_get_contents(dirname(__DIR__) . '/Core/Templates/Metadata/Relation/scopes.json'), true);

        $virtualFieldDefs = [
            'notStorable'          => true,
            'layoutListDisabled'   => true,
            'layoutDetailDisabled' => true,
            'massUpdateDisabled'   => true,
            'importDisabled'       => true,
            'exportDisabled'       => true,
            'emHidden'             => true,
            'isCustom'             => false,
            'filterDisabled'       => true,
            'unique'               => false,
            'required'             => false,
        ];

        foreach ($res as $entityName => $entityDefs) {
            $current = $data['clientDefs'][$entityName] ?? [];
            $data['clientDefs'][$entityName] = empty($current) ? $defaultClientDefs : Util::merge($defaultClientDefs, $current);

            $current = $data['entityDefs'][$entityName] ?? [];
            $current = empty($current) ? $entityDefs : Util::merge($entityDefs, $current);

            $additionalFields = array_filter($current['fields'], function ($row) {
                return empty($row['relationField']);
            });

            // put virtual fields to entities
            if (!empty($additionalFields)) {
                $relFields = array_filter($current['fields'], function ($row) {
                    return !empty($row['relationField']);
                });
                foreach ($relFields as $relField => $relDefs) {
                    $relEntity = $entityDefs['links'][$relField]['entity'];
                    $data['entityDefs'][$relEntity]['fields'][Relation::buildVirtualFieldName($entityName, 'id')] = array_merge(['type' => 'varchar', 'relId' => true],
                        $virtualFieldDefs);
                    foreach ($additionalFields as $additionalField => $additionalFieldDefs) {
                        if (!empty($additionalFieldDefs['notStorable'])) {
                            continue;
                        }
                        if ($additionalFieldDefs['type'] === 'linkMultiple') {
                            continue;
                        }
                        if ($additionalFieldDefs['type'] === 'link') {
                            $additionalFieldDefs['entity'] = $current['links'][$additionalField]['entity'];
                        }
                        $current['fields'][$additionalField]['additionalField'] = true;
                        $data['entityDefs'][$relEntity]['fields'][Relation::buildVirtualFieldName($entityName, $additionalField)] = array_merge(
                            $additionalFieldDefs, $virtualFieldDefs
                        );
                    }
                }
            }

            $data['entityDefs'][$entityName] = Util::merge($defaultEntityDefs, $current);

            $current = $data['scopes'][$entityName] ?? [];
            $data['scopes'][$entityName] = empty($current) ? $defaultScopes : Util::merge($defaultScopes, $current);

            if (!empty($data['scopes'][$entityName]['isHierarchyEntity'])) {
                $data['scopes'][$entityName]['acl'] = true;
            }

            $data['scopes'][$entityName]['tab'] = false;
            $data['scopes'][$entityName]['layouts'] = false;
            $data['scopes'][$entityName]['customizable'] = false;
        }
    }

    protected function prepareHierarchyEntities(array $data): array
    {
        foreach ($data['entityDefs'] as $scope => $scopeData) {
            if (empty($scopeData['fields'])) {
                continue;
            }

            if (!isset($data['scopes'][$scope]['type']) || $data['scopes'][$scope]['type'] !== 'Hierarchy' || !empty($data['scopes'][$scope]['disableHierarchy'])) {
                continue;
            }

            $relationEntityName = ucfirst($data['entityDefs'][$scope]['links']['children']['relationName']);

            $data['scopes'][$relationEntityName]['isHierarchyEntity'] = true;
            $data['entityDefs'][$relationEntityName]['fields']['hierarchySortOrder'] = [
                'type' => 'int'
            ];

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

                if ($fieldData['type'] === 'autoincrement') {
                    if (!isset($data['scopes'][$scope]['mandatoryUnInheritedFields'])) {
                        $data['scopes'][$scope]['mandatoryUnInheritedFields'] = [];
                    }
                    $data['scopes'][$scope]['mandatoryUnInheritedFields'][] = $fieldName;
                }
            }

            $this->addScopesToRelationShip($data, $scope, $relationEntityName, 'parents');
            $this->addScopesToRelationShip($data, $scope, $relationEntityName, 'children');
        }


        return $data;
    }

    private function addScopesToRelationShip(array &$metadata, string $scope, string $relationEntityName, string $relation)
    {
        if (empty($metadata['clientDefs'][$scope]['relationshipPanels'])) {
            $metadata['clientDefs'][$scope]['relationshipPanels'] = [
                $relation => []
            ];
        }
        $data = $metadata['clientDefs'][$scope]['relationshipPanels'][$relation];
        if (empty($data)) {
            $metadata['clientDefs'][$scope]['relationshipPanels'][$relation] = [
                "aclScopesList" => [$scope, $relationEntityName]
            ];
        } else {
            $metadata['clientDefs'][$scope]['relationshipPanels'][$relation]["aclScopesList"] = array_merge($data['aclScopesList'] ?? [], [$scope, $relationEntityName]);
        }
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

    protected function prepareMultiLangLabels(array $data): array
    {
        foreach ($data['fields'] as $field => $v) {
            foreach ($this->getConfig()->get('interfaceLocales', []) as $locale) {
                if (!empty($data['fields'][$field]['params']) && is_array($data['fields'][$field]['params'])) {
                    $param = ['name' => 'label' . ucfirst(Util::toCamelCase(strtolower($locale))), 'type' => 'varchar'];

                    $data['fields'][$field]['params'] = array_merge([$param], $data['fields'][$field]['params']);
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


        foreach ($data['fields'] as $field => $v) {
            $params = [];
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

    public function addActive(array $data)
    {
        foreach ($data['scopes'] as $scope => $row) {
            if (!empty($row['hasActive']) && empty($row['isActiveUnavailable'])) {
                $data['entityDefs'][$scope]['fields']['isActive']['type'] = 'bool';
                $data['clientDefs'][$scope]['boolFilterList'][] = 'onlyActive';
                $data['clientDefs'][$scope]['boolFilterList'][] = 'notActive';
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

            if (!empty($row['stream'])) {
                $data['clientDefs'][$scope]['boolFilterList'][] = 'onlyFollowed';
            }

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

    /**
     * @param array $data
     *
     * @return array
     */
    protected function addOnlyDeletedFilter(array $data): array
    {
        foreach ($data['entityDefs'] as $entity => $row) {
            if ($entity !== 'File') {
                $data['clientDefs'][$entity]['boolFilterList'][] = 'onlyDeleted';
            }
        }

        return $data;
    }

    protected  function prepareBoolFieldView(array $data){
        foreach ($data['entityDefs'] as $entity => $entityDef) {
           foreach ($entityDef['fields'] as $field => $fieldDefs){
               if($fieldDefs['type'] === 'bool' && $fieldDefs['notNull'] === false){
                   $data['entityDefs'][$entity]['fields'][$field]['view'] = 'views/fields/bool-enum';
               }

               if($fieldDefs['type'] === 'bool' && $fieldDefs['notNull'] !== false){
                   $data['entityDefs'][$entity]['fields'][$field]['notNull'] = true;
               }
           }
        }

        return $data;
    }
}
