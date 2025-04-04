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

class ExtensibleMultiEnumType extends AbstractFieldType
{
    public function convert(IEntity $entity, string $id, string $name, array $row, array &$attributesDefs): void
    {
        $entity->fields[$name] = [
            'type'             => 'jsonArray',
            'name'             => $name,
            'attributeValueId' => $id,
            'column'           => "json_value",
            'required'         => !empty($row['is_required'])
        ];

        $value = @json_decode($row[$entity->fields[$name]['column']] ?? '[]', true);
        $entity->set($name, is_array($value) ? $value : null);

        $entity->entityDefs['fields'][$name] = [
            'attributeValueId' => $id,
            'type'             => 'extensibleMultiEnum',
            'required'         => !empty($row['is_required']),
            'label'            => $row['name'],
            'dropdown'         => !empty($row['dropdown']),
            'extensibleEnumId' => $row['extensible_enum_id'] ?? null,
            'tooltip'          => !empty($row[$this->prepareKey('tooltip', $row)]),
            'tooltipText'      => $row[$this->prepareKey('tooltip', $row)]
        ];
        if (!empty($row['dropdown'])) {
            $entity->entityDefs['fields'][$name]['view'] = "views/fields/extensible-multi-enum-dropdown";
        }

        $attributesDefs[$name] = $entity->entityDefs['fields'][$name];
    }
}
