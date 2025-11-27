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

class DatetimeType extends DateType
{
    protected string $type = 'datetime';
    protected string $column = 'datetime_value';

    protected function convertWhere(IEntity $entity, array $attribute, array $item): array
    {
        $item = parent::convertWhere($entity, $attribute, $item);
        $item['dateTime'] = true;
        return $item;
    }
}
