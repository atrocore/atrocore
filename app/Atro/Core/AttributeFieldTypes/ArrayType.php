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
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\IEntity;

class ArrayType extends AbstractFieldType
{
    public function convert(IEntity $entity, array $row, array &$attributesDefs): void
    {
        $name = AttributeFieldConverter::prepareFieldName($row['id']);

        $entity->fields[$name] = [
            'type'        => 'jsonArray',
            'name'        => $name,
            'attributeId' => $row['id'],
            'column'      => "json_value",
            'required'    => !empty($row['is_required'])
        ];

        $value = @json_decode($row[$entity->fields[$name]['column']] ?? '[]', true);
        $entity->set($name, is_array($value) ? $value : null);

        $entity->entityDefs['fields'][$name] = [
            'attributeId' => $row['id'],
            'type'        => 'array',
            'required'    => !empty($row['is_required']),
            'label'       => $row[$this->prepareKey('name', $row)],
            'tooltip'     => !empty($row[$this->prepareKey('tooltip', $row)]),
            'tooltipText' => $row[$this->prepareKey('tooltip', $row)]
        ];

        $attributesDefs[$name] = $entity->entityDefs['fields'][$name];
    }

    public function select(array $row, string $alias, QueryBuilder $qb, Mapper $mapper): void
    {
        $name = AttributeFieldConverter::prepareFieldName($row['id']);

        $qb->addSelect("{$alias}.json_value as " . $mapper->getQueryConverter()->fieldToAlias($name));
    }
}
