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
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\IEntity;

class IntType extends AbstractFieldType
{
    protected string $type = 'int';

    protected Connection $conn;

    public function getValueColumn(): string
    {
        return "{$this->type}_value";
    }

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->conn = $container->get('connection');
    }

    public function convert(IEntity $entity, array $row, array &$attributesDefs, bool $skipValueProcessing = false): void
    {
        $id            = $row['id'];
        $name          = AttributeFieldConverter::prepareFieldName($row);
        $attributeData = @json_decode($row['data'], true)['field'] ?? null;

        $entity->fields[$name] = [
            'type'                     => $this->type,
            'name'                     => $name,
            'attributeId'              => $id,
            'column'                   => "{$this->type}_value",
            'required'                 => !empty($row['is_required']),
            'modifiedExtendedDisabled' => !empty($row['modified_extended_disabled'])
        ];

        $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);

        $entity->entityDefs['fields'][$name] = [
            'attributeId'               => $id,
            'attributeValueId'          => $row['av_id'] ?? null,
            'classificationAttributeId' => $row['classification_attribute_id'] ?? null,
            'classificationId'           => $row['classification_id'] ?? null,
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
            'label'                     => $row[$this->prepareKey('name', $row)],
            'tooltip'                   => !empty($row[$this->prepareKey('tooltip', $row)]),
            'tooltipText'               => $row[$this->prepareKey('tooltip', $row)],
            'fullWidth'                 => !empty($attributeData['fullWidth']),
            'conditionalProperties'     => $this->prepareConditionalProperties($row),
            'modifiedExtendedDisabled'  => !empty($row['modified_extended_disabled'])
        ];

        $attributeData = @json_decode($row['data'], true)['field'] ?? null;

        if (isset($attributeData['min'])) {
            $entity->entityDefs['fields'][$name]['min'] = $attributeData['min'];
        }
        if (isset($attributeData['max'])) {
            $entity->entityDefs['fields'][$name]['max'] = $attributeData['max'];
        }

        if ($this->type === 'float') {
            $entity->entityDefs['fields'][$name]['amountOfDigitsAfterComma'] = $row['amount_of_digits_after_comma'] ?? null;

            if (empty($skipValueProcessing)) {
                if ($entity->get($name) !== null) {
                    $entity->set($name, (float)$entity->get($name));
                }
            }
        } else {
            if (empty($skipValueProcessing)) {
                if ($entity->get($name) !== null) {
                    $entity->set($name, (int)$entity->get($name));
                }
            }
        }

        if (!empty($row['disable_field_value_lock'])) {
            $entity->entityDefs['fields'][$name]['disableFieldValueLock'] = true;
        }

        $hasMeasure = isset($row['measure_id']);
        $hasPrefix  = !empty($row['prefix_enabled']);
        $nameKey    = $this->prepareKey('name', $row);

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
                'attributeId' => $row['id'],
                'notStorable' => true
            ];
            $entity->fields[$name . 'UnitData'] = [
                'type'        => 'jsonObject',
                'attributeId' => $row['id'],
                'notStorable' => true
            ];
            $entity->fields[$name . 'AllUnits'] = [
                'type'        => 'jsonObject',
                'attributeId' => $row['id'],
                'notStorable' => true
            ];

            if (empty($skipValueProcessing)) {
                if (empty($row['av_id']) && !empty($row['default_unit'])) {
                    // set default unit when we add attribute
                    $entity->set($name . 'UnitId', $row['default_unit']);
                } else {
                    $entity->set($name . 'UnitId', $row[$entity->fields[$name . 'UnitId']['column']] ?? null);
                }
            }

            $entity->entityDefs['fields'][$name . 'Unit'] = [
                "type"                      => "link",
                'label'                     => "{$row[$nameKey]} " . $this->language->translate('unitPart'),
                "view"                      => "views/fields/unit-link",
                "measureId"                 => $row['measure_id'],
                'attributeId'               => $id,
                'attributeValueId'          => $row['av_id'] ?? null,
                'classificationAttributeId' => $row['classification_attribute_id'] ?? null,
                'classificationId'           => $row['classification_id'] ?? null,
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
                'notStorable' => true,
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

    public function select(array $row, string $alias, QueryBuilder $qb, Mapper $mapper, array $params): void
    {
        $name = AttributeFieldConverter::prepareFieldName($row);

        $qb->addSelect("{$alias}.{$this->type}_value as " . $mapper->getQueryConverter()->fieldToAlias($name));

        if (isset($row['measure_id'])) {
            $qb->leftJoin($alias, $this->conn->quoteIdentifier('unit'), "{$alias}_unit", "{$alias}_unit.id={$alias}.reference_value");
            $qb->addSelect("{$alias}.reference_value as " . $mapper->getQueryConverter()->fieldToAlias("{$name}UnitId"));
            $qb->addSelect("{$alias}_unit.name as " . $mapper->getQueryConverter()->fieldToAlias("{$name}UnitName"));

            if ("{$name}Unit" === $params['orderBy']) {
                $qb->add('orderBy', $mapper->getQueryConverter()->fieldToAlias("{$name}UnitName") . ' ' . $params['order']);
            }
        }

        if (!empty($row['prefix_enabled'])) {
            $qb->leftJoin($alias, $this->conn->quoteIdentifier('prefix'), "{$alias}_prefix", "{$alias}_prefix.id={$alias}.prefix_value AND {$alias}_prefix.deleted=:false");
            $qb->addSelect("{$alias}.prefix_value as " . $mapper->getQueryConverter()->fieldToAlias("{$name}PrefixId"));
            $qb->addSelect("{$alias}_prefix.value as " . $mapper->getQueryConverter()->fieldToAlias("{$name}PrefixName"));
        }

        if ($name === $params['orderBy']) {
            $qb->add('orderBy', $mapper->getQueryConverter()->fieldToAlias($name) . ' ' . $params['order']);
        }
    }

    protected function convertWhere(IEntity $entity, array $attribute, array $item): array
    {
        if (!empty($item['combinedField'])) {
            return $this->convertUnitFieldWhere($entity, $attribute, $item);
        }

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
            $item['attribute'] = "{$this->type}Value";
        }

        return $item;
    }

    protected function convertUnitFieldWhere(IEntity $entity, array $attribute, array $item): array
    {
        $type        = $item['type'];
        $value       = $item['value'] ?? null;
        $valueColumn = $item['valueColumn'] ?? "{$this->type}Value";
        $isInt       = $this->type === 'int';

        if ($type === 'isNull') {
            return [
                'type'  => 'and',
                'value' => [
                    ['type' => 'isNull', 'attribute' => $valueColumn],
                    [
                        'type'  => 'or',
                        'value' => [
                            ['type' => 'isNull', 'attribute' => 'referenceValue'],
                            ['type' => 'equals', 'attribute' => 'referenceValue', 'value' => ''],
                        ]
                    ],
                ]
            ];
        }

        if ($type === 'isNotNull') {
            return [
                'type'  => 'and',
                'value' => [
                    ['type' => 'isNotNull', 'attribute' => $valueColumn],
                    ['type' => 'isNotNull', 'attribute' => 'referenceValue'],
                    ['type' => 'notEquals', 'attribute' => 'referenceValue', 'value' => ''],
                ]
            ];
        }

        $measureUnits = $this->getAttributeMeasureUnitsData($entity, $attribute['id']);
        if (empty($measureUnits)) {
            return ['type' => 'equals', 'attribute' => 'referenceValue', 'value' => '__impossible__'];
        }

        if ($type === 'between') {
            if (!is_array($value) || count($value) < 2) {
                return [];
            }

            $baseFrom = $this->resolveUnitFilterValue($value[0], $measureUnits);
            $baseTo   = $this->resolveUnitFilterValue($value[1], $measureUnits);
            if ($baseFrom === null || $baseTo === null) {
                return [];
            }

            $orConditions = [];
            foreach ($measureUnits as $unitId => $multiplier) {
                $convertedFrom  = $baseFrom / $multiplier;
                $convertedTo    = $baseTo / $multiplier;
                $orConditions[] = [
                    'type'  => 'and',
                    'value' => [
                        ['type' => 'equals', 'attribute' => 'referenceValue', 'value' => $unitId],
                        ['type' => 'greaterThanOrEquals', 'attribute' => $valueColumn, 'value' => $isInt ? (int)round($convertedFrom) : $convertedFrom],
                        ['type' => 'lessThanOrEquals', 'attribute' => $valueColumn, 'value' => $isInt ? (int)round($convertedTo) : $convertedTo],
                    ]
                ];
            }

            return ['type' => 'or', 'value' => $orConditions];
        }

        $baseValue = $this->resolveUnitFilterValue($value, $measureUnits);
        if ($baseValue === null) {
            return [];
        }

        $orConditions = [];
        foreach ($measureUnits as $unitId => $multiplier) {
            $convertedValue = $baseValue / $multiplier;
            $orConditions[] = [
                'type'  => 'and',
                'value' => [
                    ['type' => 'equals', 'attribute' => 'referenceValue', 'value' => $unitId],
                    ['type' => $type, 'attribute' => $valueColumn, 'value' => $isInt ? (int)round($convertedValue) : $convertedValue],
                ]
            ];
        }

        return ['type' => 'or', 'value' => $orConditions];
    }

    protected function getAttributeMeasureUnitsData(IEntity $entity, string $attributeId): array
    {
        $tableName = Util::toUnderScore(lcfirst($entity->getEntityName()));

        $rows = $this->conn->createQueryBuilder()
            ->select('u.id', 'u.multiplier')
            ->from('unit', 'u')
            ->innerJoin('u',
                "(SELECT DISTINCT reference_value AS uid FROM {$tableName}_attribute_value WHERE attribute_id = :attrId AND reference_value IS NOT NULL AND reference_value != '' AND deleted = :false)",
                'used',
                'u.id = used.uid'
            )
            ->where('u.deleted = :false')
            ->setParameter('attrId', $attributeId)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        $units = [];
        foreach ($rows as $row) {
            $multiplier = (float)$row['multiplier'];
            if ($multiplier != 0) {
                $units[$row['id']] = $multiplier;
            }
        }

        return $units;
    }

    protected function resolveUnitFilterValue($value, array $measureUnits): ?float
    {
        if (!is_array($value) || count($value) < 2) {
            return null;
        }

        $amount = $value[0];
        $unitId = $value[1];

        if (!is_numeric($amount) || empty($unitId)) {
            return null;
        }

        if (isset($measureUnits[$unitId])) {
            $multiplier = $measureUnits[$unitId];
        } else {
            $row = $this->conn->createQueryBuilder()
                ->select('multiplier')
                ->from('unit')
                ->where('id = :id AND deleted = :false')
                ->setParameter('id', $unitId)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAssociative();

            $multiplier = !empty($row) ? (float)$row['multiplier'] : null;
        }

        if (empty($multiplier)) {
            return null;
        }

        return (float)$amount * $multiplier;
    }
}
