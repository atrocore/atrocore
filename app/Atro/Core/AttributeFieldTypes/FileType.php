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

class FileType extends AbstractFieldType
{
    public function convert(IEntity $entity, array $row, array &$attributesDefs): void
    {
        $name = AttributeFieldConverter::prepareFieldName($row['id']);

        $entity->fields[$name . 'Id'] = [
            'type'        => 'varchar',
            'name'        => $name,
            'attributeId' => $row['id'],
            'column'      => 'reference_value',
            'required'    => !empty($row['is_required'])
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
            'attributeId' => $row['id'],
            'type'        => 'file',
            'required'    => !empty($row['is_required']),
            'label'       => $row[$this->prepareKey('name', $row)],
            'tooltip'     => !empty($row[$this->prepareKey('tooltip', $row)]),
            'tooltipText' => $row[$this->prepareKey('tooltip', $row)]
        ];
    }

    public function select(array $row, string $alias, QueryBuilder $qb, Mapper $mapper): void
    {
    }
}
