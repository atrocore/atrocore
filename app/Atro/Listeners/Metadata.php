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

use Atro\ActionTypes\AbstractAction;
use Atro\ConditionTypes\AbstractConditionType;
use Atro\Console\CreateAction;
use Atro\Console\CreateConditionType;
use Atro\Core\EventManager\Event;
use Atro\Core\KeyValueStorages\StorageInterface;
use Atro\Entities\File;
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
        $this->prepareUserProfile($data);

        $event->setArgument('data', $data);
    }

    public function modify(Event $event): void
    {
        $data = $event->getArgument('data');

        $data = $this->addOwner($data);

        $this->addBoolFilters($data);

        $data = $this->addArchive($data);

        $data = $this->addActive($data);

        $this->prepareDerivatives($data);

        $this->addAttributesToEntity($data);

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

        foreach ($data['scopes'] as $scope => $scopeDefs) {
            if (!empty($scopeDefs['emHidden']) || empty($scopeDefs['type']) || !in_array($scopeDefs['type'],
                    ['Base', 'Hierarchy'])) {
                $data['scopes'][$scope]['attributesDisabled'] = true;
            }
        }

        $this->putCustomCodeActions($data);
        $this->putCustomCodeConditionTypes($data);

        $this->addAssociateToEntity($data);

        $this->prepareMultilingualAttributes($data);

        $data = $this->prepareClassificationAttributeMetadata($data);

        $this->addClassificationToEntity($data);

        $this->prepareMetadataViaMatchings($data);

        $this->addThumbnailFieldsByTypesToFile($data);

        $event->setArgument('data', $data);
    }

    public function afterInit(Event $event): void
    {
        $data = $event->getArgument('data');

        $this->prepareRelationEntities($data);

        if (!empty($data['action']['types'])) {
            $data['entityDefs']['Action']['fields']['type']['options'] = array_keys($data['action']['types']);
        }

        $event->setArgument('data', $data);
    }

    protected function prepareMultilingualAttributes(array &$data): void
    {
        // find multilingual attributes
        $multilingualAttributes = [];
        foreach ($data['attributes'] as $attribute => $attributeDefs) {
            if (!empty($attributeDefs['multilingual'])) {
                $multilingualAttributes[] = $attribute;
            }
        }

        $data['entityDefs']['Attribute']['fields']['isMultilang']['conditionalProperties']['visible']['conditionGroup'] = [
            [
                "type"      => "in",
                "attribute" => "type",
                "value"     => $multilingualAttributes
            ]
        ];
    }

    protected function putCustomCodeActions(array &$data): void
    {
        $dir = CreateAction::DIR;
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $fileName) {
            if (!preg_match('/\.php$/i', $fileName)) {
                continue;
            }

            $name = str_replace('.php', '', $fileName);
            $typeName = 'custom' . $name;

            $className = '\\CustomActions\\' . $name;

            if (!class_exists($className) || !is_a($className, AbstractAction::class, true)) {
                continue;
            }

            $data['action']['types'][$typeName] = $className;
            $data['action']['typesData'][$typeName] = [
                'handler'     => $className,
                'typeLabel'   => $className::getTypeLabel(),
                'name'        => $className::getName(),
                'description' => $className::getDescription(),
            ];
        }
    }

    protected function putCustomCodeConditionTypes(array &$data): void
    {
        foreach (Util::scanDir(CreateConditionType::DIR) as $fileName) {
            $type = str_replace('.php', '', $fileName);

            $className = "\\CustomConditionTypes\\$type";
            if (is_a($className, AbstractConditionType::class, true)) {
                $data['app']['conditionsTypes'][$type] = [
                    'label'      => $className::getTypeLabel(),
                    'entityName' => $className::getEntityName(),
                    'className'  => $className,
                ];
            }
        }
    }

    protected function prepareUserProfile(array &$data): void
    {
        $data['entityDefs']['UserProfile'] = $data['entityDefs']['User'];

        $systemFields = [
            'type',
            'password',
            'passwordConfirm',
            'token',
            'authTokenId',
            'authLogRecordId',
            'ipAddress',
            'defaultTeam',
            'acceptanceStatus',
            'sendAccessInfo'
        ];

        foreach ($systemFields as $field) {
            unset($data['entityDefs']['UserProfile']['fields'][$field]);
        }
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

            $relationshipEntity = 'UserFollowed' . $scope;

            $data['entityDefs'][$scope]['fields']['followers'] = [
                'type'   => 'linkMultiple',
                'noLoad' => true
            ];

            $data['entityDefs'][$scope]['links']['followers'] = [
                'type'         => 'hasMany',
                'relationName' => $relationshipEntity,
                'foreign'      => 'followed' . Util::pluralize($scope),
                'entity'       => 'User'
            ];

            $data['entityDefs']['User']['fields']['followed' . Util::pluralize($scope)] = [
                'type'   => 'linkMultiple',
                'noLoad' => true
            ];

            $data['entityDefs']['User']['links']['followed' . Util::pluralize($scope)] = [
                'type'         => 'hasMany',
                'relationName' => $relationshipEntity,
                'foreign'      => 'followers',
                'entity'       => $scope
            ];

            $data['scopes'][$relationshipEntity]['acl'] = false;
            $data['scopes'][$relationshipEntity]['streamDisabled'] = true;
            $data['scopes'][$relationshipEntity]['matchingDisabled'] = true;
            $data['scopes'][$relationshipEntity]['selectionDisabled'] = true;
        }
    }

    protected function prepareEntityFields(array &$data): void
    {
        $visible = [];
        $required = [];
        $readOnly = [];

        foreach ($data['fields'] ?? [] as $type => $defs) {
            if (empty($defs['params'])) {
                continue;
            }

            foreach ($defs['params'] as $item) {
                if (empty($item['name']) || !empty($item['hidden'])) {
                    continue;
                }

                $visible[$item['name']][] = $type;

                if (!empty($item['required'])) {
                    $required[$item['name']][] = $type;
                }

                if (!empty($item['readOnly'])) {
                    $readOnly[$item['name']][] = $type;
                }
            }
        }

        foreach ($visible as $field => $types) {
            $data['entityDefs']['EntityField']['fields'][$field]['conditionalProperties']['visible']['conditionGroup'][] = [
                'type'      => 'in',
                'attribute' => 'type',
                'value'     => $types,
            ];
        }

        foreach ($required as $field => $types) {
            $data['entityDefs']['EntityField']['fields'][$field]['conditionalProperties']['required']['conditionGroup'][] = [
                'type'      => 'in',
                'attribute' => 'type',
                'value'     => $types,
            ];
        }

        foreach ($readOnly as $field => $types) {
            $data['entityDefs']['EntityField']['fields'][$field]['conditionalProperties']['readOnly']['conditionGroup'][] = [
                'type'      => 'in',
                'attribute' => 'type',
                'value'     => $types,
            ];
        }

        $data['entityDefs']['EntityField']['fields']['isMultilang']['conditionalProperties']['visible']['conditionGroup'][] = [
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

                if (!empty($fieldDefs['type']) && $fieldDefs['type'] === 'enum' && empty($fieldDefs['optionColors']) && empty($fieldDefs['view'])) {
                    $data['entityDefs'][$entityType]['fields'][$field]['view'] = "views/fields/enum";
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
            $connection = $this->getConnection();
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
            $params = [
                'id'      => $action['id'],
                'name'    => $action['name'],
                'display' => $action['display'],
                'type'    => $action['type'],
                'acl'     => [
                    'scope'  => $action['source_entity'],
                    'action' => 'read',
                ],
            ];

            if (in_array($action['type'], ['update', 'create'])) {
                $params['acl'] = [
                    'scope'  => $action['target_entity'],
                    'action' => 'edit',
                ];
            } elseif ($action['type'] == 'delete') {
                $params['acl'] = [
                    'scope'  => $action['target_entity'],
                    'action' => 'delete',
                ];
            }

            if (!empty($action['icon_class']) && !empty($data['app']['systemIcons'][$action['icon_class']]['path'])) {
                $html = '<img src="'.  $data['app']['systemIcons'][$action['icon_class']]['path'] .'" class="icon-button" >';
                if (empty($action['hide_text_label'])) {
                    $html .= ' ' . $action['name'];
                }else{
                    $params['tooltip'] = $action['name'];
                }
                $params['html'] = $html;
            }

            if ($action['type'] === 'email') {
                $actionData = @json_decode($action['data'], true);
                $params = array_merge($params, [
                    'showEmailPreview' => !empty($actionData['field']['showEmailPreview']),
                    'emailTemplateId'  => $actionData['field']['emailTemplateId'] ?? '',
                ]);
            }

            if ($action['usage'] === 'entity' && !empty($action['source_entity'])) {
                $data['clientDefs'][$action['source_entity']]['dynamicEntityActions'][] = $params;
            }

            if ($action['usage'] === 'record' && !empty($action['source_entity'])) {
                $data['clientDefs'][$action['source_entity']]['dynamicRecordActions'][] = array_merge($params, [
                    'massAction' => !empty($action['mass_action']),
                ]);
            }

            if ($action['usage'] === 'field' && !empty($action['source_entity']) && !empty($action['display_field'])) {
                $data['clientDefs'][$action['source_entity']]['dynamicFieldActions'][] = array_merge($params, [
                    'displayField' => $action['display_field'],
                    'massAction'   => !empty($action['mass_action']),
                ]);
            }

            if ($action['usage'] === 'onFieldFocus') {
                $data['clientDefs'][$action['source_entity']]['dynamicOnFieldFocusActions'][] = array_merge($params, [
                    'focusField' => $action['focus_field'],
                ]);
            }

            if ($action['usage'] === 'onRecordLoad') {
                $data['clientDefs'][$action['source_entity']]['dynamicOnRecordLoadActions'][] = $params;
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
                    $data['entityDefs'][$entityType]['fields'][$field]['readOnly'] = true;
                    $data['entityDefs'][$entityType]['fields'][$field]['importDisabled'] = true;
                    $data['entityDefs'][$entityType]['fields'][$field]['massUpdateDisabled'] = true;

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
                    $notStorable = !empty($data['entityDefs'][$entityType]['fields'][$field . 'From']['notStorable']);
                } else {
                    $notStorable = !empty($fieldDefs['notStorable']);
                }

                $unitFieldName = $field . 'Unit';
                $data['entityDefs'][$entityType]['fields'][$unitFieldName] = [
                    "type"        => "link",
                    "view"        => "views/fields/unit-link",
                    "measureId"   => $fieldDefs['measureId'],
                    "unitIdField" => true,
                    "mainField"   => $field,
                    "required"    => !empty($fieldDefs['required']),
                    "notStorable" => $notStorable,
                    "emHidden"    => true
                ];

                if (isset($fieldDefs['multilangLocale'])) {
                    $data['entityDefs'][$entityType]['fields'][$unitFieldName]['multilangLocale'] = $fieldDefs['multilangLocale'];
                }

                $data['entityDefs'][$entityType]['links'][$unitFieldName] = [
                    "type"                        => "belongsTo",
                    "entity"                      => "Unit",
                    "skipOrmDefs"                 => $notStorable,
                    'layoutRelationshipsDisabled' => true,
                ];

                if ($visibleLogic = $this->getMetadata()->get([
                    'entityDefs',
                    $entityType,
                    'fields',
                    $field,
                    'conditionalProperties',
                    'visible'
                ])) {
                    $data['entityDefs'][$entityType]['fields'][$unitFieldName]['conditionalProperties']['visible'] = $visibleLogic;
                }

                if (($readOnly = $this->getMetadata()->get([
                    'entityDefs',
                    $entityType,
                    'fields',
                    $field,
                    'conditionalProperties',
                    'readOnly'
                ]))) {
                    $data['entityDefs'][$entityType]['fields'][$unitFieldName]['conditionalProperties']['readOnly'] = $readOnly;
                }

                if ($requireLogic = $this->getMetadata()->get([
                    'entityDefs',
                    $entityType,
                    'fields',
                    $field,
                    'conditionalProperties',
                    'required'
                ])) {
                    $data['entityDefs'][$entityType]['fields'][$unitFieldName]['conditionalProperties']['required'] = $requireLogic;
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
                        "importDisabled"     => true,
                        "emHidden"           => true
                    ];

                    if ($visibleLogic = $this->getMetadata()->get([
                        'entityDefs',
                        $entityType,
                        'fields',
                        $field,
                        'conditionalProperties',
                        'visible'
                    ])) {
                        $data['entityDefs'][$entityType]['fields'][$virtualFieldName]['conditionalProperties']['visible'] = $visibleLogic;
                    }

                    if (($readOnly = $this->getMetadata()->get([
                        'entityDefs',
                        $entityType,
                        'fields',
                        $field,
                        'conditionalProperties',
                        'readOnly'
                    ]))) {
                        $data['entityDefs'][$entityType]['fields'][$virtualFieldName]['conditionalProperties']['readOnly'] = $readOnly;
                    }

                    if ($requireLogic = $this->getMetadata()->get([
                        'entityDefs',
                        $entityType,
                        'fields',
                        $field,
                        'conditionalProperties',
                        'required'
                    ])) {
                        $data['entityDefs'][$entityType]['fields'][$virtualFieldName]['conditionalProperties']['required'] = $requireLogic;
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

                if (!empty($data['entityDefs'][$entity]['fields'][$field]['conditionalProperties'])) {
                    $data['entityDefs'][$entity]['fields'][$fieldFrom]['conditionalProperties'] = $data['entityDefs'][$entity]['fields'][$field]['conditionalProperties'];
                    $data['entityDefs'][$entity]['fields'][$fieldTo]['conditionalProperties'] = $data['entityDefs'][$entity]['fields'][$field]['conditionalProperties'];
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

                $ignoredEntity = (!empty($data['scopes'][$entityName]['associatesForEntity'])) || strpos($entityName, 'Hierarchy') !== false;

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
                                    'type'                      => 'linkMultiple',
                                    'linkToRelationEntity'      => $relationParams['entity'],
                                    'layoutDetailDisabled'      => true,
                                    'layoutLeftSidebarDisabled' => true,
                                    'massUpdateDisabled'        => true,
                                    'noLoad'                    => true
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
                                'type'                      => 'linkMultiple',
                                'linkToRelationEntity'      => $scope,
                                'layoutLeftSidebarDisabled' => true,
                                'layoutDetailDisabled'      => true,
                                'massUpdateDisabled'        => true,
                                'noLoad'                    => true
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

        $defaultEntityDefs['fields']['created'] = [
            'type'                => 'datetime',
            'view'                => 'views/fields/created-at-with-user',
            'notStorable'         => true,
            'readOnly'            => true,
            'ignoreViewForSearch' => true,
            "massUpdateDisabled"  => true,
            "filterDisabled"      => true,
            "exportDisabled"      => true,
            "importDisabled"      => true,
            "emHidden"            => true
        ];

        $defaultEntityDefs['fields']['modified'] = [
            'type'                => 'datetime',
            'view'                => 'views/fields/modified-at-with-user',
            'notStorable'         => true,
            'readOnly'            => true,
            'ignoreViewForSearch' => true,
            "massUpdateDisabled"  => true,
            "filterDisabled"      => true,
            "exportDisabled"      => true,
            "importDisabled"      => true,
            "emHidden"            => true
        ];

        foreach ($res as $entityName => $entityDefs) {
            $current = $data['clientDefs'][$entityName] ?? [];
            $data['clientDefs'][$entityName] = empty($current) ? $defaultClientDefs : Util::merge($defaultClientDefs,
                $current);

            $current = $data['entityDefs'][$entityName] ?? [];
            $current = empty($current) ? $entityDefs : Util::merge($entityDefs, $current);

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
            if (!isset($data['scopes'][$entityName]['layouts'])) {
                $data['scopes'][$entityName]['layouts'] = true;
            }
            $data['scopes'][$entityName]['customizable'] = false;
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
            $data['entityDefs'][$scope]['fields']['parents']['layoutLeftSidebarDisabled'] = true;
            $data['entityDefs'][$scope]['fields']['children']['layoutLeftSidebarDisabled'] = true;

            $data['entityDefs'][$scope]['fields']['routes'] = [
                "type"               => "jsonArray",
                "view"               => "views/fields/hierarchy-routes",
                "protected"          => true,
                "massUpdateDisabled" => true,
                "filterDisabled"     => true,
                "importDisabled"     => true,
                "emHidden"           => true,
            ];

            if (
                $this->getConfig()->get('isInstalled')
                && $this->getConfig()->get('database')['driver'] === 'pdo_pgsql'
            ) {
                $data['entityDefs'][$scope]['indexes']['routes'] = ['columns' => ['routes', 'deleted']];
            }

            $data['entityDefs'][$scope]['fields']['routesNames'] = [
                "type"                 => "jsonArray",
                "notStorable"          => true,
                "layoutListDisabled"   => true,
                "layoutDetailDisabled" => true,
                "massUpdateDisabled"   => true,
                "filterDisabled"       => true,
                "importDisabled"       => true,
                "exportDisabled"       => true,
                "emHidden"             => true,
            ];

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
                    "type"                      => "link",
                    "notStorable"               => true,
                    "entity"                    => $scope,
                    "emHidden"                  => true,
                    "layoutLeftSidebarDisabled" => true,
                    "exportDisabled"            => false,
                    "importDisabled"            => false
                ];

                $data['entityDefs'][$scope]['fields']['parents'] = array_merge($data['entityDefs'][$scope]['fields']['parents'],
                    [
                        "layoutListDisabled"        => true,
                        "layoutDetailDisabled"      => true,
                        "layoutLeftSidebarDisabled" => true,
                        "massUpdateDisabled"        => true,
                        "filterDisabled"            => true,
                        "importDisabled"            => true,
                        "emHidden"                  => true
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
                    continue;
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
                        $mParams['required'] = false;
                        $mParams['unique'] = false;
                        if (in_array($mParams['type'], ['enum', 'multiEnum'])) {
                            $mParams['notStorable'] = true;
                            $mParams['optionsOriginal'] = $params['options'];
                            if (!empty($mParams['options' . $preparedLocale])) {
                                $mParams['options'] = $mParams['options' . $preparedLocale];
                            }
                            $mParams['emHidden'] = true;
                        }
                        if ($mParams['type'] == 'script' && isset($mParams['script' . $preparedLocale])) {
                            $mParams['script'] = $mParams['script' . $preparedLocale];
                        }

                        if (isset($data['entityDefs'][$scope]['fields'][$mField])) {
                            $mParams = array_merge($mParams, $data['entityDefs'][$scope]['fields'][$mField]);
                            $toSkip[] = $mField;
                        }

                        foreach (['default', 'defaultValueType'] as $key) {
                            if (array_key_exists($key, $mParams)) {
                                unset($mParams[$key]);
                            }
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

    protected function addAttributesToEntity(array &$metadata): void
    {
        if (empty($metadata['scopes']['Attribute']['type'])) {
            return;
        }

        foreach ($metadata['scopes'] ?? [] as $scope => $scopeDefs) {
            if (!empty($scopeDefs['hasAttribute'])) {
                $entityName = "{$scope}AttributeValue";

                $metadata['scopes'][$entityName] = [
                    'type'              => "Base",
                    'attributeValueFor' => $scope,
                    'entity'            => false,
                    'layouts'           => false,
                    'tab'               => false,
                    'acl'               => false,
                    'customizable'      => false,
                    'importable'        => false,
                    'notifications'     => false,
                    'disabled'          => false,
                    'object'            => false,
                    'streamDisabled'    => true,
                    'hideLastViewed'    => true,
                    'emHidden'          => true,
                ];

                $metadata["entityDefs"][$scope]['fields']["attributesDefs"] = [
                    "type"                        => "jsonObject",
                    "notStorable"                 => true,
                    "layoutDetailDisabled"        => true,
                    "layoutListDisabled"          => true,
                    "layoutRelationshipsDisabled" => true,
                    "layoutLeftSidebarDisabled"   => true,
                    "massUpdateDisabled"          => true,
                    "importDisabled"              => true,
                    "exportDisabled"              => true,
                    "emHidden"                    => true,
                ];

                $metadata["entityDefs"][$scope]['fields'][lcfirst($scope) . "AttributeValues"] = [
                    "type"                        => "linkMultiple",
                    "layoutDetailDisabled"        => true,
                    "layoutListDisabled"          => true,
                    "layoutRelationshipsDisabled" => true,
                    "layoutLeftSidebarDisabled"   => true,
                    "massUpdateDisabled"          => true,
                    "importDisabled"              => true,
                    "exportDisabled"              => true,
                    "noLoad"                      => true
                ];

                $metadata["entityDefs"][$scope]['links'][lcfirst($scope) . "AttributeValues"] = [
                    "type"                        => "hasMany",
                    "foreign"                     => lcfirst($scope),
                    "layoutRelationshipsDisabled" => true,
                    "entity"                      => "{$scope}AttributeValue",
                    "disableMassRelation"         => true
                ];

                $metadata["entityDefs"][$entityName] = [
                    "fields"        => [
                        lcfirst($scope)  => [
                            "type"     => "link",
                            "required" => true
                        ],
                        "attribute"      => [
                            "type"     => "link",
                            "required" => true
                        ],
                        "boolValue"      => [
                            "type"    => "bool",
                            "notNull" => false
                        ],
                        "dateValue"      => [
                            "type" => "date"
                        ],
                        "datetimeValue"  => [
                            "type" => "datetime"
                        ],
                        "intValue"       => [
                            "type" => "int"
                        ],
                        "intValue1"      => [
                            "type" => "int"
                        ],
                        "floatValue"     => [
                            "type" => "float"
                        ],
                        "floatValue1"    => [
                            "type" => "float"
                        ],
                        "varcharValue"   => [
                            "type"        => "varchar",
                            "isMultilang" => true
                        ],
                        "textValue"      => [
                            "type"        => "text",
                            "isMultilang" => true
                        ],
                        "referenceValue" => [
                            "type"      => "varchar",
                            "maxLength" => 50
                        ],
                        "jsonValue"      => [
                            "type" => "jsonObject"
                        ]
                    ],
                    "links"         => [
                        lcfirst($scope) => [
                            "type"     => "belongsTo",
                            "entity"   => $scope,
                            "foreign"  => lcfirst($scope) . "AttributeValues",
                            "emHidden" => true
                        ],
                        "attribute"     => [
                            "type"     => "belongsTo",
                            "entity"   => "Attribute",
                            "emHidden" => true
                        ]
                    ],
                    "uniqueIndexes" => [
                        "unique_relationship" => [
                            "deleted",
                            Util::toUnderScore(lcfirst($scope)) . "_id",
                            "attribute_id"
                        ]
                    ],
                    "indexes"       => [
                        "boolValue"      => [
                            "columns" => [
                                "boolValue",
                                "deleted"
                            ]
                        ],
                        "dateValue"      => [
                            "columns" => [
                                "dateValue",
                                "deleted"
                            ]
                        ],
                        "datetimeValue"  => [
                            "columns" => [
                                "datetimeValue",
                                "deleted"
                            ]
                        ],
                        "intValue"       => [
                            "columns" => [
                                "intValue",
                                "deleted"
                            ]
                        ],
                        "intValue1"      => [
                            "columns" => [
                                "intValue1",
                                "deleted"
                            ]
                        ],
                        "floatValue"     => [
                            "columns" => [
                                "floatValue",
                                "deleted"
                            ]
                        ],
                        "floatValue1"    => [
                            "columns" => [
                                "floatValue1",
                                "deleted"
                            ]
                        ],
                        "varcharValue"   => [
                            "columns" => [
                                "varcharValue",
                                "deleted"
                            ]
                        ],
                        "textValue"      => [
                            "columns" => [
                                "textValue",
                                "deleted"
                            ]
                        ],
                        "referenceValue" => [
                            "columns" => [
                                "referenceValue",
                                "deleted"
                            ]
                        ]
                    ],
                    "collection"    => [
                        "sortBy"           => "id",
                        "asc"              => false,
                        "textFilterFields" => []
                    ]
                ];
            }
        }
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

            if (empty($data['entityDefs'][$scope]['fields'])) {
                continue;
            }

            $data['entityDefs'][$scope]['fields']['created'] = [
                'type'                => 'datetime',
                'view'                => 'views/fields/created-at-with-user',
                'notStorable'         => true,
                'readOnly'            => true,
                'ignoreViewForSearch' => true,
                "massUpdateDisabled"  => true,
                "filterDisabled"      => true,
                "exportDisabled"      => true,
                "importDisabled"      => true,
                "emHidden"            => true
            ];

            $data['entityDefs'][$scope]['fields']['modified'] = [
                'type'                => 'datetime',
                'view'                => 'views/fields/modified-at-with-user',
                'notStorable'         => true,
                'readOnly'            => true,
                'ignoreViewForSearch' => true,
                "massUpdateDisabled"  => true,
                "filterDisabled"      => true,
                "exportDisabled"      => true,
                "importDisabled"      => true,
                "emHidden"            => true
            ];

            foreach ($data['entityDefs'][$scope]['fields'] as $field => $fieldDefs) {
                if (
                    !empty($fieldDefs['type']) && $fieldDefs['type'] === 'link'
                    && !empty($data['entityDefs'][$scope]['links'][$field]['entity'])
                    && $data['entityDefs'][$scope]['links'][$field]['entity'] === 'User'
                    && empty($data['entityDefs'][$scope]['links'][$field]['view'])
                ) {
                    $data['entityDefs'][$scope]['fields'][$field]['view'] = 'views/fields/user-with-avatar';
                }

                if (
                    in_array($field, ['createdAt', 'modifiedAt'])
                    && !empty($data['entityDefs'][$scope]['fields'][$field]['showUser'])
                    && empty($data['entityDefs'][$scope]['fields'][$field]['view'])
                ) {
                    $data['entityDefs'][$scope]['fields'][$field]['view'] = 'views/fields/datetime-with-user';
                    $data['entityDefs'][$scope]['fields'][$field]['ignoreViewForSearch'] = true;
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
                $previewTemplates = $this->getConnection()->createQueryBuilder()
                    ->select('id, name, entity_type, data')
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
            $data['clientDefs'][$previewTemplate['entity_type']]['dynamicRecordActions'][] = [
                'id'             => $previewTemplate['id'],
                'name'           => $previewTemplate['name'],
                'type'           => 'previewTemplate',
                'display'        => 'single',
                'actionViewPath' => 'views/preview-template/record/actions/preview',
                'action'         => 'showHtmlPreview',
                'optionsToPass'  => [
                    'model'
                ],
                'acl'            => [
                    'scope'  => $previewTemplate['entity_type'],
                    'action' => 'read',
                ],
                'data'           => @json_decode($previewTemplate['data'] ?? '')
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
                "type"                  => "varchar",
                "virtualField"          => true,
                "notStorable"           => true,
                "filterDisabled"        => true,
                "view"                  => "views/notification-rule/fields/notification-template",
                "name"                  => $transport . 'Template',
                "t_type"                => $transport,
                "conditionalProperties" => [
                    "required" => [
                        "conditionGroup" => [
                            [
                                "type"      => "isTrue",
                                "attribute" => $transport . 'Active',
                            ],
                        ],
                    ],
                ],
            ];
            $data['entityDefs']['NotificationRule']['fields'][$transport . 'TemplateName'] = [
                "type"           => "varchar",
                "filterDisabled" => true,
                "readOnly"       => true,
                "notStorable"    => true
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
            $connection = $this->getConnection();
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
                        $users[$notificationProfileId] = NotificationRule::getNotificationProfileUsers($notificationProfileId,
                            $this->getConfig(), $this->getConnection());

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

    protected function addAssociateToEntity(array &$data): void
    {
        foreach ($data['scopes'] ?? [] as $scope => $scopeDefs) {
            if (!empty($scopeDefs['hasAssociate'])) {
                // relation table
                $relationName = "Associated$scope";

                $data['scopes'][$relationName]['associatesForEntity'] = $scope;

                $defs = [
                    "fields"        => [
                        "association"             => [
                            "type"            => "link",
                            "required"        => true,
                            "view"            => "views/associated-record/fields/association",
                            "tooltip"         => true,
                            "additionalField" => true,
                        ],
                        "associatingItem"         => [
                            "required"      => true,
                            "type"          => "link",
                            "relationField" => true,
                            "view"          => "views/associated-record/fields/associating-item"
                        ],
                        "associatedItem"          => [
                            "required"      => true,
                            "type"          => "link",
                            "relationField" => true,
                            "view"          => "views/associated-record/fields/associated-item",
                        ],
                        "associatedItems"         => [
                            "type"                      => "linkMultiple",
                            "entity"                    => $scope,
                            "view"                      => "views/associated-record/fields/associated-items",
                            "noLoad"                    => true,
                            "notStorable"               => true,
                            "layoutListDisabled"        => true,
                            "layoutListSmallDisabled"   => true,
                            "layoutDetailDisabled"      => true,
                            "layoutDetailSmallDisabled" => true,
                            "layoutMassUpdateDisabled"  => true,
                            "filterDisabled"            => true,
                            "exportDisabled"            => true,
                            "importDisabled"            => true,
                            "emHidden"                  => true
                        ],
                        "reverseAssociation"      => [
                            "type"           => "link",
                            "notStorable"    => true,
                            "entity"         => "Association",
                            "view"           => "views/associated-record/fields/reverse-association",
                            "filterDisabled" => true
                        ],
                        "reverseAssociated$scope" => [
                            "type"                      => "link",
                            "readOnly"                  => true,
                            "layoutListDisabled"        => true,
                            "layoutListSmallDisabled"   => true,
                            "layoutDetailDisabled"      => true,
                            "layoutDetailSmallDisabled" => true,
                            "layoutMassUpdateDisabled"  => true,
                            "filterDisabled"            => true,
                            "exportDisabled"            => true,
                            "importDisabled"            => true,
                            "emHidden"                  => true
                        ],
                        "associateEverything"     => [
                            "type"                     => "bool",
                            "view"                     => "views/associated-record/fields/associate-everything",
                            "notStorable"              => true,
                            "layoutListDisabled"       => true,
                            "layoutListSmallDisabled"  => true,
                            "layoutMassUpdateDisabled" => true,
                            "filterDisabled"           => true,
                            "exportDisabled"           => true,
                            "importDisabled"           => true,
                            "emHidden"                 => true
                        ],
                        "sorting"                 => [
                            "type"            => "int",
                            "additionalField" => true
                        ]
                    ],
                    "links"         => [
                        "association"             => [
                            "type"   => "belongsTo",
                            "entity" => "Association"
                        ],
                        "associatingItem"         => [
                            "type"    => "belongsTo",
                            "foreign" => "associatedItemRelation",
                            "entity"  => $scope
                        ],
                        "associatedItem"          => [
                            "type"    => "belongsTo",
                            "foreign" => "associatingItemRelation",
                            "entity"  => $scope
                        ],
                        "reverseAssociated$scope" => [
                            "type"   => "belongsTo",
                            "entity" => $relationName,
                        ]
                    ],
                    "uniqueIndexes" => [
                        "unique_relation" => [
                            "deleted",
                            "association_id",
                            "associating_item_id",
                            "associated_item_id"
                        ]
                    ]
                ];
                $data['entityDefs'][$relationName] = Util::merge($data['entityDefs'][$relationName] ?? [], $defs);

                $data['clientDefs'][$relationName] = array_merge($data['clientDefs'][$relationName] ?? [], [
                    "quickCreate"           => true,
                    "modalFullFormDisabled" => true,
                    "quickCreateOptions"    => [
                        "fullFormDisabled" => true
                    ],
                    "iconClass"             => "package",
                    "disabledMassActions"   => [
                        "merge"
                    ],
                    "boolFilterList"        => [
                        "onlyMy"
                    ],
                    "recordViews"           => [
                        'editSmall' => 'views/associated-record/record/edit-small'
                    ]
                ]);

                $additionalScopeDefs = [
                    "fields" => [
                        "associatedItemRelations"  => [
                            "type"                      => "linkMultiple",
                            "layoutDetailDisabled"      => true,
                            "layoutListDisabled"        => true,
                            "layoutLeftSidebarDisabled" => true,
                            "massUpdateDisabled"        => true,
                            "filterDisabled"            => false,
                            "noLoad"                    => true,
                            "importDisabled"            => true,
                            "exportDisabled"            => false
                        ],
                        "associatingItemRelations" => [
                            "type"                      => "linkMultiple",
                            "layoutDetailDisabled"      => true,
                            "layoutListDisabled"        => true,
                            "layoutLeftSidebarDisabled" => true,
                            "massUpdateDisabled"        => true,
                            "filterDisabled"            => false,
                            "noLoad"                    => true,
                            "exportDisabled"            => false,
                            "importDisabled"            => true
                        ],
                        "associatedItems"          => [
                            "type"                      => "linkMultiple",
                            "layoutDetailDisabled"      => true,
                            "layoutListDisabled"        => true,
                            "layoutLeftSidebarDisabled" => true,
                            "massUpdateDisabled"        => true,
                            "filterDisabled"            => false,
                            "noLoad"                    => true,
                            "importDisabled"            => true,
                            "exportDisabled"            => false
                        ],
                        "associatingItems"         => [
                            "type"                      => "linkMultiple",
                            "layoutDetailDisabled"      => true,
                            "layoutListDisabled"        => true,
                            "layoutLeftSidebarDisabled" => true,
                            "massUpdateDisabled"        => true,
                            "filterDisabled"            => false,
                            "noLoad"                    => true,
                            "exportDisabled"            => false,
                            "importDisabled"            => true
                        ]
                    ],
                    "links"  => [
                        "associatedItems"          => [
                            "type"                => "hasMany",
                            "relationName"        => $relationName,
                            "entity"              => $scope,
                            "isAssociateRelation" => true,
                            "midKeys"             => [
                                "associatingItemId",
                                "associatedItemId"
                            ],
                            "disableMassRelation" => true
                        ],
                        "associatingItems"         => [
                            "type"                        => "hasMany",
                            "relationName"                => $relationName,
                            "entity"                      => $scope,
                            "layoutRelationshipsDisabled" => false,
                            "midKeys"                     => [
                                "associatedItemId",
                                "associatingItemId"
                            ],
                            "disableMassRelation"         => true
                        ],
                        "associatedItemRelations"  => [
                            "type"                        => "hasMany",
                            "foreign"                     => "associatingItem",
                            "entity"                      => $relationName,
                            "layoutRelationshipsDisabled" => true,
                            "isMainAssociateRelation"     => true,
                            "addRelationCustomDefs"       => [
                                "link"   => "associatedItems",
                                "entity" => $scope
                            ]
                        ],
                        "associatingItemRelations" => [
                            "type"                        => "hasMany",
                            "foreign"                     => "associatedItem",
                            "entity"                      => $relationName,
                            "layoutRelationshipsDisabled" => true,
                            "isRelatedAssociateRelation"  => true,
                            "disableMassRelation"         => true
                        ]
                    ]
                ];

                $data['entityDefs'][$scope] = Util::merge($data['entityDefs'][$scope] ?? [], $additionalScopeDefs);

                $data['clientDefs'][$scope]['relationshipPanels']["associatedItems"] = array_merge($data['clientDefs'][$scope]['relationshipPanels']["associatedItems"] ?? [], [
                    "view" => "views/record/panels/associated-records"
                ]);

                $data['clientDefs'][$scope]['relationshipPanels']["associatingItems"] = array_merge($data['clientDefs'][$scope]['relationshipPanels']["associatingItems"] ?? [], [
                    "view"   => "views/record/panels/related-records",
                    "create" => false,
                ]);
            }
        }
    }

    protected function prepareClassificationAttributeMetadata(array $data): array
    {
        // is multi-lang activated
        if (empty($this->getConfig()->get('isMultilangActive'))) {
            return $data;
        }

        // get locales
        if (empty($locales = $this->getConfig()->get('inputLanguageList', []))) {
            return $data;
        }

        foreach ($locales as $locale) {
            $camelCaseLocale = ucfirst(Util::toCamelCase(strtolower($locale)));
            $data['entityDefs']['ClassificationAttribute']['fields']["attributeName$camelCaseLocale"] = [
                "type"                      => "varchar",
                "notStorable"               => true,
                "default"                   => null,
                "layoutListDisabled"        => true,
                "layoutListSmallDisabled"   => true,
                "layoutDetailDisabled"      => true,
                "layoutDetailSmallDisabled" => true,
                "massUpdateDisabled"        => true,
                "filterDisabled"            => true,
                "importDisabled"            => true,
                "emHidden"                  => true
            ];
        }

        return $data;
    }

    protected function addClassificationToEntity(array &$data): void
    {
        foreach ($data['scopes'] ?? [] as $scope => $scopeDefs) {
            if (!empty($scopeDefs['hasAttribute']) && !empty($scopeDefs['hasClassification'])) {
                if (empty($data['entityDefs'][$scope]['fields']['classifications'])) {
                    $data['entityDefs'][$scope]['fields']['classifications'] = [];
                }
                $data['entityDefs'][$scope]['fields']['classifications']['type'] = "linkMultiple";
                $data['entityDefs'][$scope]['fields']['classifications']['view'] = "views/fields/classifications";
                $data['entityDefs'][$scope]['fields']['classifications']['customizable'] = false;

                $data['entityDefs'][$scope]['links']['classifications'] = [
                    "type"         => "hasMany",
                    "foreign"      => Util::pluralize(lcfirst($scope)),
                    "relationName" => "{$scope}Classification",
                    "entity"       => "Classification"
                ];

                if (!empty($scopeDefs['singleClassification'])) {
                    $data['entityDefs'][$scope]['fields']['classifications']['view'] = 'views/fields/classifications-single';
                    $data['entityDefs'][$scope]['fields']['classifications']['ignoreTypeForMerge'] = true;
                    $data['entityDefs'][$scope]['links']['classifications']['layoutRelationshipsDisabled'] = true;
                    $data['entityDefs'][$scope]['links']['classifications']['notMergeable'] = true;
                } else {
                    $data['clientDefs'][$scope]['boolFilterList'][] = 'multipleClassifications';
                }

                $data['entityDefs']['Classification']['fields'][Util::pluralize(lcfirst($scope))] = [
                    "type" => "linkMultiple"
                ];

                $data['entityDefs']['Classification']['links'][Util::pluralize(lcfirst($scope))] = [
                    "type"         => "hasMany",
                    "foreign"      => 'classifications',
                    "relationName" => "{$scope}Classification",
                    "entity"       => "$scope"
                ];

                $data['scopes']["{$scope}Classification"]['classificationForEntity'] = $scope;
            }
        }
    }

    protected function prepareDerivatives(array &$data): void
    {
        if (!$this->getConfig()->get('isInstalled', false)) {
            return;
        }

        foreach ($data['scopes'] ?? [] as $scope => $scopeDefs) {
            if (empty($scopeDefs['type']) || $scopeDefs['type'] !== 'Derivative') {
                continue;
            }

            $primaryEntity = $scopeDefs['primaryEntityId'];

            // clone entity defs
            foreach ($data['entityDefs'][$primaryEntity]['fields'] ?? [] as $fieldName => $fieldDefs) {
                if (empty($fieldDefs['type'])) {
                    continue;
                }

                if ($fieldDefs['type'] === 'linkMultiple') {
                    continue;
                }

                // disable unique indexes
                if (!empty($fieldDefs['unique'])) {
                    $fieldDefs['unique'] = false;
                }

                if ($fieldDefs['type'] === 'link') {
                    $linkDefs = $data['entityDefs'][$primaryEntity]['links'][$fieldName] ?? null;
                    if (!empty($linkDefs['foreign'])) {
                        unset($linkDefs['foreign']);
                    }
                    $data['entityDefs'][$scope]['links'][$fieldName] = $linkDefs;
                }

                $fieldDefs['customizable'] = false;

                $data['entityDefs'][$scope]['fields'][$fieldName] = $fieldDefs;
            }
            if (!empty($data['entityDefs'][$primaryEntity]['indexes'])) {
                $data['entityDefs'][$scope]['indexes'] = $data['entityDefs'][$primaryEntity]['indexes'];
            }
            if (!empty($data['entityDefs'][$primaryEntity]['collection'])) {
                $data['entityDefs'][$scope]['collection'] = $data['entityDefs'][$primaryEntity]['collection'];
            }

            // clone scope defs
            $data['scopes'][$scope] = array_merge($data['scopes'][$primaryEntity], [
                'type'            => 'Derivative',
                'primaryEntityId' => $primaryEntity,
                'layouts'         => false
            ]);

            // add link to the primary entity
            $data['entityDefs'][$scope]['fields']['primaryRecord'] = [
                'type'     => 'link',
                'required' => true
            ];
            $data['entityDefs'][$scope]['links']['primaryRecord'] = [
                'type'    => 'belongsTo',
                'foreign' => 'derivedRecords',
                'entity'  => $primaryEntity
            ];

            $data['entityDefs'][$primaryEntity]['fields']['derivedRecords'] = [
                'type'   => 'linkMultiple',
                'noLoad' => true
            ];
            $data['entityDefs'][$primaryEntity]['links']['derivedRecords'] = [
                'type'    => 'hasMany',
                'foreign' => 'primaryRecord',
                'entity'  => $scope
            ];
        }
    }

    protected function prepareMetadataViaMatchings(array &$data): void
    {
        if (!$this->getConfig()->get('isInstalled', false)) {
            return;
        }

        return;

        foreach ($this->getConfig()->get('referenceData.Matching') ?? [] as $code => $matching) {
            if (empty($matching['type'])) {
                continue;
            }

            if ($matching['type'] === 'masterRecord') {
                $sourceRecords = 'sourceRecords'.$matching['sourceEntity'];

                $data['entityDefs'][$matching['sourceEntity']]['fields']['goldenRecord'] = [
                    'type'         => 'link',
                    'customizable' => false,
                ];
                $data['entityDefs'][$matching['sourceEntity']]['links']['goldenRecord'] = [
                    'type'    => 'belongsTo',
                    'foreign' => $sourceRecords,
                    'entity'  => $matching['masterEntity'],
                ];

                $data['entityDefs'][$matching['masterEntity']]['fields'][$sourceRecords] = [
                    'type'         => 'linkMultiple',
                    'noLoad'       => true,
                    'customizable' => false,
                ];

                $data['entityDefs'][$matching['masterEntity']]['links'][$sourceRecords] = [
                    'type'    => 'hasMany',
                    'foreign' => 'goldenRecord',
                    'entity'  => $matching['sourceEntity'],
                ];
            }
        }

        // set matching rules types
        foreach ($data['entityDefs']['MatchingRule']['fields']['type']['options'] ?? [] as $type) {
            $className = "\\Atro\\Core\\MatchingRuleType\\" . ucfirst($type);
            if (!class_exists($className)) {
                continue;
            }

            $data['app']['matchingRules'][$type] = [
                'fieldTypes' => $className::getSupportedFieldTypes(),
            ];
        }

        foreach ($this->getConfig()->get('referenceData')['Matching'] ?? [] as $matching) {
            $fieldName = \Atro\Repositories\Matching::prepareFieldName($matching['code']);
            $data['entityDefs'][$matching['sourceEntity']]['fields'][$fieldName] = [
                'type'                 => 'bool',
                "layoutListDisabled"   => true,
                "layoutDetailDisabled" => true,
                "massUpdateDisabled"   => true,
                "filterDisabled"       => true,
                "importDisabled"       => true,
                "exportDisabled"       => true,
                "emHidden"             => true
            ];

//            if (empty($matching['isActive'])) {
//                continue;
//            }

//            // add right panel
//            foreach (['stagingEntity', 'masterEntity'] as $entityType) {
//                $panels = array_column($data['clientDefs'][$matching[$entityType]]['rightSidePanels'] ?? [], 'name');
//                if (!empty($matching[$entityType]) && !in_array('matchedRecords', $panels)) {
//                    $data['clientDefs'][$matching[$entityType]]['rightSidePanels'][] = [
//                        'name'     => 'matchedRecords',
//                        'label'    => 'matchedRecords',
//                        'view'     => 'views/record/panels/side/matchings',
//                        'aclScope' => 'MatchedRecord',
//                    ];
//                }
//            }
        }
    }

    protected function addThumbnailFieldsByTypesToFile(array &$data): void
    {
        if (empty($data['app'])) {
            return;
        }

        foreach ($data['app']['thumbnailTypes'] ?? [] as $size => $params) {
            $field = File::prepareThumbnailUrlFieldName($size);

            if (empty($data['entityDefs']['File']['fields'][$field])) {
                $data['entityDefs']['File']['fields'][$field] = [
                    'type' => 'varchar',
                    'notStorable' => true,
                    'readOnly' => true,
                    'layoutMassUpdateDisabled' => true,
                    'filterDisabled' => true,
                    'importDisabled' => true,
                    'openApiEnabled' => true
                ];
            }
        }
    }
}
