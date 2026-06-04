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

namespace Atro\Core\AttributeFieldTypes;

use Atro\Core\AttributeFieldConverter;
use Atro\Core\Container;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\IEntity;

class TextType extends AbstractFieldType
{
    protected string $type = 'text';
    protected string $column = 'text_value';

    protected Connection $conn;

    private ?array $cachedLanguages = null;

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->conn = $container->get('connection');
    }

    public function convert(IEntity $entity, array $row, array &$attributesDefs, bool $skipValueProcessing = false): void
    {
        $id            = $row['id'];
        $name          = AttributeFieldConverter::prepareFieldName($row);
        $attributeData = !empty($row['data']) ? @json_decode($row['data'], true)['field'] ?? null : null;

        // Resolve once per attribute — avoids repeated detectLocale + getEntity calls inside prepareKey
        $nameKey      = $this->prepareKey('name', $row);
        $tooltipKey   = $this->prepareKey('tooltip', $row);
        $conditionals = $this->prepareConditionalProperties($row);

        $entity->fields[$name] = [
            'type'                     => $this->type,
            'name'                     => $name,
            'attributeId'              => $id,
            'column'                   => $this->column,
            'required'                 => !empty($row['is_required']),
            'modifiedExtendedDisabled' => !empty($row['modified_extended_disabled'])
        ];

        if (empty($skipValueProcessing)) {
            $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);
        }

        $entity->entityDefs['fields'][$name] = [
            'attributeId'               => $id,
            'attributeValueId'          => $row['av_id'] ?? null,
            'classificationAttributeId' => $row['classification_attribute_id'] ?? null,
            'attributePanelId'          => $row['attribute_panel_id'] ?? null,
            'sortOrder'                 => $row['sort_order'] ?? null,
            'sortOrderInAttributeGroup' => $row['sort_order_in_attribute_group'] ?? null,
            'attributeGroup'            => [
                'id'        => $row['attribute_group_id'] ?? null,
                'name'      => $row['attribute_group_name'] ?? null,
                'sortOrder' => $row['attribute_group_sort_order'] ?? null,
            ],
            'channelId'                 => $row['channel_id'] ?? null,
            'channelName'               => $row['channel_name'] ?? null,
            'type'                      => $this->type,
            'required'                  => !empty($row['is_required']),
            'readOnly'                  => !empty($row['is_read_only']),
            'protected'                 => !empty($row['is_protected']),
            'notNull'                   => !empty($row['not_null']),
            'label'                     => $row[$nameKey],
            'tooltip'                   => !empty($row[$tooltipKey]),
            'tooltipText'               => $row[$tooltipKey],
            'conditionalProperties'     => $conditionals,
            'modifiedExtendedDisabled'  => !empty($row['modified_extended_disabled'])
        ];

        if (!empty($row['disable_field_value_lock'])) {
            $entity->entityDefs['fields'][$name]['disableFieldValueLock'] = true;
        }

        if ($this->type === 'varchar') {
            $entity->entityDefs['fields'][$name]['fullWidth'] = !empty($attributeData['fullWidth']);

            if (!empty($row['pattern'])) {
                $entity->entityDefs['fields'][$name]['pattern'] = $row['pattern'];
            }
        } else {
            $entity->entityDefs['fields'][$name]['fullWidth'] = $attributeData['fullWidth'] ?? true;
        }

        if (!empty($attributeData['maxLength'])) {
            $entity->entityDefs['fields'][$name]['maxLength'] = $attributeData['maxLength'];
        }

        if (!empty($attributeData['countBytesInsteadOfCharacters'])) {
            $entity->entityDefs['fields'][$name]['countBytesInsteadOfCharacters'] = $attributeData['countBytesInsteadOfCharacters'];
        }

        $languages = $this->getLanguageMap();

        if (!empty($row['is_multilang'])) {
            foreach ($languages as $language => $languageName) {
                $lName                  = $name . ucfirst(Util::toCamelCase(strtolower($language)));
                $entity->fields[$lName] = array_merge($entity->fields[$name], [
                    'name'   => $lName,
                    'column' => $this->column . "_" . strtolower($language)
                ]);
                if (empty($skipValueProcessing)) {
                    $entity->set($lName, $row[$entity->fields[$lName]['column']] ?? null);
                }

                $entity->entityDefs['fields'][$lName] = array_merge($entity->entityDefs['fields'][$name], [
                    'name'            => $lName,
                    'label'           => $this->getAttributeLabel($row, $language, $languages),
                    'tooltip'         => !empty($row[$tooltipKey]),
                    'tooltipText'     => $row[$tooltipKey],
                    'multilangField'  => $name,
                    'multilangLocale' => $language,
                ]);

                $attributesDefs[$lName] = $entity->entityDefs['fields'][$lName];
            }
            $entity->entityDefs['fields'][$name]['isMultilang'] = true;
            $entity->entityDefs['fields'][$name]['label']       = $this->getAttributeLabel($row, '', $languages);
        }

        $hasMeasure = $this->type === 'varchar' && isset($row['measure_id']) && empty($row['is_multilang']);
        $hasPrefix  = $this->type === 'varchar' && !empty($row['prefix_enabled']) && empty($row['is_multilang']);

        if ($hasMeasure || $hasPrefix) {
            $entity->entityDefs['fields'][$name]['mainField']        = $name;
            $entity->entityDefs['fields'][$name]['combinedField']    = true;
            $entity->entityDefs['fields'][$name]['layoutDetailView'] = "views/fields/combined-{$this->type}";
            $entity->entityDefs['fields'][$name]['detailViewLabel']  = $entity->entityDefs['fields'][$name]['label'];
            $entity->entityDefs['fields'][$name]['label']            = "{$row[$nameKey]} " . $this->language->translate("{$this->type}Part");
        }

        if ($hasMeasure) {
            $entity->entityDefs['fields'][$name]['measureId'] = $row['measure_id'];

            $entity->fields[$name . 'UnitId']   = [
                'type'        => 'varchar',
                'name'        => $name,
                'attributeId' => $id,
                'column'      => 'reference_value',
                'required'    => !empty($row['is_required'])
            ];
            $entity->fields[$name . 'UnitName'] = [
                'type'        => 'varchar',
                'attributeId' => $id,
                'notStorable' => true
            ];
            $entity->fields[$name . 'UnitData'] = [
                'type'        => 'jsonObject',
                'attributeId' => $id,
                'notStorable' => true
            ];
            $entity->fields[$name . 'AllUnits'] = [
                'type'        => 'jsonObject',
                'attributeId' => $id,
                'notStorable' => true
            ];
            if (empty($skipValueProcessing)) {
                $entity->set($name . 'UnitId', $row[$entity->fields[$name . 'UnitId']['column']] ?? null);
            }

            $entity->entityDefs['fields'][$name . 'Unit'] = [
                "type"                      => "link",
                'label'                     => "{$row[$nameKey]} " . $this->language->translate('unitPart'),
                "view"                      => "views/fields/unit-link",
                "measureId"                 => $row['measure_id'],
                'attributeId'               => $id,
                'attributeValueId'          => $row['av_id'] ?? null,
                'classificationAttributeId' => $row['classification_attribute_id'] ?? null,
                'attributePanelId'          => $row['attribute_panel_id'] ?? null,
                'sortOrder'                 => $row['sort_order'] ?? null,
                'sortOrderInAttributeGroup' => $row['sort_order_in_attribute_group'] ?? null,
                'attributeGroup'            => [
                    'id'        => $row['attribute_group_id'] ?? null,
                    'name'      => $row['attribute_group_name'] ?? null,
                    'sortOrder' => $row['attribute_group_sort_order'] ?? null,
                ],
                'channelId'                 => $row['channel_id'] ?? null,
                'channelName'               => $row['channel_name'] ?? null,
                "entity"                    => 'Unit',
                "unitIdField"               => true,
                "mainField"                 => $name,
                'required'                  => !empty($row['is_required']),
                'readOnly'                  => !empty($row['is_read_only']),
                'protected'                 => !empty($row['is_protected']),
                'layoutDetailDisabled'      => true,
                'conditionalProperties'     => $conditionals,
                'modifiedExtendedDisabled'  => !empty($row['modified_extended_disabled'])
            ];
            $attributesDefs[$name . 'Unit']               = $entity->entityDefs['fields'][$name . 'Unit'];

            $entity->entityDefs['fields'][$name . 'UnitId'] = [
                'label' => "{$row[$nameKey]} " . $this->language->translate('unitPart'),
            ];
        }

        if ($hasPrefix) {
            $where = $this->extractPrefixWhere($row['data'] ?? null);

            $entity->entityDefs['fields'][$name]['prefixEnabled'] = true;
            $entity->entityDefs['fields'][$name]['where']         = $where;

            $entity->fields[$name . 'PrefixId']   = [
                'type'        => 'varchar',
                'name'        => $name,
                'attributeId' => $id,
                'column'      => 'prefix_value',
                'required'    => false,
            ];
            $entity->fields[$name . 'PrefixName'] = [
                'type'        => 'varchar',
                'attributeId' => $id,
                'notStorable' => true
            ];
            if (empty($skipValueProcessing)) {
                $entity->set($name . 'PrefixId', $row['prefix_value'] ?? null);
                $entity->set($name . 'PrefixName', $row['prefix_name'] ?? null);
            }

            $entity->entityDefs['fields'][$name . 'Prefix'] = [
                'type'                 => 'link',
                'label'                => "{$row[$nameKey]} " . $this->language->translate('prefixPart'),
                'entity'               => 'Prefix',
                'prefixEnabled'        => true,
                'where'                => $where,
                'prefixIdField'        => true,
                'mainField'            => $name,
                'attributeId'          => $id,
                'layoutDetailDisabled' => true,
            ];
            $attributesDefs[$name . 'Prefix']               = $entity->entityDefs['fields'][$name . 'Prefix'];
        }

        $attributesDefs[$name] = $entity->entityDefs['fields'][$name];
    }

    protected function getLanguageMap(): array
    {
        if ($this->cachedLanguages !== null) {
            return $this->cachedLanguages;
        }

        $this->cachedLanguages = [];
        if (!empty($this->config->get('isMultilangActive'))) {
            $referenceLanguages = $this->config->get('referenceData.Language', []);
            $codeToName         = [];
            foreach ($referenceLanguages as $v) {
                $codeToName[$v['code']] = $v['name'];
            }
            foreach ($this->config->get('inputLanguageList', []) as $code) {
                $this->cachedLanguages[$code] = $codeToName[$code] ?? $code;
            }
        }

        return $this->cachedLanguages;
    }

    public function getAttributeLabel(array $row, string $languageCode, array $languages): string
    {
        $currentLocale = $this->getCachedCurrentLocale();
        $nameKey       = $this->prepareKey('name', $row);

        if (!empty($currentLocale)) {
            if (!empty($currentLocale->get('displayLabelsInContentLanguage'))) {
                if (!empty($row['name_' . strtolower($languageCode)])) {
                    return $row['name_' . strtolower($languageCode)];
                }
            }

            if (array_key_exists($currentLocale->get('languageCode'), $languages)) {
                if ($languageCode === $currentLocale->get('languageCode')) {
                    return $row[$nameKey];
                }
                if (empty($languageCode)) {
                    foreach ($this->config->get('referenceData.Language', []) as $v) {
                        if ($v['role'] === 'main') {
                            return $row[$nameKey] . ' / ' . $v['name'];
                        }
                    }
                }
            }
        }

        $res = $row[$nameKey];
        if (!empty($languageCode)) {
            $res .= ' / ' . $languages[$languageCode];
        }

        return $res;
    }

    public function select(array $row, string $alias, QueryBuilder $qb, Mapper $mapper, array $params): void
    {
        $name = AttributeFieldConverter::prepareFieldName($row);

        $qb->addSelect("{$alias}.{$this->column} as " . $mapper->getQueryConverter()->fieldToAlias($name));

        if ($name === $params['orderBy']) {
            $qb->add('orderBy', $mapper->getQueryConverter()->fieldToAlias($name) . ' ' . $params['order']);
        }

        if (!empty($this->config->get('isMultilangActive')) && !empty($row['is_multilang'])) {
            foreach ($this->config->get('inputLanguageList', []) as $code) {
                $lName = $name . ucfirst(Util::toCamelCase(strtolower($code)));
                $qb->addSelect("{$alias}.{$this->column}_" . strtolower($code) . " as " . $mapper->getQueryConverter()->fieldToAlias($lName));

                if ($lName === $params['orderBy']) {
                    $qb->add('orderBy', $mapper->getQueryConverter()->fieldToAlias($lName) . ' ' . $params['order']);
                }
            }
        }

        $hasMeasure = $this->type === 'varchar' && isset($row['measure_id']) && empty($row['is_multilang']);
        $hasPrefix  = $this->type === 'varchar' && !empty($row['prefix_enabled']) && empty($row['is_multilang']);

        if ($hasMeasure) {
            $qb->leftJoin($alias, $this->conn->quoteIdentifier('unit'), "{$alias}_unit", "{$alias}_unit.id={$alias}.reference_value");

            $qb->addSelect("{$alias}.reference_value as " . $mapper->getQueryConverter()->fieldToAlias("{$name}UnitId"));
            $qb->addSelect("{$alias}_unit.name as " . $mapper->getQueryConverter()->fieldToAlias("{$name}UnitName"));

            if ("{$name}Unit" === $params['orderBy']) {
                $qb->add('orderBy', $mapper->getQueryConverter()->fieldToAlias("{$name}UnitName") . ' ' . $params['order']);
            }
        }

        if ($hasPrefix) {
            $qb->leftJoin($alias, $this->conn->quoteIdentifier('prefix'), "{$alias}_prefix", "{$alias}_prefix.id={$alias}.prefix_value AND {$alias}_prefix.deleted=:false");

            $qb->addSelect("{$alias}.prefix_value as " . $mapper->getQueryConverter()->fieldToAlias("{$name}PrefixId"));
            $qb->addSelect("{$alias}_prefix.value as " . $mapper->getQueryConverter()->fieldToAlias("{$name}PrefixName"));
        }
    }

    protected function convertWhere(IEntity $entity, array $attribute, array $item): array
    {
        if (str_ends_with($item['attribute'], 'UnitId')) {
            if ($item['type'] === 'isNull') {
                $item = [
                    'type'  => 'or',
                    'value' => [
                        [
                            'type'      => 'equals',
                            'attribute' => 'referenceValue',
                            'value'     => ''
                        ],
                        [
                            'type'      => 'isNull',
                            'attribute' => 'referenceValue'
                        ],
                    ]
                ];
            } else {
                if (!empty($item['subQuery'])) {
                    $this->convertSubquery($entity, 'Unit', $item);
                }
                $item['attribute'] = 'referenceValue';
            }
        } else if (str_ends_with($item['attribute'], 'PrefixId')) {
            if ($item['type'] === 'isNull') {
                $item = [
                    'type'  => 'or',
                    'value' => [
                        [
                            'type'      => 'equals',
                            'attribute' => 'prefixValue',
                            'value'     => ''
                        ],
                        [
                            'type'      => 'isNull',
                            'attribute' => 'prefixValue'
                        ],
                    ]
                ];
            } else {
                if (!empty($item['subQuery'])) {
                    $this->convertSubquery($entity, 'Prefix', $item);
                }
                $item['attribute'] = 'prefixValue';
            }
        } else {
            $item['attribute'] = Util::toCamelCase($this->column);

            if (!empty($attribute['is_multilang']) && !empty($item['language']) && $item['language'] !== 'main') {
                $item['attribute'] = $item['attribute'] . ucfirst(Util::toCamelCase(strtolower($item['language'])));
            }
        }

        return $item;
    }
}
