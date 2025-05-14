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

    public function convert(IEntity $entity, array $row, array &$attributesDefs): void
    {
        $id = $row['id'];
        $name = AttributeFieldConverter::prepareFieldName($id);
        $attributeData = @json_decode($row['data'], true)['field'] ?? null;

        $entity->entityDefs['fields'][$name] = [
            'type'           => 'range' . ucfirst($this->type),
            'attributeId'    => $id,
            'required'       => !empty($row['is_required']),
            'label'          => $row[$this->prepareKey('name', $row)],
            'view'           => "views/fields/range-{$this->type}",
            'importDisabled' => true,
            'tooltip'        => !empty($row[$this->prepareKey('tooltip', $row)]),
            'tooltipText'    => $row[$this->prepareKey('tooltip', $row)],
            'fullWidth'      => !empty($attributeData['fullWidth']),
        ];

        $entity->fields[$name . 'From'] = [
            'type'        => $this->type,
            'name'        => $name,
            'attributeId' => $id,
            'column'      => "{$this->type}_value",
            'required'    => !empty($row['is_required'])
        ];
        $entity->set($name . 'From', $row[$entity->fields[$name . 'From']['column']] ?? null);

        $attributesDefs[$name . 'From'] = $entity->entityDefs['fields'][$name . 'From'] = [
            'attributeId'          => $id,
            'type'                 => $this->type,
            "mainField"            => $name,
            'required'             => !empty($row['is_required']),
            'label'                => $row[$this->prepareKey('name', $row)] . ' ' . $this->language->translate('From'),
            'layoutDetailDisabled' => true
        ];

        $entity->fields[$name . 'To'] = [
            'type'        => $this->type,
            'name'        => $name,
            'attributeId' => $id,
            'column'      => "{$this->type}_value1",
            'required'    => !empty($row['is_required'])
        ];
        $entity->set($name . 'To', $row[$entity->fields[$name . 'To']['column']] ?? null);

        $attributesDefs[$name . 'To'] = $entity->entityDefs['fields'][$name . 'To'] = [
            'attributeId'          => $id,
            'type'                 => $this->type,
            "mainField"            => $name,
            'required'             => !empty($row['is_required']),
            'label'                => $row[$this->prepareKey('name', $row)] . ' ' . $this->language->translate('To'),
            'layoutDetailDisabled' => true
        ];

        if ($this->type === 'float') {
            $entity->entityDefs['fields'][$name]['amountOfDigitsAfterComma'] = $row['amount_of_digits_after_comma'] ?? null;
            $entity->entityDefs['fields'][$name . 'From']['amountOfDigitsAfterComma'] = $entity->entityDefs['fields'][$name]['amountOfDigitsAfterComma'];
            $entity->entityDefs['fields'][$name . 'To']['amountOfDigitsAfterComma'] = $entity->entityDefs['fields'][$name]['amountOfDigitsAfterComma'];

            if ($entity->get($name . 'From') !== null) {
                $entity->set($name . 'From', (float)$entity->get($name . 'From'));
            }
            if ($entity->get($name . 'To') !== null) {
                $entity->set($name . 'To', (float)$entity->get($name . 'To'));
            }
        } else {
            if ($entity->get($name . 'From') !== null) {
                $entity->set($name . 'From', (int)$entity->get($name . 'From'));
            }
            if ($entity->get($name . 'To') !== null) {
                $entity->set($name . 'To', (int)$entity->get($name . 'To'));
            }
        }

        if (isset($row['measure_id'])) {
            $entity->entityDefs['fields'][$name]['measureId'] = $row['measure_id'];

            $entity->fields[$name . 'UnitId'] = [
                'type'        => 'varchar',
                'name'        => $name,
                'attributeId' => $id,
                'column'      => 'reference_value',
                'required'    => !empty($row['is_required'])
            ];
            $entity->fields[$name . 'UnitName'] = [
                'type'        => 'varchar',
                'notStorable' => true
            ];
            $entity->set($name . 'UnitId', $row[$entity->fields[$name . 'UnitId']['column']] ?? null);

            $attributesDefs[$name . 'Unit'] = $entity->entityDefs['fields'][$name . 'Unit'] = [
                "type"                 => "link",
                'label'                => "{$row[$this->prepareKey('name', $row)]} " . $this->language->translate('unitPart'),
                "view"                 => "views/fields/unit-link",
                "measureId"            => $row['measure_id'],
                'attributeId'          => $id,
                "entity"               => 'Unit',
                "unitIdField"          => true,
                "mainField"            => $name,
                'required'             => !empty($row['is_required']),
                'layoutDetailDisabled' => true
            ];
        }

        $attributesDefs[$name] = $entity->entityDefs['fields'][$name];
    }

    public function select(array $row, string $alias, QueryBuilder $qb, Mapper $mapper): void
    {
        $name = AttributeFieldConverter::prepareFieldName($row['id']);

        $qb->leftJoin($alias, $this->conn->quoteIdentifier('unit'), "{$alias}_unit", "{$alias}_unit.id={$alias}.reference_value");

        $qb->addSelect("{$alias}.{$this->type}_value as " . $mapper->getQueryConverter()->fieldToAlias($name . 'From'));
        $qb->addSelect("{$alias}.{$this->type}_value1 as " . $mapper->getQueryConverter()->fieldToAlias($name . 'To'));
        $qb->addSelect("{$alias}.reference_value as " . $mapper->getQueryConverter()->fieldToAlias("{$name}UnitId"));
        $qb->addSelect("{$alias}_unit.name as " . $mapper->getQueryConverter()->fieldToAlias("{$name}UnitName"));
    }


    protected function convertWhere(IEntity $entity, array $attribute, array $item): array
    {
        if(str_ends_with($item['attribute'], 'UnitId')) {
            if($item['type'] === 'isNull') {
                $item =  [
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
            }else{
                if(!empty($item['subQuery'])) {
                    $this->convertSubquery($entity, 'Unit', $item);
                }
                $item['attribute'] = 'referenceValue';
            }
        }else{
            $item['attribute'] = str_ends_with($item['attribute'], 'From') ? "{$this->type}Value" :  "{$this->type}Value1";
        }

        return $item;
    }
}
