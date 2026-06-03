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

class MultiEnumType extends ArrayType
{
    protected string $type = 'multiEnum';

    public function convert(IEntity $entity, array $row, array &$attributesDefs, bool $skipValueProcessing = false): void
    {
        parent::convert($entity, $row, $attributesDefs, $skipValueProcessing);

        $name = AttributeFieldConverter::prepareFieldName($row);

        $attributeData = @json_decode($row['data'], true)['field'] ?? null;

        $entity->entityDefs['fields'][$name]['options'] = $attributeData['options'] ?? [];
        $entity->entityDefs['fields'][$name]['optionColors'] = $attributeData['optionColors'] ?? [];

        $attributesDefs[$name] = $entity->entityDefs['fields'][$name];
    }
}
