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

class DateType implements AttributeFieldTypeInterface
{
    protected string $type = 'date';
    protected string $column = 'date_value';

    public function convert(IEntity $entity, string $id, string $name, array $row, array &$attributesDefs): void
    {
        $entity->fields[$name] = [
            'type'             => $this->type,
            'name'             => $name,
            'attributeValueId' => $id,
            'column'           => $this->column,
            'required'         => !empty($row['is_required'])
        ];

        $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);

        $attributesDefs[$name] = $entity->entityDefs['fields'][$name] = [
            'attributeValueId' => $id,
            'type'             => $this->type,
            'required'         => !empty($row['is_required']),
            'label'            => $row['name']
        ];
    }
}
