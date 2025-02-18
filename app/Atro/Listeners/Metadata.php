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
use Atro\Repositories\NotificationRule;
use Atro\Repositories\PreviewTemplate;
use Doctrine\DBAL\ParameterType;
use Atro\Core\DataManager;
use Espo\Core\Utils\Database\Orm\RelationManager;
use Atro\Core\Utils\Util;

class Metadata extends AbstractListener
{
    public function loadData(Event $event): void
    {
        $data = $event->getArgument('data');

        $this->addFollowersField($data);

        $event->setArgument('data', $data);
    }

    public function modify(Event $event): void
    {
        $data = $event->getArgument('data');

        $data = $this->addOwner($data);

        $this->addBoolFilters($data);

        $data = $this->addArchive($data);

        $data = $this->addActive($data);

        $data = $this->prepareMultiLang($data);

        $data = $this->setForeignName($data);

        $data = $this->prepareHierarchyEntities($data);

        $this->prepareRanges($data);

        $this->prepareUnit($data);

        $this->setTranslationRequiredLanguage($data);

        $this->prepareScriptField($data);

        $this->prepareModifiedIntermediateEntities($data);

        $this->pushDynamicActions($data);

        $this->prepareExtensibleEnum($data);

        $this->prepareAclActionLevelListMap($data);

        $this->addPreviewTemplates($data);

        $this->prepareNotificationRuleTransportField($data);

        $this->addNotificationRulesToCache($data);

        $data['multilang']['languageList'] = $data['entityDefs']['Language']['fields']['code']['options'];

        // multiParents is mandatory disabled for Folder
        $data['scopes']['Folder']['multiParents'] = false;

        $this->prepareEntityFields($data);

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

    protected function addFollowersField(array &$data): void
    {
        foreach ($data['scopes'] ?? [] as $scope => $scopeDefs) {
            if (empty($scopeDefs['type'])) {
                continue;
            }

            if (array_key_exists('stream', $scopeDefs) && !array_key_exists('streamDisabled', $scopeDefs)) {
                $data['scopes'][$scope]['streamDisabled'] = $scopeDefs['streamDisabled'] = empty($scopeDefs['stream']);
            }

            if (!empty($scopeDefs['streamDisabled']) || !empty($scopeDefs['notStorable'])) {
                continue;
            }

            $data['entityDefs'][$scope]['fields']['followers'] = [
                'type'   => 'linkMultiple',
                'noLoad' => true
            ];

            $data['entityDefs'][$scope]['links']['followers'] = [
                'type'         => 'hasMany',
                'relationName' => 'UserFollowed' . $scope,
                'foreign'      => 'followed' . Util::pluralize($scope),
                'entity'       => 'User'
            ];

            $data['entityDefs']['User']['fields']['followed' . Util::pluralize($scope)] = [
                'type'   => 'linkMultiple',
                'noLoad' => true
            ];

            $data['entityDefs']['User']['links']['followed' . Util::pluralize($scope)] = [
                'type'         => 'hasMany',
                'relationName' => 'UserFollowed' . $scope,
                'foreign'      => 'followers',
                'entity'       => $scope
            ];
        }
    }

    protected function prepareEntityFields(array &$data): void
    {
        foreach ($data['fields'] ?? [] as $type => $defs) {
            if (empty($defs['params'])) {
                continue;
            }

            foreach ($defs['params'] as $item) {
                if (empty($item['name']) || !empty($item['hidden'])) {
                    continue;
                }
                $data['clientDefs']['EntityField']['dynamicLogic']['fields'][$item['name']]['visible']['conditionGroup'][0]['type'] = 'in';
                $data['clientDefs']['EntityField']['dynamicLogic']['fields'][$item['name']]['visible']['conditionGroup'][0]['attribute'] = 'type';
                $data['clientDefs']['EntityField']['dynamicLogic']['fields'][$item['name']]['visible']['conditionGroup'][0]['value'][] = $type;

                if (!empty($item['required'])) {
                    $data['clientDefs']['EntityField']['dynamicLogic']['fields'][$item['name']]['required']['conditionGroup'][0]['type'] = 'in';
                    $data['clientDefs']['EntityField']['dynamicLogic']['fields'][$item['name']]['required']['conditionGroup'][0]['attribute'] = 'type';
                    $data['clientDefs']['EntityField']['dynamicLogic']['fields'][$item['name']]['required']['conditionGroup'][0]['value'][] = $type;
                }

                if (!empty($item['readOnly'])) {
                    $data['clientDefs']['EntityField']['dynamicLogic']['fields'][$item['name']]['readOnly']['conditionGroup'][0]['type'] = 'in';
                    $data['clientDefs']['EntityField']['dynamicLogic']['fields'][$item['name']]['readOnly']['conditionGroup'][0]['attribute'] = 'type';
                    $data['clientDefs']['EntityField']['dynamicLogic']['fields'][$item['name']]['readOnly']['conditionGroup'][0]['value'][] = $type;
                }
            }
        }

        $data['clientDefs']['EntityField']['dynamicLogic']['fields']['isMultilang']['visible']['conditionGroup'][] = [
            "type"      => "isEmpty",
            "attribute" => "multilangField"
        ];
    }

    protected function prepareAclActionLevelListMap(array &$data): void
    {
        foreach ($data['scopes'] as $scope => $scopeDefs) {
            if (empty($scopeDefs['streamDisabled'])) {
                $data['scopes'][$scope]['aclActionLevelListMap']['stream'] = [
                    'all',
                    'no'
                ];
            }
        }
    }

    protected function prepareExtensibleEnum(array &$data): void
    {
        foreach ($data['entityDefs'] as $entityType => $entityDefs) {
            if (empty($entityDefs['fields'])) {
                continue;
            }

            foreach ($entityDefs['fields'] as $field => $fieldDefs) {
                $dropdownTypes = ['extensibleEnum', 'extensibleMultiEnum', 'link', 'linkMultiple', 'measure'];
                if (!empty($fieldDefs['type']) && in_array($fieldDefs['type'],
                        $dropdownTypes) && empty($fieldDefs['view'])) {
                    if (!empty($fieldDefs['dropdown'])) {
                        switch ($fieldDefs['type']) {
                            case 'extensibleEnum':
                                $viewType = 'extensible-enum';
                                break;
                            case 'extensibleMultiEnum':
                                $viewType = 'extensible-multi-enum';
                                break;
                            case 'linkMultiple':
                                $viewType = 'link-multiple';
                                break;
                            default:
                                $viewType = $fieldDefs['type'];
                                break;
                        }

                        $data['entityDefs'][$entityType]['fields'][$field]['view'] = "views/fields/$viewType-dropdown";
                        $data['entityDefs'][$entityType]['fields'][$field]['ignoreViewForSearch'] = true;
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

        foreach ($actions ?? [] as $action) {
            if (in_array($action['type'], ['webhook', 'set'])) {
                if ($action['usage'] === 'record' && !empty($action['source_entity'])) {
                    $data['clientDefs'][$action['source_entity']]['dynamicRecordActions'][] = [
                        'id'         => $action['id'],
                        'name'       => $action['name'],
                        'display'    => $action['display'],
                        'massAction' => !empty($action['mass_action']),
                        'acl'        => [
                            'scope'  => $action['source_entity'],
                            'action' => 'read',
                        ]
                    ];
                }
                if ($action['usage'] === 'entity' && !empty($action['source_entity'])) {
                    $data['clientDefs'][$action['source_entity']]['dynamicEntityActions'][] = [
                        'id'      => $action['id'],
                        'name'    => $action['name'],
                        'display' => $action['display'],
                        'acl'     => [
                            'scope'  => $action['source_entity'],
                            'action' => 'read',
                        ]
                    ];
                }
            }
        }
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
                if (empty($fieldDefs['type']) || !in_array($fieldDefs['type'],
                        ['int', 'float', 'rangeInt', 'rangeFloat', 'varchar'])) {
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
                    "notStorable" => !empty($fieldDefs['notStorable']),
                    "emHidden"    => true
                ];

                if (isset($fieldDefs['multilangLocale'])) {
                    $data['entityDefs'][$entityType]['fields'][$unitFieldName]['multilangLocale'] = $fieldDefs['multilangLocale'];
                }

                $data['entityDefs'][$entityType]['links'][$unitFieldName] = [
                    "type"                        => "belongsTo",
                    "entity"                      => "Unit",
                    "skipOrmDefs"                 => !empty($fieldDefs['notStorable']),
                    'layoutRelationshipsDisabled' => true,
                ];

                if ($visibleLogic = $this->getMetadata()->get([
                    'clientDefs',
                    $entityType,
                    'dynamicLogic',
                    'fields',
                    $field,
                    'visible'
                ])) {
                    $data['clientDefs'][$entityType]['dynamicLogic']['fields'][$unitFieldName]['visible'] = $visibleLogic;
                }

                if (($readOnly = $this->getMetadata()->get([
                    'clientDefs',
                    $entityType,
                    'dynamicLogic',
                    'fields',
                    $field,
                    'readOnly'
                ]))) {
                    $data['clientDefs'][$entityType]['dynamicLogic']['fields'][$unitFieldName]['readOnly'] = $readOnly;
                }

                if ($requireLogic = $this->getMetadata()->get([
                    'clientDefs',
                    $entityType,
                    'dynamicLogic',
                    'fields',
                    $field,
                    'required'
                ])) {
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
                        "filterDisabled"     => true,
                        "massUpdateDisabled" => true,
                        "emHidden"           => true
                    ];

                    if ($visibleLogic = $this->getMetadata()->get([
                        'clientDefs',
                        $entityType,
                        'dynamicLogic',
                        'fields',
                        $field,
                        'visible'
                    ])) {
                        $data['clientDefs'][$entityType]['dynamicLogic']['fields'][$virtualFieldName]['visible'] = $visibleLogic;
                    }

                    if (($readOnly = $this->getMetadata()->get([
                        'clientDefs',
                        $entityType,
                        'dynamicLogic',
                        'fields',
                        $field,
                        'readOnly'
                    ]))) {
                        $data['clientDefs'][$entityType]['dynamicLogic']['fields'][$virtualFieldName]['readOnly'] = $readOnly;
                    }

                    if ($requireLogic = $this->getMetadata()->get([
                        'clientDefs',
                        $entityType,
                        'dynamicLogic',
                        'fields',
                        $field,
                        'required'
                    ])) {
                        $data['clientDefs'][$entityType]['dynamicLogic']['fields'][$virtualFieldName]['required'] = $requireLogic;
                    }
                } else {
                    $data['entityDefs'][$entityType]['fields'][$field]['unitField'] = true;
                }

                foreach (in_array($fieldDefs['type'], ['int', 'float', 'varchar']) ? [$field] : [
                    $field . 'From',
                    $field . 'To'
                ] as $v) {
                    $data['entityDefs'][$entityType]['fields'][$v . 'AllUnits'] = [
                        "type"                 => "jsonObject",
                        "notStorable"          => true,
                        "mainField"            => $field,
                        "required"             => false,
                        "layoutListDisabled"   => true,
                        "layoutDetailDisabled" => true,
                        "massUpdateDisabled"   => true,
                        "filterDisabled"       => true,
                        "exportDisabled"       => true,
                        "importDisabled"       => true,
                        "emHidden"             => true
                    ];

                    if (isset($fieldDefs['multilangLocale'])) {
                        $data['entityDefs'][$entityType]['fields'][$v . 'AllUnits']['multilangLocale'] = $fieldDefs['multilangLocale'];
                    }

                    $data['entityDefs'][$entityType]['fields'][$v . 'UnitData'] = [
                        "type"                 => "jsonObject",
                        "notStorable"          => true,
                        "mainField"            => $field,
                        "required"             => false,
                        "layoutListDisabled"   => true,
                        "layoutDetailDisabled" => true,
                        "massUpdateDisabled"   => true,
                        "filterDisabled"       => true,
                        "exportDisabled"       => true,
                        "importDisabled"       => true,
                        "emHidden"             => true
                    ];

                    if (isset($fieldDefs['multilangLocale'])) {
                        $data['entityDefs'][$entityType]['fields'][$v . 'UnitData']['multilangLocale'] = $fieldDefs['multilangLocale'];
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

                $ignoredEntity = $entityName === 'AssociatedProduct' || strpos($entityName, 'Hierarchy') !== false;

                $additionalFields = [];
                if (!$ignoredEntity && !empty($data['entityDefs'][$entityName]['fields'])) {
                    $additionalFields = array_filter($data['entityDefs'][$entityName]['fields'], function ($row) {
                        return empty($row['relationField']) && empty($row['notStorable']);
                    });
                }

                // MIDDLE columns
                if (!empty($relationParams['midKeys'])) {
                    $leftId = $relationParams['midKeys'][0];
                    $left = substr($leftId, 0, -2);

                    $rightId = $relationParams['midKeys'][1];
                    $right = substr($rightId, 0, -2);

                    if ($entityName === 'EntityTeam') {
                        $res[$entityName]['fields'][$leftId] = [
                            'type'     => 'varchar',
                            'len'      => 36,
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

                        if (!empty($additionalFields)) {
                            $relFieldName = $left . ucfirst(Util::pluralize($right));
                            if (empty($data['entityDefs'][$scope]['fields'][$relFieldName])
                                && empty($data['entityDefs'][$scope]['links'][$relFieldName])) {
                                $res[$entityName]['links'][$left]['foreign'] = $relFieldName;
                                $data['entityDefs'][$scope]['fields'][$relFieldName] = [
                                    'type'                 => 'linkMultiple',
                                    'linkToRelationEntity' => $relationParams['entity'],
                                    'layoutDetailDisabled' => true,
                                    'massUpdateDisabled'   => true,
                                    'noLoad'               => true
                                ];
                                $data['entityDefs'][$scope]['links'][$relFieldName] = [
                                    'type'    => 'hasMany',
                                    'foreign' => $left,
                                    'entity'  => $entityName
                                ];
                            }

                        }
                    }

                    $res[$entityName]['fields'][$right] = [
                        'type'          => 'link',
                        'required'      => true,
                        'relationField' => true
                    ];
                    $res[$entityName]['links'][$right] = [
                        'type'   => 'belongsTo',
                        'entity' => $relationParams['entity']
                    ];

                    if (!empty($additionalFields)) {
                        $relFieldName = $right . ucfirst(Util::pluralize($left));
                        if (empty($data['entityDefs'][$relationParams['entity']]['fields'][$relFieldName])
                            && empty($data['entityDefs'][$relationParams['entity']]['links'][$relFieldName])) {
                            $res[$entityName]['links'][$right]['foreign'] = $relFieldName;
                            $data['entityDefs'][$relationParams['entity']]['fields'][$relFieldName] = [
                                'type'                 => 'linkMultiple',
                                'linkToRelationEntity' => $scope,
                                'layoutDetailDisabled' => true,
                                'massUpdateDisabled'   => true,
                                'noLoad'               => true
                            ];
                            $data['entityDefs'][$relationParams['entity']]['links'][$relFieldName] = [
                                'type'    => 'hasMany',
                                'foreign' => $right,
                                'entity'  => $entityName
                            ];
                        }
                    }

                    $res[$entityName]['uniqueIndexes']['unique_relation'] = [
                        'deleted',
                        Util::toUnderScore($leftId),
                        Util::toUnderScore($rightId)
                    ];
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

        $defaultClientDefs = json_decode(file_get_contents(dirname(__DIR__) . '/Core/Templates/Metadata/Relation/clientDefs.json'),
            true);
        $defaultEntityDefs = json_decode(file_get_contents(dirname(__DIR__) . '/Core/Templates/Metadata/Relation/entityDefs.json'),
            true);
        $defaultScopes = json_decode(file_get_contents(dirname(__DIR__) . '/Core/Templates/Metadata/Relation/scopes.json'),
            true);

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
            $data['clientDefs'][$entityName] = empty($current) ? $defaultClientDefs : Util::merge($defaultClientDefs,
                $current);

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
                    $data['entityDefs'][$relEntity]['fields'][Relation::buildVirtualFieldName($entityName,
                        'id')] = array_merge(['type' => 'varchar', 'relId' => true],
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
                        $data['entityDefs'][$relEntity]['fields'][Relation::buildVirtualFieldName($entityName,
                            $additionalField)] = array_merge(
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
            if (!isset($data['scopes'][$entityName]['streamDisabled'])) {
                $data['scopes'][$entityName]['streamDisabled'] = true;
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

            if (empty($data['entityDefs'][$scope]['links']['children']['relationName'])) {
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
                "type"                 => "bool",
                "notStorable"          => true,
                "layoutListDisabled"   => true,
                "layoutDetailDisabled" => true,
                "massUpdateDisabled"   => true,
                "filterDisabled"       => true,
                "importDisabled"       => true,
                "exportDisabled"       => true,
                "emHidden"             => true
            ];

            $data['entityDefs'][$scope]['fields']['hasChildren'] = [
                "type"                 => "bool",
                "notStorable"          => true,
                "layoutListDisabled"   => true,
                "layoutDetailDisabled" => true,
                "massUpdateDisabled"   => true,
                "filterDisabled"       => true,
                "importDisabled"       => true,
                "exportDisabled"       => true,
                "emHidden"             => true
            ];

            $data['entityDefs'][$scope]['fields']['hierarchyRoute'] = [
                "type"                 => "jsonObject",
                "notStorable"          => true,
                "layoutListDisabled"   => true,
                "layoutDetailDisabled" => true,
                "massUpdateDisabled"   => true,
                "filterDisabled"       => true,
                "importDisabled"       => true,
                "exportDisabled"       => true,
                "emHidden"             => true
            ];

            $data['entityDefs'][$scope]['fields']['inheritedFields'] = [
                "type"                 => "array",
                "notStorable"          => true,
                "layoutListDisabled"   => true,
                "layoutDetailDisabled" => true,
                "massUpdateDisabled"   => true,
                "filterDisabled"       => true,
                "importDisabled"       => true,
                "exportDisabled"       => true,
                "emHidden"             => true
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

            if (empty($data['scopes'][$scope]['multiParents'])) {
                $data['entityDefs'][$scope]['fields']['parent'] = [
                    "type"           => "link",
                    "notStorable"    => true,
                    "entity"         => $scope,
                    "emHidden"       => true,
                    "exportDisabled" => false,
                    "importDisabled" => false
                ];

                $data['entityDefs'][$scope]['fields']['parents'] = array_merge($data['entityDefs'][$scope]['fields']['parents'],
                    [
                        "layoutListDisabled"   => true,
                        "layoutDetailDisabled" => true,
                        "massUpdateDisabled"   => true,
                        "filterDisabled"       => true,
                        "importDisabled"       => true,
                        "emHidden"             => true
                    ]);
                $data['entityDefs'][$scope]['links']['parents']['layoutRelationshipsDisabled'] = true;
            }
        }

        return $data;
    }

    private function addScopesToRelationShip(
        array  &$metadata,
        string $scope,
        string $relationEntityName,
        string $relation
    )
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
            $metadata['clientDefs'][$scope]['relationshipPanels'][$relation]["aclScopesList"] = array_merge($data['aclScopesList'] ?? [],
                [$scope, $relationEntityName]);
        }
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

                $fieldParams = $data['fields'][$params['type']]['params'] ?? [];
                $multilingual = in_array('isMultilang', array_column($fieldParams, 'name'));

                $newFields[$field] = $params;
                if ($multilingual && !empty($params['isMultilang'])) {
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
                                $index = array_key_exists('default', $params) ? array_search($params['default'],
                                    $params['options']) : false;
                                $mParams['default'] = $index !== false ? $mParams['options'][$index] : null;
                            } else {
                                $mParams['default'] = null;
                            }
                            $mParams['required'] = false;
                            $mParams['emHidden'] = true;
                        }
                        if ($mParams['type'] == 'script' && isset($mParams['script' . $preparedLocale])) {
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
                    $data['entityDefs']['Account']['fields'][$foreign]['type'] = 'linkMultiple';
                    $data['entityDefs']['Account']['fields'][$foreign]['layoutDetailDisabled'] = true;
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

    protected function addPreviewTemplates(array &$data): void
    {
        if (!$this->getConfig()->get('isInstalled', false)) {
            return;
        }

        /** @var DataManager $dataManager */
        $dataManager = $this->getContainer()->get('dataManager');
        $previewTemplates = $dataManager->getCacheData(PreviewTemplate::CACHE_NAME);
        if ($previewTemplates === null) {
            try {
                $previewTemplates = $this->getEntityManager()->getConnection()->createQueryBuilder()
                    ->select('id, name, entity_type')
                    ->from('preview_template')
                    ->where('is_active = :true')
                    ->andWhere('deleted = :false')
                    ->setParameter('true', true, ParameterType::BOOLEAN)
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->fetchAllAssociative();
            } catch (\Throwable $e) {
                $previewTemplates = [];
            }

            $dataManager->setCacheData(PreviewTemplate::CACHE_NAME, $previewTemplates);
        }

        foreach ($previewTemplates as $previewTemplate) {
            $data['clientDefs'][$previewTemplate['entity_type']]['additionalButtons'][$previewTemplate['id']] = [
                'name'           => $previewTemplate['id'],
                'label'          => $previewTemplate['name'],
                'actionViewPath' => 'views/preview-template/record/actions/preview',
                'action'         => 'showHtmlPreview',
                'optionsToPass'  => [
                    'model'
                ]
            ];
        }
    }

    protected function prepareNotificationRuleTransportField(array &$data): void
    {
        foreach (array_keys(($this->getMetadata()->get(['app', 'notificationTransports'], []))) as $transport) {
            $data['entityDefs']['NotificationRule']['fields'][$transport . 'Active'] = [
                "type"         => "bool",
                "virtualField" => true,
                "notStorable"  => true
            ];
            // field for the notification template selected for this transport
            $data['entityDefs']['NotificationRule']['fields'][$transport . 'TemplateId'] = [
                "type"           => "varchar",
                "virtualField"   => true,
                "notStorable"    => true,
                "filterDisabled" => true,
                "view"           => "views/notification-rule/fields/notification-template",
                "name"           => $transport . 'Template',
                "t_type"         => $transport
            ];
            $data['entityDefs']['NotificationRule']['fields'][$transport . 'TemplateName'] = [
                "type"           => "varchar",
                "filterDisabled" => true,
                "readOnly"       => true,
                "notStorable"    => true
            ];

            $data['clientDefs']['NotificationRule']['dynamicLogic']['fields'][$transport . 'TemplateId'] = [
                "required" => [
                    "conditionGroup" => [
                        [
                            "type"      => "isTrue",
                            "attribute" => $transport . 'Active'
                        ]
                    ]
                ]
            ];
        }
    }

    protected function addNotificationRulesToCache(array &$data): void
    {
        if (!$this->getConfig()->get('isInstalled', false)) {
            return;
        }

        /** @var DataManager $dataManager */
        $dataManager = $this->getContainer()->get('dataManager');
        $cachedData = $dataManager->getCacheData(NotificationRule::CACHE_NAME);
        if (!isset($cachedData['notificationRules']) || !isset($cachedData['users']) || !isset($cachedData['notificationProfilesIds'])) {
            $notificationProfilesIds = [];
            $connection = $this->getEntityManager()->getConnection();
            try {
                $notificationRules = $connection->createQueryBuilder()
                    ->select('nr.*')
                    ->from($connection->quoteIdentifier('notification_rule'), 'nr')
                    ->leftJoin('nr', 'notification_profile', 'np',
                        'nr.notification_profile_id = np.id AND np.deleted = :false')
                    ->where('nr.is_active = :true')
                    ->andWhere('nr.deleted = :false')
                    ->andWhere('np.is_active = :true')
                    ->setParameter('true', true, ParameterType::BOOLEAN)
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->fetchAllAssociative();
            } catch (\Throwable $e) {
                $notificationRules = [];
            }

            $users = [];

            foreach ($notificationRules as $notificationRule) {
                $notificationProfileId = $notificationRule['notification_profile_id'];

                if (!isset($users[$notificationProfileId])) {
                    try {
                        $users[$notificationProfileId] = $this->getEntityManager()
                            ->getRepository('NotificationRule')
                            ->getNotificationProfileUsers($notificationProfileId);

                        if (!empty($users[$notificationProfileId])) {
                            $notificationProfilesIds[] = $notificationProfileId;
                        }

                    } catch (\Throwable $e) {
                        $users[$notificationProfileId] = [];
                    }
                }
            }

            $dataManager->setCacheData(NotificationRule::CACHE_NAME, $cachedData = [
                "notificationProfilesIds" => $notificationProfilesIds,
                "notificationRules"       => $notificationRules,
                "users"                   => $users
            ]);
        }

        $data['app']['activeNotificationProfilesIds'] = $cachedData['notificationProfilesIds'];

        foreach ($cachedData['notificationRules'] as $notificationRule) {
            if (!empty($notificationRule['entity'])) {
                $data['scopes'][$notificationRule['entity']]['notificationRuleIdByOccurrence'][$notificationRule['occurrence']][] = $notificationRule['id'];
            } else {
                $data['app']['globalNotificationRuleIdByOccurrence'][$notificationRule['occurrence']][] = $notificationRule['id'];
            }
        }
    }

    protected function addBoolFilters(array &$data): void
    {
        foreach ($data['scopes'] as $entity => $defs) {
            if (empty($defs['type'])) {
                continue;
            }

            $entityDefs = $data['entityDefs'][$entity] ?? null;
            if (empty($entityDefs)) {
                continue;
            }

            $data['clientDefs'][$entity]['boolFilterList'][] = 'fieldsFilter';
            $data['clientDefs'][$entity]['hiddenBoolFilterList'][] = 'fieldsFilter';

            if (isset($entityDefs['fields']['isActive']['type']) && $entityDefs['fields']['isActive']['type'] == 'bool') {
                $data['clientDefs'][$entity]['boolFilterList'][] = 'onlyActive';
            }

            $data['clientDefs'][$entity]['boolFilterList'][] = 'onlyDeleted';

            if (empty($defs['bookmarkDisabled'])) {
                $data['clientDefs'][$entity]['boolFilterList'][] = 'onlyBookmarked';
                $data['clientDefs'][$entity]['treeScopes'][] = 'Bookmark';
                $data['entityDefs'][$entity]['fields']['bookmarkId'] = [
                    "type"                 => "varchar",
                    "notStorable"          => true,
                    "layoutListDisabled"   => true,
                    "layoutDetailDisabled" => true,
                    "massUpdateDisabled"   => true,
                    "filterDisabled"       => true,
                    "exportDisabled"       => true,
                    "importDisabled"       => true,
                    "emHidden"             => true
                ];
            }
        }
    }
}
