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

class ArrayType implements AttributeFieldTypeInterface
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
            'type'             => 'array',
            'required'         => !empty($row['is_required']),
            'label'            => $row['name']
        ];

        $attributesDefs[$name] = $entity->entityDefs['fields'][$name];
    }
}
