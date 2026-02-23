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

class ExtensibleMultiEnumType extends AbstractFieldType
{
    public function convert(IEntity $entity, array $row, array &$attributesDefs, bool $skipValueProcessing = false): void
    {
        $name = AttributeFieldConverter::prepareFieldName($row);

        $attributeData = @json_decode($row['data'], true)['field'] ?? null;

        $entity->fields[$name] = [
            'type'        => 'jsonArray',
            'name'        => $name,
            'attributeId' => $row['id'],
            'column'      => "json_value",
            'required'    => !empty($row['is_required']),
            'fullWidth'   => !empty($attributeData['fullWidth']),
            'modifiedExtendedDisabled' => !empty($row['modified_extended_disabled'])
        ];

        $entity->fields[$name . 'Names'] = [
            'type'        => 'jsonObject',
            'attributeId' => $row['id'],
            'notStorable' => true
        ];

        $entity->fields[$name . 'OptionsData'] = [
            'type'        => 'jsonArray',
            'attributeId' => $row['id'],
            'notStorable' => true
        ];

        if (empty($skipValueProcessing)) {
            $value = $row[$entity->fields[$name]['column']] ?? null;
            if ($value !== null) {
                $value = @json_decode((string)$value, true);
            }

            if (!empty($attributeData['dropdown'])) {
                $entity->set($name, is_array($value) ? $value : []);
            } else {
                $entity->set($name, !empty($value) ? $value : null);
            }

            if (!empty($entity->get($name))) {
                $options = $this->em
                    ->getRepository('ExtensibleEnumOption')
                    ->select(['id', 'name'])
                    ->where(['id' => $value])
                    ->find();

                if (!empty($options)) {
                    $entity->set($name . 'Names', array_column($options->toArray(), 'name', 'id'));
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
            'type'                      => 'extensibleMultiEnum',
            'required'                  => !empty($row['is_required']),
            'readOnly'                  => !empty($row['is_read_only']),
            'protected'                 => !empty($row['is_protected']),
            'label'                     => $row[$this->prepareKey('name', $row)],
            'dropdown'                  => !empty($attributeData['dropdown']),
            'extensibleEnumId'          => $row['extensible_enum_id'] ?? null,
            'allowedOptions'            => $attributeData['allowedOptions'] ?? null,
            'tooltip'                   => !empty($row[$this->prepareKey('tooltip', $row)]),
            'tooltipText'               => $row[$this->prepareKey('tooltip', $row)],
            'notSortable'               => true,
            'conditionalProperties'     => $this->prepareConditionalProperties($row),
            'modifiedExtendedDisabled' => !empty($row['modified_extended_disabled'])
        ];

        if (!empty($row['disable_field_value_lock'])) {
            $entity->entityDefs['fields'][$name]['disableFieldValueLock'] = true;
        }

        if (!empty($attributeData['dropdown'])) {
            $entity->entityDefs['fields'][$name]['view'] = "views/fields/extensible-multi-enum-dropdown";
        }

        $attributesDefs[$name] = $entity->entityDefs['fields'][$name];
    }

    public function select(array $row, string $alias, QueryBuilder $qb, Mapper $mapper, array $params): void
    {
        $name = AttributeFieldConverter::prepareFieldName($row);

        $qb->addSelect("{$alias}.json_value as " . $mapper->getQueryConverter()->fieldToAlias($name));
    }

    protected function convertWhere(IEntity $entity, array $attribute, array $item): array
    {
        if ($item['type'] === 'arrayIsEmpty') {
            $item = [
                'type'  => 'or',
                'value' => [
                    [
                        'type'      => 'isNull',
                        'attribute' => 'jsonValue'
                    ],
                    [
                        'type'      => 'equals',
                        'attribute' => 'jsonValue',
                        'value'     => ''
                    ],
                    [
                        'type'      => 'equals',
                        'attribute' => 'jsonValue',
                        'value'     => '[]'
                    ]
                ]
            ];
        } elseif ($item['type'] === 'arrayIsNotEmpty') {
            $item = [
                'type'  => 'or',
                'value' => [
                    [
                        'type'      => 'isNotNull',
                        'attribute' => 'jsonValue'
                    ],
                    [
                        'type'      => 'notEquals',
                        'attribute' => 'jsonValue',
                        'value'     => ''
                    ],
                    [
                        'type'      => 'notEquals',
                        'attribute' => 'jsonValue',
                        'value'     => '[]'
                    ]
                ]
            ];
        } else {
            $where = [
                'type'  => 'or',
                'value' => []
            ];

            $values = (empty($item['value'])) ? [md5('no-such-value-' . time())] : $item['value'];
            foreach ($values as $value) {
                // escape slashes to search in escaped json
                $value = str_replace('\\', '\\\\\\\\', $value);
                $value = str_replace("/", "\\\\/", $value);
                $where['value'][] = [
                    'type'      => 'like',
                    'attribute' => 'jsonValue',
                    'value'     => "%\"$value\"%"
                ];
            }
            $item = $where;
        }

        return $item;
    }

}
