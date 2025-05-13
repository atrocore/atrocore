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

class IntType extends AbstractFieldType
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

        $entity->fields[$name] = [
            'type'        => $this->type,
            'name'        => $name,
            'attributeId' => $id,
            'column'      => "{$this->type}_value",
            'required'    => !empty($row['is_required'])
        ];

        $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);

        $entity->entityDefs['fields'][$name] = [
            'attributeId' => $id,
            'type'        => $this->type,
            'required'    => !empty($row['is_required']),
            'notNull'     => !empty($row['not_null']),
            'label'       => $row[$this->prepareKey('name', $row)],
            'tooltip'     => !empty($row[$this->prepareKey('tooltip', $row)]),
            'tooltipText' => $row[$this->prepareKey('tooltip', $row)],
            'fullWidth'   => !empty($attributeData['fullWidth']),
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

            if ($entity->get($name) !== null) {
                $entity->set($name, (float)$entity->get($name));
            }
        } else {
            if ($entity->get($name) !== null) {
                $entity->set($name, (int)$entity->get($name));
            }
        }

        if (isset($row['measure_id'])) {
            $entity->entityDefs['fields'][$name]['measureId'] = $row['measure_id'];
            $entity->entityDefs['fields'][$name]['layoutDetailView'] = "views/fields/unit-{$this->type}";
            $entity->entityDefs['fields'][$name]['detailViewLabel'] = $entity->entityDefs['fields'][$name]['label'];
            $entity->entityDefs['fields'][$name]['label'] = "{$row[$this->prepareKey('name', $row)]} " . $this->language->translate("{$this->type}Part");

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

            $entity->entityDefs['fields'][$name . 'Unit'] = [
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
            $attributesDefs[$name . 'Unit'] = $entity->entityDefs['fields'][$name . 'Unit'];
        }

        $attributesDefs[$name] = $entity->entityDefs['fields'][$name];
    }

    public function select(array $row, string $alias, QueryBuilder $qb, Mapper $mapper): void
    {
        $name = AttributeFieldConverter::prepareFieldName($row['id']);

        $qb->leftJoin($alias, $this->conn->quoteIdentifier('unit'), "{$alias}_unit", "{$alias}_unit.id={$alias}.reference_value");

        $qb->addSelect("{$alias}.{$this->type}_value as " . $mapper->getQueryConverter()->fieldToAlias($name));
        $qb->addSelect("{$alias}.reference_value as " . $mapper->getQueryConverter()->fieldToAlias("{$name}UnitId"));
        $qb->addSelect("{$alias}_unit.name as " . $mapper->getQueryConverter()->fieldToAlias("{$name}UnitName"));
    }

    protected function convertWhere(IEntity $entity, array $attribute, array $item): array
    {
        if(str_ends_with($item['attribute'], 'UnitId')) {
            if($item['type'] === 'isNull') {
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
            }else{
                $item['attribute'] = 'referenceValue';
            }
        }else{
            $item['attribute'] = "{$this->type}Value";
        }

        return $item;
    }
}
