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

use Atro\Core\Container;
use Atro\Core\Utils\Language;
use Espo\ORM\IEntity;

class IntType implements AttributeFieldTypeInterface
{
    protected Language $language;
    protected string $type = 'int';

    public function __construct(Container $container)
    {
        $this->language = $container->get('language');
    }

    public function convert(IEntity $entity, string $id, string $name, array $row, array &$attributesDefs): void
    {
        $entity->fields[$name] = [
            'type'             => $this->type,
            'name'             => $name,
            'attributeValueId' => $id,
            'column'           => "{$this->type}_value",
            'required'         => !empty($row['is_required'])
        ];

        $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);

        $entity->entityDefs['fields'][$name] = [
            'attributeValueId' => $id,
            'type'             => $this->type,
            'required'         => !empty($row['is_required']),
            'notNull'          => !empty($row['not_null']),
            'label'            => $row['name']
        ];

        if (isset($attributeData['min'])) {
            $entity->entityDefs['fields'][$name]['min'] = $attributeData['min'];
        }
        if (isset($attributeData['max'])) {
            $entity->entityDefs['fields'][$name]['max'] = $attributeData['max'];
        }

        if ($this->type === 'float') {
            $entity->entityDefs['fields'][$name]['amountOfDigitsAfterComma'] = $row['amount_of_digits_after_comma'] ?? null;
        }

        if (isset($row['measure_id'])) {
            $entity->entityDefs['fields'][$name]['measureId'] = $row['measure_id'];
            $entity->entityDefs['fields'][$name]['layoutDetailView'] = "views/fields/unit-{$this->type}";

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

            $entity->entityDefs['fields'][$name . 'Unit'] = [
                "type"                 => "link",
                'label'                => "{$row['name']} " . $this->language->translate('unitPart'),
                "view"                 => "views/fields/unit-link",
                "measureId"            => $row['measure_id'],
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
}
