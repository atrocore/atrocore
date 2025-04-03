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

class ExtensibleEnumType implements AttributeFieldTypeInterface
{
    public function convert(IEntity $entity, string $id, string $name, array $row, array &$attributesDefs): void
    {
        $entity->fields[$name] = [
            'type'             => 'varchar',
            'name'             => $name,
            'attributeValueId' => $id,
            'attributeId'      => $row['id'],
            'attributeName'    => $row['name'],
            'attributeType'    => $row['type'],
            'column'           => "reference_value",
            'required'         => !empty($row['is_required'])
        ];
        $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);

        $entity->entityDefs['fields'][$name] = [
            'type'                 => 'extensibleEnum',
            'required'             => !empty($row['is_required']),
            'label'                => $row['name'],
            'prohibitedEmptyValue' => !empty($row['prohibited_empty_value']),
            'dropdown'             => !empty($row['dropdown']),
            'extensibleEnumId'     => $row['extensible_enum_id'] ?? null
        ];
        if (!empty($row['dropdown'])) {
            $entity->entityDefs['fields'][$name]['view'] = "views/fields/extensible-enum-dropdown";
        }

        $attributesDefs[$name] = $entity->entityDefs['fields'][$name];
    }
}
