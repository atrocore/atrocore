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

namespace Atro\Core;

use Atro\Core\Utils\Config;
use Atro\Core\Utils\Metadata;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\IEntity;

class AttributeValueConverter
{
    protected Metadata $metadata;
    protected Config $config;
    protected Connection $conn;

    public function __construct(Container $container)
    {
        $this->metadata = $container->get('metadata');
        $this->config = $container->get('config');
        $this->conn = $container->get('connection');
    }

    public function addAttributeValues(IEntity $entity): void
    {
        if (!$this->metadata->get("scopes.{$entity->getEntityType()}.hasAttribute")) {
            return;
        }

        $languages = [];
        if (!empty($this->config->get('isMultilangActive'))) {
            foreach ($this->config->get('inputLanguageList', []) as $code) {
                $languages[$code] = $code;
                foreach ($this->config->get('referenceData.Language', []) as $v) {
                    if ($code === $v['code']) {
                        $languages[$code] = $v['name'];
                        break;
                    }
                }
            }
        }

        $tableName = Util::toUnderScore(lcfirst($entity->getEntityType()));
        $res = $this->conn->createQueryBuilder()
            ->select('a.*, av.id as av_id, av.bool_value, av.date_value, av.datetime_value, av.int_value, av.int_value1, av.float_value, av.float_value1, av.varchar_value, av.text_value, av.reference_value, av.json_value, f.name as file_name')
            ->from("{$tableName}_attribute_value", 'av')
            ->leftJoin('av', $this->conn->quoteIdentifier('attribute'), 'a', 'a.id=av.attribute_id')
            ->leftJoin('av', $this->conn->quoteIdentifier('file'), 'f', 'f.id=av.reference_value AND a.type=:fileType')
            ->where('av.deleted=:false')
            ->andWhere('a.deleted=:false')
            ->andWhere("av.{$tableName}_id=:id")
            ->orderBy('a.sort_order', 'ASC')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('id', $entity->get('id'))
            ->setParameter('fileType', 'file')
            ->fetchAllAssociative();

        $attributeValues = [];

        foreach ($res as $row) {
            $id = $row['av_id'];
            $name = "attr_{$id}";

            $attributeRow = [
                'id'                            => $id,
                'attributeId'                   => $row['id'],
                'name'                          => $name,
                'label'                         => $row['name'],
                'type'                          => $row['type'],
                'trim'                          => !empty($row['trim']),
                'required'                      => !empty($row['is_required']),
                'notNull'                       => !empty($row['not_null']),
                'useDisabledTextareaInViewMode' => !empty($row['use_disabled_textarea_in_view_mode']),
                'amountOfDigitsAfterComma'      => $row['amount_of_digits_after_comma'] ?? null,
                'prohibitedEmptyValue'          => !empty($row['prohibited_empty_value']),
                'extensibleEnumId'              => $row['extensible_enum_id'] ?? null
            ];

            $attributeData = @json_decode($row['data'], true)['field'] ?? null;

            if (!empty($attributeData['entityType'])) {
                $attributeRow['entity'] = $attributeData['entityType'];
            }

            if (!empty($attributeData['maxLength'])) {
                $attributeRow['maxLength'] = $attributeData['maxLength'];
            }

            if (!empty($attributeData['countBytesInsteadOfCharacters'])) {
                $attributeRow['countBytesInsteadOfCharacters'] = $attributeData['countBytesInsteadOfCharacters'];
            }

            if (isset($attributeData['min'])) {
                $attributeRow['min'] = $attributeData['min'];
            }

            if (isset($attributeData['max'])) {
                $attributeRow['max'] = $attributeData['max'];
            }

            if (isset($row['measure_id'])) {
                $attributeRow['measureId'] = $row['measure_id'];
                $attributeRow['view'] = "views/fields/unit-{$row['type']}";
            }

            $dropdownTypes = $this->metadata->get(['app', 'attributeDropdownTypes'], []);
            if (!empty($row['dropdown']) && isset($dropdownTypes[$item['type']])) {
                $attributeRow['view'] = $dropdownTypes[$row['type']];
            }

            if ($row['type'] === 'rangeInt') {
                $attributeRow['view'] = 'views/fields/range-int';
            }

            if ($row['type'] === 'rangeFloat') {
                $attributeRow['view'] = 'views/fields/range-float';
            }

            $attributeValues[] = $attributeRow;
            if (!empty($row['is_multilang'])) {
                foreach ($languages as $language => $languageName) {
                    $attributeValues[] = array_merge($attributeRow, [
                        'name'  => $row['id'] . ucfirst(Util::toCamelCase(strtolower($language))),
                        'label' => $row['name'] . ' / ' . $languageName
                    ]);
                }
            }

            $entity->set('attributeValues', $attributeValues);

            switch ($row['type']) {
                case 'extensibleEnum':
                    $entity->fields[$name] = [
                        'type'             => 'varchar',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => "reference_value",
                        'required'         => !empty($row['is_required'])
                    ];
                    $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);

                    $entity->entityDefs['fields'][$name] = [
                        'type'                 => 'extensibleEnum',
                        'required'             => !empty($row['is_required']),
                        'label'                => $row['name'],
                        'prohibitedEmptyValue' => !empty($row['prohibited_empty_value']),
                        'dropdown'             => !empty($row['dropdown']),
                        'extensibleEnumId'     => $row['extensible_enum_id'] ?? null
                    ];
                    if (!empty($row['dropdown'])) {
                        $entity->entityDefs['fields'][$name]['view'] = "views/fields/extensible-enum-dropdown";
                    }
                    break;
                case 'extensibleMultiEnum':
                    $entity->fields[$name] = [
                        'type'             => 'jsonArray',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => "json_value",
                        'required'         => !empty($row['is_required'])
                    ];
                    $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);

                    $entity->entityDefs['fields'][$name] = [
                        'type'             => 'extensibleMultiEnum',
                        'required'         => !empty($row['is_required']),
                        'label'            => $row['name'],
                        'dropdown'         => !empty($row['dropdown']),
                        'extensibleEnumId' => $row['extensible_enum_id'] ?? null
                    ];
                    if (!empty($row['dropdown'])) {
                        $entity->entityDefs['fields'][$name]['view'] = "views/fields/extensible-multi-enum-dropdown";
                    }
                    break;
                case 'array':
                    $entity->fields[$name] = [
                        'type'             => 'jsonArray',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => "json_value",
                        'required'         => !empty($row['is_required'])
                    ];
                    $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);

