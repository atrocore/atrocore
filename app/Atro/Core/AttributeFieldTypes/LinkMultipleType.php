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

class LinkMultipleType extends AbstractFieldType
{
    public function convert(IEntity $entity, array $row, array &$attributesDefs, bool $skipValueProcessing = false): void
    {
        $name = AttributeFieldConverter::prepareFieldName($row);

        $data = @json_decode($row['data'], true);
        $attributeData = $data['field'] ?? null;
        $entityName = $attributeData['entityType'] ?? null;

        $entity->fields[$name . 'Ids'] = [
            'type'                     => 'jsonArray',
            'name'                     => $name,
            'attributeId'              => $row['id'],
            'column'                   => "json_value",
            'required'                 => !empty($row['is_required']),
            'fullWidth'                => !empty($attributeData['fullWidth']),
            'modifiedExtendedDisabled' => !empty($row['modified_extended_disabled'])
        ];

        $entity->fields[$name . 'Names'] = [
            'type'        => 'jsonObject',
            'attributeId' => $row['id'],
            'notStorable' => true
        ];

        if (empty($skipValueProcessing)) {
            $value = $row[$entity->fields[$name . 'Ids']['column']] ?? null;
            if ($value !== null) {
                $value = @json_decode((string)$value, true);
            }

            if (!empty($attributeData['dropdown'])) {
                $entity->set($name . 'Ids', is_array($value) ? $value : []);
            } else {
                $entity->set($name . 'Ids', !empty($value) ? $value : null);
            }

            if (!empty($value) && !empty($entityName)) {
                $names = $this->em->getRepository($entityName)
                    ->select(['id', 'name'])
                    ->where(['id' => $value])
                    ->find();

                if (!empty($names)) {
                    $entity->set($name . 'Names', array_column($names->toArray(), 'name', 'id'));
                }
            }
        }

        $entity->entityDefs['fields'][$name] = [
            'attributeId'               => $row['id'],
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
            'type'                      => 'linkMultiple',
            'entity'                    => $entityName,
            'required'                  => !empty($row['is_required']),
            'readOnly'                  => !empty($row['is_read_only']),
            'protected'                 => !empty($row['is_protected']),
            'label'                     => $row[$this->prepareKey('name', $row)],
            'dropdown'                  => !empty($attributeData['dropdown']),
            'tooltip'                   => !empty($row[$this->prepareKey('tooltip', $row)]),
            'tooltipText'               => $row[$this->prepareKey('tooltip', $row)],
            'notSortable'               => true,
            'conditionalProperties'     => $this->prepareConditionalProperties($row),
            'modifiedExtendedDisabled'  => !empty($row['modified_extended_disabled']),
            'where'                     => $data['where'] ?? []
        ];

        if (!empty($row['disable_field_value_lock'])) {
            $entity->entityDefs['fields'][$name]['disableFieldValueLock'] = true;
        }

        if (!empty($attributeData['dropdown'])) {
            $entity->entityDefs['fields'][$name]['view'] = "views/fields/link-multiple-dropdown";
        }

        $attributesDefs[$name] = $entity->entityDefs['fields'][$name];
    }

    public function select(array $row, string $alias, QueryBuilder $qb, Mapper $mapper, array $params): void
    {
        $name = AttributeFieldConverter::prepareFieldName($row);

        $qb->addSelect("{$alias}.json_value as " . $mapper->getQueryConverter()->fieldToAlias($name . 'Ids'));
    }

    protected function convertWhere(IEntity $entity, array $attribute, array $item): array
    {
        $where = [
            'type'  => 'and',
            'value' => []
        ];

        $values = (empty($item['value'])) ? [md5('no-such-value-' . time())] : $item['value'];

        foreach ($values as $value) {
            // escape slashes to search in escaped json
            $value = str_replace('\\', '\\\\\\\\', $value);
            $value = str_replace("/", "\\\\/", $value);
            $where['value'][] = [
                'type'      => $item['type'] === 'notLinkedWith' ? 'notLike' : 'like',
                'attribute' => 'jsonValue',
                'value'     => "%\"$value\"%"
            ];
        }
        return $where;
    }
}
