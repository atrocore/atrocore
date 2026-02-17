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

class BoolType extends AbstractFieldType
{
    public function convert(IEntity $entity, array $row, array &$attributesDefs, bool $skipValueProcessing = false): void
    {
        $name = AttributeFieldConverter::prepareFieldName($row);

        $entity->fields[$name] = [
            'type'        => 'bool',
            'name'        => $name,
            'attributeId' => $row['id'],
            'column'      => "bool_value",
            'required'    => !empty($row['is_required']),
            'modifiedExtendedDisabled' => !empty($row['modified_extended_disabled'])
        ];

        $attributeData = @json_decode($row['data'], true)['field'] ?? null;

        if (empty($skipValueProcessing)) {
            $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);

            if ($entity->get($name) !== null) {
                $entity->set($name, !empty($entity->get($name)));
            }
        }


        $entity->entityDefs['fields'][$name] = [
            'attributeId'               => $row['id'],
            'attributeValueId'          => $row['av_id'] ?? null,
            'classificationAttributeId' => $row['classification_attribute_id'] ?? null,
            'channelId'                 => $row['channel_id'] ?? null,
            'channelName'               => $row['channel_name'] ?? null,
            'attributePanelId'          => $row['attribute_panel_id'] ?? null,
            'sortOrder'                 => $row['sort_order'] ?? null,
            'sortOrderInAttributeGroup' => $row['sort_order_in_attribute_group'] ?? null,
            'attributeGroup'            => [
                'id'        => $row['attribute_group_id'] ?? null,
                'name'      => $row['attribute_group_name'] ?? null,
                'sortOrder' => $row['attribute_group_sort_order'] ?? null,
            ],
            'type'                      => 'bool',
            'required'                  => !empty($row['is_required']),
            'readOnly'                  => !empty($row['is_read_only']),
            'protected'                 => !empty($row['is_protected']),
            'notNull'                   => !empty($row['not_null']),
            'label'                     => $row[$this->prepareKey('name', $row)],
            'tooltip'                   => !empty($row[$this->prepareKey('tooltip', $row)]),
            'tooltipText'               => $row[$this->prepareKey('tooltip', $row)],
            'fullWidth'                 => !empty($attributeData['fullWidth']),
            'conditionalProperties'     => $this->prepareConditionalProperties($row),
            'modifiedExtendedDisabled' => !empty($row['modified_extended_disabled'])
        ];

        if (class_exists('\\AdvancedDataManagement\\Module')) {
            $entity->entityDefs['fields'][$name]['disableFieldValueLock'] = !empty($row['disable_field_value_lock']);
        }

        $attributesDefs[$name] = $entity->entityDefs['fields'][$name];
    }

    public function select(array $row, string $alias, QueryBuilder $qb, Mapper $mapper, array $params): void
    {
        $name = AttributeFieldConverter::prepareFieldName($row);

        $qb->addSelect("{$alias}.bool_value as " . $mapper->getQueryConverter()->fieldToAlias($name));

        if ($name === $params['orderBy']) {
            $qb->add('orderBy', $mapper->getQueryConverter()->fieldToAlias($name) . ' ' . $params['order']);
        }
    }

    protected function convertWhere(IEntity $entity, array $attribute, array $item): array
    {
        $item['attribute'] = 'boolValue';

        return $item;
    }
}