                    $entity->entityDefs['fields'][$name] = [
                        'type'     => 'array',
                        'required' => !empty($row['is_required']),
                        'label'    => $row['name']
                    ];
                    break;
                case 'bool':
                    $entity->fields[$name] = [
                        'type'             => 'bool',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => "bool_value",
                        'required'         => !empty($row['is_required'])
                    ];
                    $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);

                    $entity->entityDefs['fields'][$name] = [
                        'type'     => 'bool',
                        'required' => !empty($row['is_required']),
                        'label'    => $row['name']
                    ];
                    break;
                case 'int':
                    $entity->fields[$name] = [
                        'type'             => 'int',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => "int_value",
                        'required'         => !empty($row['is_required'])
                    ];
                    $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);

                    $entity->entityDefs['fields'][$name] = [
                        'type'     => 'int',
                        'required' => !empty($row['is_required']),
                        'label'    => $row['name'],
                        'notNull'  => !empty($row['not_null']),
                    ];
                    if (isset($attributeData['min'])) {
                        $entity->entityDefs['fields'][$name]['min'] = $attributeData['min'];
                    }
                    if (isset($attributeData['max'])) {
                        $entity->entityDefs['fields'][$name]['max'] = $attributeData['max'];
                    }

                    $entity->fields[$name . 'UnitId'] = [
                        'type'             => 'varchar',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => 'reference_value',
                        'required'         => !empty($row['is_required'])
                    ];
                    $entity->set($name . 'UnitId', $row[$entity->fields[$name . 'UnitId']['column']] ?? null);
                    $entity->fields[$name . 'UnitName'] = [
                        'type'        => 'varchar',
                        'notStorable' => true
                    ];

                    if (isset($row['measure_id'])) {
                        $entity->entityDefs['fields'][$name]['measureId'] = $row['measure_id'];
                        $entity->entityDefs['fields'][$name]['view'] = "views/fields/unit-int'";
                        $entity->entityDefs['fields'][$name . 'Unit'] = [
                            "type"        => "link",
                            'label'       => "{$row['name']} (Unit)",
                            "view"        => "views/fields/unit-link",
                            "measureId"   => $row['measure_id'],
                            "entity"      => 'Unit',
                            "unitIdField" => true,
                            "mainField"   => $name,
                            'required'    => !empty($row['is_required'])
                        ];
                    }
                    break;
                case 'rangeInt':
                    $entity->fields[$name . 'From'] = [
                        'type'             => 'int',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => 'int_value',
                        'required'         => !empty($row['is_required'])
                    ];
                    $entity->set($name . 'From', $row[$entity->fields[$name . 'From']['column']] ?? null);

                    $entity->fields[$name . 'To'] = [
                        'type'             => 'int',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => 'int_value1',
                        'required'         => !empty($row['is_required'])
                    ];
                    $entity->set($name . 'To', $row[$entity->fields[$name . 'To']['column']] ?? null);

                    $entity->fields[$name . 'UnitId'] = [
                        'type'             => 'varchar',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => 'reference_value',
                        'required'         => !empty($row['is_required'])
                    ];
                    $entity->set($name . 'UnitId', $row[$entity->fields[$name . 'UnitId']['column']] ?? null);

                    $entity->fields[$name . 'UnitName'] = [
                        'type'        => 'varchar',
                        'notStorable' => true
                    ];

                    if (isset($row['measure_id'])) {
                        $entity->entityDefs['fields'][$name]['measureId'] = $row['measure_id'];
                        $entity->entityDefs['fields'][$name]['view'] = "views/fields/unit-int'";
                        $entity->entityDefs['fields'][$name . 'Unit'] = [
                            "type"        => "link",
                            'label'       => "{$row['name']} (Unit)",
                            "view"        => "views/fields/unit-link",
                            "measureId"   => $row['measure_id'],
                            "entity"      => 'Unit',
                            "unitIdField" => true,
                            "mainField"   => $name,
                            'required'    => !empty($row['is_required'])
                        ];
                    }
                    break;
                case 'float':
                    $entity->fields[$name] = [
                        'type'             => 'float',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => "float_value",
                        'required'         => !empty($row['is_required'])
                    ];
                    $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);

                    $entity->entityDefs['fields'][$name] = [
                        'type'     => 'float',
                        'required' => !empty($row['is_required']),
                        'label'    => $row['name'],
                    ];

                    $entity->fields[$name . 'UnitId'] = [
                        'type'             => 'varchar',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => 'reference_value',
                        'required'         => !empty($row['is_required'])
                    ];
                    $entity->fields[$name . 'UnitName'] = [
                        'type'        => 'varchar',
                        'notStorable' => true
                    ];
                    $entity->set($name . 'UnitId', $row[$entity->fields[$name . 'UnitId']['column']] ?? null);

                    $entity->entityDefs['fields'][$name . 'Unit'] = [
                        "type"        => "link",
                        'label'       => "{$row['name']} (Unit)",
                        "view"        => "views/fields/unit-link",
                        "measureId"   => $row['measure_id'],
                        "entity"      => 'Unit',
                        "unitIdField" => true,
                        "mainField"   => $name,
                        'required'    => !empty($row['is_required'])
                    ];
                    break;
                case 'rangeFloat':
                    $entity->fields[$name . 'From'] = [
                        'type'             => 'float',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => 'float_value',
                        'required'         => !empty($row['is_required'])
                    ];
                    $entity->set($name . 'From', $row[$entity->fields[$name . 'From']['column']] ?? null);

                    $entity->fields[$name . 'To'] = [
                        'type'             => 'float',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => 'float_value1',
                        'required'         => !empty($row['is_required'])
                    ];
                    $entity->set($name . 'To', $row[$entity->fields[$name . 'To']['column']] ?? null);

                    $entity->fields[$name . 'UnitId'] = [
                        'type'             => 'varchar',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => 'reference_value',
                        'required'         => !empty($row['is_required'])
                    ];
                    $entity->set($name . 'UnitId', $row[$entity->fields[$name . 'UnitId']['column']] ?? null);
                    break;
                case 'date':
                    $entity->fields[$name] = [
                        'type'             => 'date',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => "date_value",
                        'required'         => !empty($row['is_required'])
                    ];
                    $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);
                    break;
                case 'datetime':
                    $entity->fields[$name] = [
                        'type'             => 'datetime',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => "datetime_value",
                        'required'         => !empty($row['is_required'])
                    ];
                    $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);
                    break;
                case 'file':
                    $entity->fields[$name . 'Id'] = [
                        'type'             => 'varchar',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => 'reference_value',
                        'required'         => !empty($row['is_required'])
                    ];
                    $entity->set($name . 'Id', $row[$entity->fields[$name . 'Id']['column']] ?? null);

                    $entity->fields[$name . 'Name'] = [
                        'type'        => 'varchar',
                        'notStorable' => true
                    ];
                    $entity->fields[$name . 'PathsData'] = [
                        'type'        => 'jsonObject',
                        'notStorable' => true
                    ];
                    $entity->set($name . 'Name', $row['file_name'] ?? null);
                    break;
                case 'link':
                    $entity->fields[$name . 'Id'] = [
                        'type'             => 'varchar',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => 'reference_value',
                        'required'         => !empty($row['is_required'])
                    ];
                    $entity->set($name . 'Id', $row[$entity->fields[$name . 'Id']['column']] ?? null);

                    if (!empty($attributeData['entityType'])) {
                        $referenceTable = Util::toUnderScore(lcfirst($attributeData['entityType']));
                        try {
                            $referenceItem = $this->conn->createQueryBuilder()
                                ->select('id, name')
                                ->from($referenceTable)
                                ->where('id=:id')
                                ->andWhere('deleted=:false')
                                ->setParameter('id', $row['reference_value'])
                                ->setParameter('false', false, ParameterType::BOOLEAN)
                                ->fetchAssociative();

                            $entity->fields[$name . 'Name'] = [
                                'type'        => 'varchar',
                                'notStorable' => true
                            ];
                            $entity->set($name . 'Name', $referenceItem['name'] ?? null);
                        } catch (\Throwable $e) {
                            // ignore all
                        }
                    }
                    break;
                case 'text':
                case 'markdown':
                case 'wysiwyg':
                    $entity->fields[$name] = [
                        'type'             => 'text',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => "text_value",
                        'required'         => !empty($row['is_required'])
                    ];
                    $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);

                    if (!empty($row['is_multilang'])) {
                        foreach ($languages as $language => $languageName) {
                            $lName = $name . ucfirst(Util::toCamelCase(strtolower($language)));
                            $entity->fields[$lName] = [
                                'type'             => 'text',
                                'name'             => $name,
                                'attributeValueId' => $id,
                                'attributeId'      => $row['id'],
                                'attributeName'    => $row['name'] . ' / ' . $languageName,
                                'attributeType'    => $row['type'],
                                'column'           => "text_value_" . strtolower($language),
                                'required'         => !empty($row['is_required'])
                            ];
                            $entity->set($lName, $row[$entity->fields[$lName]['column']] ?? null);
                        }
                    }
                    break;
                case 'varchar':
                    $entity->fields[$name] = [
                        'type'             => 'varchar',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => "varchar_value",
                        'required'         => !empty($row['is_required'])
                    ];
                    $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);

                    if (!empty($row['is_multilang'])) {
                        foreach ($languages as $language => $languageName) {
                            $lName = $name . ucfirst(Util::toCamelCase(strtolower($language)));
                            $entity->fields[$lName] = [
                                'type'             => 'varchar',
                                'name'             => $name,
                                'attributeValueId' => $id,
                                'attributeId'      => $row['id'],
                                'attributeName'    => $row['name'] . ' / ' . $languageName,
                                'attributeType'    => $row['type'],
                                'column'           => "varchar_value_" . strtolower($language),
                                'required'         => !empty($row['is_required'])
                            ];
                            $entity->set($lName, $row[$entity->fields[$lName]['column']] ?? null);
                        }
                    }
                    break;
                default:
                    $entity->fields[$name] = [
                        'type'             => 'varchar',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => "varchar_value",
                        'required'         => !empty($row['is_required'])
                    ];
                    $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);
                    break;
            }
        }
    }
}