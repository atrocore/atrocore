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

class EnumType extends AbstractFieldType
{
    protected string $type = 'enum';
    protected string $column = 'varchar_value';

    public function convert(IEntity $entity, array $row, array &$attributesDefs, bool $skipValueProcessing = false): void
    {
        $id = $row['id'];
        $name = AttributeFieldConverter::prepareFieldName($row);
        $attributeData = !empty($row['data']) ? @json_decode($row['data'], true)['field'] ?? null : null;

        // Resolve once per attribute — avoids repeated detectLocale + getEntity calls inside prepareKey
        $nameKey = $this->prepareKey('name', $row);
        $tooltipKey = $this->prepareKey('tooltip', $row);
        $conditionals = $this->prepareConditionalProperties($row);

        $entity->fields[$name] = [
            'type'                     => $this->type,
            'name'                     => $name,
            'attributeId'              => $id,
            'column'                   => $this->column,
            'required'                 => !empty($row['is_required']),
            'modifiedExtendedDisabled' => !empty($row['modified_extended_disabled'])
        ];

        if (empty($skipValueProcessing)) {
            $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);
        }

        $entity->entityDefs['fields'][$name] = [
            'attributeId'               => $id,
            'attributeValueId'          => $row['av_id'] ?? null,
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
            'channelName'               => $row['channel_name'] ?? null,
            'type'                      => $this->type,
            'required'                  => !empty($row['is_required']),
            'readOnly'                  => !empty($row['is_read_only']),
            'protected'                 => !empty($row['is_protected']),
            'notNull'                   => !empty($row['not_null']),
            'label'                     => $row[$nameKey],
            'tooltip'                   => !empty($row[$tooltipKey]),
            'tooltipText'               => $row[$tooltipKey],
            'conditionalProperties'     => $conditionals,
            'modifiedExtendedDisabled'  => !empty($row['modified_extended_disabled']),
            'options'                   => $attributeData['options'] ?? [],
            'optionColors'              => $attributeData['optionColors'] ?? [],
        ];

        if (!empty($row['disable_field_value_lock'])) {
            $entity->entityDefs['fields'][$name]['disableFieldValueLock'] = true;
        }

        $entity->entityDefs['fields'][$name]['fullWidth'] = !empty($attributeData['fullWidth']);

        $attributesDefs[$name] = $entity->entityDefs['fields'][$name];
    }

    public function select(array $row, string $alias, QueryBuilder $qb, Mapper $mapper, array $params): void
    {
        $name = AttributeFieldConverter::prepareFieldName($row);

        $qb->addSelect("{$alias}.{$this->column} as " . $mapper->getQueryConverter()->fieldToAlias($name));

        if ($name === $params['orderBy']) {
            $qb->add('orderBy', $mapper->getQueryConverter()->fieldToAlias($name) . ' ' . $params['order']);
        }
    }
}
