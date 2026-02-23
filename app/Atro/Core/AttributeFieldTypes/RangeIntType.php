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
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\IEntity;

class RangeIntType extends AbstractFieldType
{
    protected string $type = 'int';

    protected Connection $conn;

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->conn = $container->get('connection');
    }

    public function convert(IEntity $entity, array $row, array &$attributesDefs, bool $skipValueProcessing = false): void
    {
        $id = $row['id'];
        $name = AttributeFieldConverter::prepareFieldName($row);
        $attributeData = @json_decode($row['data'], true)['field'] ?? null;

        $entity->entityDefs['fields'][$name] = [
            'type'                      => 'range' . ucfirst($this->type),
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
            'required'                  => !empty($row['is_required']),
            'readOnly'                  => !empty($row['is_read_only']),
            'protected'                 => !empty($row['is_protected']),
            'label'                     => $row[$this->prepareKey('name', $row)],
            'view'                      => "views/fields/range-{$this->type}",
            'importDisabled'            => true,
            'notSortable'               => true,
            'tooltip'                   => !empty($row[$this->prepareKey('tooltip', $row)]),
            'tooltipText'               => $row[$this->prepareKey('tooltip', $row)],
            'fullWidth'                 => !empty($attributeData['fullWidth']),
            'conditionalProperties'     => $this->prepareConditionalProperties($row)
        ];

        $entity->fields[$name . 'From'] = [
            'type'        => $this->type,
            'name'        => $name,
            'attributeId' => $id,
            'column'      => "{$this->type}_value",
            'required'    => !empty($row['is_required'])
        ];

        if (empty($skipValueProcessing)) {
            $entity->set($name . 'From', $row[$entity->fields[$name . 'From']['column']] ?? null);
        }

        $attributesDefs[$name . 'From'] = $entity->entityDefs['fields'][$name . 'From'] = [
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
            'rangeType'                 => true,
            "mainField"                 => $name,
            'required'                  => !empty($row['is_required']),
            'readOnly'                  => !empty($row['is_read_only']),
            'protected'                 => !empty($row['is_protected']),
            'label'                     => $row[$this->prepareKey('name', $row)] . ' ' . $this->language->translate('From'),
            'layoutDetailDisabled'      => true,
            'conditionalProperties'     => $this->prepareConditionalProperties($row)
        ];

        $entity->fields[$name . 'To'] = [
            'type'        => $this->type,
            'name'        => $name,
            'attributeId' => $id,
            'column'      => "{$this->type}_value1",
            'required'    => !empty($row['is_required'])
        ];

        if (empty($skipValueProcessing)) {
            $entity->set($name . 'To', $row[$entity->fields[$name . 'To']['column']] ?? null);
        }

        $attributesDefs[$name . 'To'] = $entity->entityDefs['fields'][$name . 'To'] = [
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
            'rangeType'                 => true,
            "mainField"                 => $name,
            'required'                  => !empty($row['is_required']),
            'readOnly'                  => !empty($row['is_read_only']),
            'protected'                 => !empty($row['is_protected']),
            'label'                     => $row[$this->prepareKey('name', $row)] . ' ' . $this->language->translate('To'),
            'layoutDetailDisabled'      => true,
            'conditionalProperties'     => $this->prepareConditionalProperties($row)
        ];

        if ($this->type === 'float') {
            $entity->entityDefs['fields'][$name]['amountOfDigitsAfterComma'] = $row['amount_of_digits_after_comma'] ?? null;
            $entity->entityDefs['fields'][$name . 'From']['amountOfDigitsAfterComma'] = $entity->entityDefs['fields'][$name]['amountOfDigitsAfterComma'];
            $entity->entityDefs['fields'][$name . 'To']['amountOfDigitsAfterComma'] = $entity->entityDefs['fields'][$name]['amountOfDigitsAfterComma'];

            if (empty($skipValueProcessing)) {
                if ($entity->get($name . 'From') !== null) {
                    $entity->set($name . 'From', (float)$entity->get($name . 'From'));
                }
                if ($entity->get($name . 'To') !== null) {
                    $entity->set($name . 'To', (float)$entity->get($name . 'To'));
                }
            }
        } else {
            if (empty($skipValueProcessing)) {
                if ($entity->get($name . 'From') !== null) {
                    $entity->set($name . 'From', (int)$entity->get($name . 'From'));
                }
                if ($entity->get($name . 'To') !== null) {
                    $entity->set($name . 'To', (int)$entity->get($name . 'To'));
                }
            }
        }

        if (isset($row['measure_id'])) {
            $entity->entityDefs['fields'][$name]['measureId'] = $row['measure_id'];
            $attributesDefs[$name . 'To']['measureId'] = $entity->entityDefs['fields'][$name . 'To']['measureId'] = $row['measure_id'];
            $attributesDefs[$name . 'From']['measureId'] = $entity->entityDefs['fields'][$name . 'From']['measureId'] = $row['measure_id'];

            $entity->fields[$name . 'UnitId'] = [
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

            $attributesDefs[$name . 'Unit'] = $entity->entityDefs['fields'][$name . 'Unit'] = [
                "type"                      => "link",
                'label'                     => "{$row[$this->prepareKey('name', $row)]} " . $this->language->translate('unitPart'),
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
                'conditionalProperties'     => $this->prepareConditionalProperties($row)
            ];
        }

        if (!empty($row['disable_field_value_lock'])) {
            $entity->entityDefs['fields'][$name]['disableFieldValueLock'] = true;
        }

        $attributesDefs[$name] = $entity->entityDefs['fields'][$name];

        $entity->entityDefs['fields'][$name . 'UnitId'] = [
            'label' => "{$row[$this->prepareKey('name', $row)]} " . $this->language->translate('unitPart'),
        ];
    }

    public function select(array $row, string $alias, QueryBuilder $qb, Mapper $mapper, array $params): void
    {
        $name = AttributeFieldConverter::prepareFieldName($row);

        $qb->leftJoin($alias, $this->conn->quoteIdentifier('unit'), "{$alias}_unit", "{$alias}_unit.id={$alias}.reference_value");

        $qb->addSelect("{$alias}.{$this->type}_value as " . $mapper->getQueryConverter()->fieldToAlias($name . 'From'));
        $qb->addSelect("{$alias}.{$this->type}_value1 as " . $mapper->getQueryConverter()->fieldToAlias($name . 'To'));
        $qb->addSelect("{$alias}.reference_value as " . $mapper->getQueryConverter()->fieldToAlias("{$name}UnitId"));
        $qb->addSelect("{$alias}_unit.name as " . $mapper->getQueryConverter()->fieldToAlias("{$name}UnitName"));

        switch ($params['orderBy']) {
            case "{$name}From":
                $qb->add('orderBy', $mapper->getQueryConverter()->fieldToAlias("{$name}From") . ' ' . $params['order']);
                break;
            case "{$name}To":
                $qb->add('orderBy', $mapper->getQueryConverter()->fieldToAlias("{$name}To") . ' ' . $params['order']);
                break;
            case "{$name}Unit":
                $qb->add('orderBy', $mapper->getQueryConverter()->fieldToAlias("{$name}UnitName") . ' ' . $params['order']);
                break;
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
        } else {
            $item['attribute'] = str_ends_with($item['attribute'], 'From') ? "{$this->type}Value" : "{$this->type}Value1";
        }

        return $item;
    }
}
