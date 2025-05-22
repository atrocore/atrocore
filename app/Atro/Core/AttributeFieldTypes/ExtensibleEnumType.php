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

class ExtensibleEnumType extends AbstractFieldType
{
    public function convert(IEntity $entity, array $row, array &$attributesDefs): void
    {
        $name = AttributeFieldConverter::prepareFieldName($row['id']);

        $entity->fields[$name] = [
            'type'        => 'varchar',
            'name'        => $name,
            'attributeId' => $row['id'],
            'column'      => "reference_value",
            'required'    => !empty($row['is_required'])
        ];
        $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);

        $attributeData = @json_decode($row['data'], true)['field'] ?? null;

        $entity->entityDefs['fields'][$name] = [
            'attributeId'               => $row['id'],
            'classificationAttributeId' => $row['classification_attribute_id'] ?? null,
            'attributePanelId'          => $row['attribute_panel_id'] ?? null,
            'sortOrder'                 => $row['sort_order'] ?? null,
            'sortOrderInAttributeGroup' => $row['sort_order_in_attribute_group'] ?? null,
            'attributeGroup'            => [
                'id'        => $row['attribute_group_id'] ?? null,
                'name'      => $row['attribute_group_name'] ?? null,
                'sortOrder' => $row['attribute_group_sort_order'] ?? null,
            ],
            'channelId'                 => $row['channel_id'] ?? null,
            'type'                      => 'extensibleEnum',
            'required'                  => !empty($row['is_required']),
            'label'                     => $row[$this->prepareKey('name', $row)],
            'prohibitedEmptyValue'      => !empty($row['prohibited_empty_value']),
            'dropdown'                  => !empty($row['dropdown']),
            'extensibleEnumId'          => $row['extensible_enum_id'] ?? null,
            'allowedOptions'            => $attributeData['allowedOptions'] ?? null,
            'tooltip'                   => !empty($row[$this->prepareKey('tooltip', $row)]),
            'tooltipText'               => $row[$this->prepareKey('tooltip', $row)],
            'fullWidth'                 => !empty($attributeData['fullWidth'])
        ];

        if (!empty($attributeData['dropdown'])) {
            $entity->entityDefs['fields'][$name]['view'] = "views/fields/extensible-enum-dropdown";
        }

        $attributesDefs[$name] = $entity->entityDefs['fields'][$name];
    }

    public function select(array $row, string $alias, QueryBuilder $qb, Mapper $mapper): void
    {
        $name = AttributeFieldConverter::prepareFieldName($row['id']);

        $qb->addSelect("{$alias}.reference_value as " . $mapper->getQueryConverter()->fieldToAlias($name));
    }

    protected function convertWhere(IEntity $entity, array $attribute, array $item): array
    {
        if(!empty($item['subQuery'])) {
            $this->convertSubquery($entity, 'ExtensibleEnumOption', $item);
        }

        $item['attribute'] = 'referenceValue';

        return $item;
    }
}
