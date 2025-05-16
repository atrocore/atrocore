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
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\IEntity;

class DateType extends AbstractFieldType
{
    protected string $type = 'date';
    protected string $column = 'date_value';

    public function convert(IEntity $entity, array $row, array &$attributesDefs): void
    {
        $name = AttributeFieldConverter::prepareFieldName($row['id']);

        $entity->fields[$name] = [
            'type'        => $this->type,
            'name'        => $name,
            'attributeId' => $row['id'],
            'column'      => $this->column,
            'required'    => !empty($row['is_required'])
        ];

        $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);

        $attributeData = @json_decode($row['data'], true)['field'] ?? null;
        $attributesDefs[$name] = $entity->entityDefs['fields'][$name] = [
            'attributeId'               => $row['id'],
            'classificationAttributeId' => $row['classification_attribute_id'] ?? null,
            'attributePanelId'          => $row['attribute_panel_id'] ?? null,
            'channelId'                 => $row['channel_id'] ?? null,
            'type'                      => $this->type,
            'required'                  => !empty($row['is_required']),
            'label'                     => $row[$this->prepareKey('name', $row)],
            'tooltip'                   => !empty($row[$this->prepareKey('tooltip', $row)]),
            'tooltipText'               => $row[$this->prepareKey('tooltip', $row)],
            'fullWidth'                 => !empty($attributeData['fullWidth']),
        ];
    }

    public function select(array $row, string $alias, QueryBuilder $qb, Mapper $mapper): void
    {
        $name = AttributeFieldConverter::prepareFieldName($row['id']);

        $qb->addSelect("{$alias}.{$this->column} as " . $mapper->getQueryConverter()->fieldToAlias($name));
    }

    protected function convertWhere(IEntity $entity, array $attribute, array $item): array
    {
        $item['attribute'] = Util::toCamelCase($this->column);

        return $item;
    }
}
