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

class FileType implements AttributeFieldTypeInterface
{
    public function convert(IEntity $entity, string $id, string $name, array $row, array &$attributesDefs): void
    {
        $entity->fields[$name . 'Id'] = [
            'type'             => 'varchar',
            'name'             => $name,
            'attributeValueId' => $id,
            'column'           => 'reference_value',
            'required'         => !empty($row['is_required'])
        ];

        $entity->fields[$name . 'Name'] = [
            'type'        => 'varchar',
            'notStorable' => true
        ];

        $entity->fields[$name . 'PathsData'] = [
            'type'        => 'jsonObject',
            'notStorable' => true
        ];

        $entity->set($name . 'Id', $row[$entity->fields[$name . 'Id']['column']] ?? null);
        $entity->set($name . 'Name', $row['file_name'] ?? null);

        $attributesDefs[$name] = $entity->entityDefs['fields'][$name] = [
            'attributeValueId' => $id,
            'type'             => 'file',
            'required'         => !empty($row['is_required']),
            'label'            => $row['name'],
            'tooltip'          => !empty($row['tooltip']),
            'tooltipText'      => $row['tooltip']
        ];
    }
}
