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

use Espo\ORM\IEntity;

class RangeIntType extends AbstractFieldType
{
    protected string $type = 'int';

    public function convert(IEntity $entity, string $id, string $name, array $row, array &$attributesDefs): void
    {
        $entity->entityDefs['fields'][$name] = [
            'type'             => 'range' . ucfirst($this->type),
            'attributeValueId' => $id,
            'required'         => !empty($row['is_required']),
            'label'            => $row[$this->prepareKey('name', $row)],
            'view'             => "views/fields/range-{$this->type}",
            'tooltip'          => !empty($row[$this->prepareKey('tooltip', $row)]),
            'tooltipText'      => $row[$this->prepareKey('tooltip', $row)]
        ];

        $entity->fields[$name . 'From'] = [
            'type'             => $this->type,
            'name'             => $name,
            'attributeValueId' => $id,
            'column'           => "{$this->type}_value",
            'required'         => !empty($row['is_required'])
        ];
        $entity->set($name . 'From', $row[$entity->fields[$name . 'From']['column']] ?? null);

        $attributesDefs[$name . 'From'] = $entity->entityDefs['fields'][$name . 'From'] = [
            'attributeValueId'     => $id,
            'type'                 => $this->type,
            'required'             => !empty($row['is_required']),
            'label'                => $row[$this->prepareKey('name', $row)] . ' ' . $this->language->translate('From'),
            'layoutDetailDisabled' => true
        ];

        $entity->fields[$name . 'To'] = [
            'type'             => $this->type,
            'name'             => $name,
            'attributeValueId' => $id,
            'column'           => "{$this->type}_value1",
            'required'         => !empty($row['is_required'])
        ];
        $entity->set($name . 'To', $row[$entity->fields[$name . 'To']['column']] ?? null);

        $attributesDefs[$name . 'To'] = $entity->entityDefs['fields'][$name . 'To'] = [
            'attributeValueId'     => $id,
            'type'                 => $this->type,
            'required'             => !empty($row['is_required']),
            'label'                => $row[$this->prepareKey('name', $row)] . ' ' . $this->language->translate('To'),
            'layoutDetailDisabled' => true
        ];

        if ($this->type === 'float') {
            $entity->entityDefs['fields'][$name]['amountOfDigitsAfterComma'] = $row['amount_of_digits_after_comma'] ?? null;
            $entity->entityDefs['fields'][$name . 'From']['amountOfDigitsAfterComma'] = $entity->entityDefs['fields'][$name]['amountOfDigitsAfterComma'];
            $entity->entityDefs['fields'][$name . 'To']['amountOfDigitsAfterComma'] = $entity->entityDefs['fields'][$name]['amountOfDigitsAfterComma'];
        }

        if (isset($row['measure_id'])) {
            $entity->entityDefs['fields'][$name]['measureId'] = $row['measure_id'];

            $entity->fields[$name . 'UnitId'] = [
                'type'             => 'varchar',
                'name'             => $name,
                'attributeValueId' => $id,
                'column'           => 'reference_value',
                'required'         => !empty($row['is_required'])
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
                "entity"               => 'Unit',
                "unitIdField"          => true,
                "mainField"            => $name,
                'required'             => !empty($row['is_required']),
                'layoutDetailDisabled' => true
            ];
        }

        $attributesDefs[$name] = $entity->entityDefs['fields'][$name];
    }
}
