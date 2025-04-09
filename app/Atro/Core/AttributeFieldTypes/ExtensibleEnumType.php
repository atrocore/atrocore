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
use Espo\ORM\IEntity;

class ExtensibleEnumType extends AbstractFieldType
{
    public function convert(IEntity $entity, array $row, array &$attributesDefs): void
    {
        $name = AttributeFieldConverter::prepareFieldName($row['id']);

        $entity->fields[$name] = [
            'type'        => 'varchar',
            'name'        => $name,
            'attributeId' => $row['id'],
            'column'      => "reference_value",
            'required'    => !empty($row['is_required'])
        ];
        $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);

        $entity->entityDefs['fields'][$name] = [
            'attributeId'          => $row['id'],
            'type'                 => 'extensibleEnum',
            'required'             => !empty($row['is_required']),
            'label'                => $row[$this->prepareKey('name', $row)],
            'prohibitedEmptyValue' => !empty($row['prohibited_empty_value']),
            'dropdown'             => !empty($row['dropdown']),
            'extensibleEnumId'     => $row['extensible_enum_id'] ?? null,
            'tooltip'              => !empty($row[$this->prepareKey('tooltip', $row)]),
            'tooltipText'          => $row[$this->prepareKey('tooltip', $row)]
        ];

        $attributeData = @json_decode($row['data'], true)['field'] ?? null;

        if (!empty($attributeData['dropdown'])) {
            $entity->entityDefs['fields'][$name]['view'] = "views/fields/extensible-enum-dropdown";
        }

        $attributesDefs[$name] = $entity->entityDefs['fields'][$name];
    }
}
