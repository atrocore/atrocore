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

use Atro\Core\AttributeFieldTypes\AttributeFieldTypeInterface;
use Atro\Core\Utils\Config;
use Atro\Core\Utils\Metadata;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\IEntity;

class AttributeFieldConverter
{
    protected Metadata $metadata;
    protected Config $config;
    protected Connection $conn;
    private Container $container;

    public function __construct(Container $container)
    {
        $this->metadata = $container->get('metadata');
        $this->config = $container->get('config');
        $this->conn = $container->get('connection');
        $this->container = $container;
    }

    public function putAttributesToEntity(IEntity $entity): void
    {
        if (!$this->metadata->get("scopes.{$entity->getEntityType()}.hasAttribute")) {
            return;
        }

        $tableName = Util::toUnderScore(lcfirst($entity->getEntityType()));

        $select = 'a.*, av.id as av_id, av.bool_value, av.date_value, av.datetime_value, av.int_value, av.int_value1, av.float_value, av.float_value1, av.varchar_value, av.text_value, av.reference_value, av.json_value, f.name as file_name';
        if (!empty($this->config->get('isMultilangActive'))) {
            foreach ($this->config->get('inputLanguageList', []) as $code) {
                $select .= ',av.varchar_value_' . strtolower($code);
                $select .= ',av.text_value_' . strtolower($code);
            }
        }

        $res = $this->conn->createQueryBuilder()
            ->select($select)
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

        $attributesDefs = [];

        foreach ($res as $row) {
            $id = $row['av_id'];
            $name = "attr_{$id}";

            $className = "\\Atro\\Core\\AttributeFieldTypes\\" . ucfirst($row['type']) . "Type";
            if (!class_exists($className)) {
                $className = "\\Atro\\Core\\AttributeFieldTypes\\VarcharType";
            }

            if (is_a($className, AttributeFieldTypeInterface::class, true)) {
                $this->container->get($className)->convert($entity, $id, $name, $row, $attributesDefs);
            }

//            $attributeRow = [
//                'id'                            => $id,
//                'attributeId'                   => $row['id'],
//                'name'                          => $name,
//                'label'                         => $row['name'],
//                'type'                          => $row['type'],
//                'trim'                          => !empty($row['trim']),
//                'required'                      => !empty($row['is_required']),
//                'notNull'                       => !empty($row['not_null']),
//                'useDisabledTextareaInViewMode' => !empty($row['use_disabled_textarea_in_view_mode']),
//                'amountOfDigitsAfterComma'      => $row['amount_of_digits_after_comma'] ?? null,
//                'prohibitedEmptyValue'          => !empty($row['prohibited_empty_value']),
//                'extensibleEnumId'              => $row['extensible_enum_id'] ?? null
//            ];
//
//            $attributeData = @json_decode($row['data'], true)['field'] ?? null;
//
//            if (!empty($attributeData['entityType'])) {
//                $attributeRow['entity'] = $attributeData['entityType'];
//            }
//
//            if (!empty($attributeData['maxLength'])) {
//                $attributeRow['maxLength'] = $attributeData['maxLength'];
//            }
//
//            if (!empty($attributeData['countBytesInsteadOfCharacters'])) {
//                $attributeRow['countBytesInsteadOfCharacters'] = $attributeData['countBytesInsteadOfCharacters'];
//            }
//
//            if (isset($attributeData['min'])) {
//                $attributeRow['min'] = $attributeData['min'];
//            }
//
//            if (isset($attributeData['max'])) {
//                $attributeRow['max'] = $attributeData['max'];
//            }
//
//            if (isset($row['measure_id'])) {
//                $attributeRow['measureId'] = $row['measure_id'];
//                $attributeRow['view'] = "views/fields/unit-{$row['type']}";
//            }
//
//            $dropdownTypes = $this->metadata->get(['app', 'attributeDropdownTypes'], []);
//            if (!empty($row['dropdown']) && isset($dropdownTypes[$item['type']])) {
//                $attributeRow['view'] = $dropdownTypes[$row['type']];
//            }
//
//            if ($row['type'] === 'rangeInt') {
//                $attributeRow['view'] = 'views/fields/range-int';
//            }
//
//            if ($row['type'] === 'rangeFloat') {
//                $attributeRow['view'] = 'views/fields/range-float';
//            }
//
//            $entity->set('attributeValues', $attributeValues);

//            switch ($row['type']) {
////                case 'rangeInt':
////                    $entity->fields[$name . 'From'] = [
////                        'type'             => 'int',
////                        'name'             => $name,
////                        'attributeValueId' => $id,
////                        'attributeId'      => $row['id'],
////                        'attributeName'    => $row['name'],
////                        'attributeType'    => $row['type'],
////                        'column'           => 'int_value',
////                        'required'         => !empty($row['is_required'])
////                    ];
////                    $entity->set($name . 'From', $row[$entity->fields[$name . 'From']['column']] ?? null);
////
////                    $entity->fields[$name . 'To'] = [
////                        'type'             => 'int',
////                        'name'             => $name,
////                        'attributeValueId' => $id,
////                        'attributeId'      => $row['id'],
////                        'attributeName'    => $row['name'],
////                        'attributeType'    => $row['type'],
////                        'column'           => 'int_value1',
////                        'required'         => !empty($row['is_required'])
////                    ];
////                    $entity->set($name . 'To', $row[$entity->fields[$name . 'To']['column']] ?? null);
////
////                    $entity->fields[$name . 'UnitId'] = [
////                        'type'             => 'varchar',
////                        'name'             => $name,
////                        'attributeValueId' => $id,
////                        'attributeId'      => $row['id'],
////                        'attributeName'    => $row['name'],
////                        'attributeType'    => $row['type'],
////                        'column'           => 'reference_value',
////                        'required'         => !empty($row['is_required'])
////                    ];
////                    $entity->set($name . 'UnitId', $row[$entity->fields[$name . 'UnitId']['column']] ?? null);
////
////                    $entity->fields[$name . 'UnitName'] = [
////                        'type'        => 'varchar',
////                        'notStorable' => true
////                    ];
////
////                    if (isset($row['measure_id'])) {
////                        $entity->entityDefs['fields'][$name]['measureId'] = $row['measure_id'];
////                        $entity->entityDefs['fields'][$name]['view'] = "views/fields/unit-int'";
////                        $entity->entityDefs['fields'][$name . 'Unit'] = [
////                            "type"        => "link",
////                            'label'       => "{$row['name']} (Unit)",
////                            "view"        => "views/fields/unit-link",
////                            "measureId"   => $row['measure_id'],
////                            "entity"      => 'Unit',
////                            "unitIdField" => true,
////                            "mainField"   => $name,
////                            'required'    => !empty($row['is_required'])
////                        ];
////                    }
////                    break;
////                case 'rangeFloat':
////                    $entity->fields[$name . 'From'] = [
////                        'type'             => 'float',
////                        'name'             => $name,
////                        'attributeValueId' => $id,
////                        'attributeId'      => $row['id'],
////                        'attributeName'    => $row['name'],
////                        'attributeType'    => $row['type'],
////                        'column'           => 'float_value',
////                        'required'         => !empty($row['is_required'])
////                    ];
////                    $entity->set($name . 'From', $row[$entity->fields[$name . 'From']['column']] ?? null);
////
////                    $entity->fields[$name . 'To'] = [
////                        'type'             => 'float',
////                        'name'             => $name,
////                        'attributeValueId' => $id,
////                        'attributeId'      => $row['id'],
////                        'attributeName'    => $row['name'],
////                        'attributeType'    => $row['type'],
////                        'column'           => 'float_value1',
////                        'required'         => !empty($row['is_required'])
////                    ];
////                    $entity->set($name . 'To', $row[$entity->fields[$name . 'To']['column']] ?? null);
////
////                    $entity->fields[$name . 'UnitId'] = [
////                        'type'             => 'varchar',
////                        'name'             => $name,
////                        'attributeValueId' => $id,
////                        'attributeId'      => $row['id'],
////                        'attributeName'    => $row['name'],
////                        'attributeType'    => $row['type'],
////                        'column'           => 'reference_value',
////                        'required'         => !empty($row['is_required'])
////                    ];
////                    $entity->set($name . 'UnitId', $row[$entity->fields[$name . 'UnitId']['column']] ?? null);
////                    break;
////                case 'date':
////                    $entity->fields[$name] = [
////                        'type'             => 'date',
////                        'name'             => $name,
////                        'attributeValueId' => $id,
////                        'attributeId'      => $row['id'],
////                        'attributeName'    => $row['name'],
////                        'attributeType'    => $row['type'],
////                        'column'           => "date_value",
////                        'required'         => !empty($row['is_required'])
////                    ];
////                    $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);
////                    break;
////                case 'datetime':
////                    $entity->fields[$name] = [
////                        'type'             => 'datetime',
////                        'name'             => $name,
////                        'attributeValueId' => $id,
////                        'attributeId'      => $row['id'],
////                        'attributeName'    => $row['name'],
////                        'attributeType'    => $row['type'],
////                        'column'           => "datetime_value",
////                        'required'         => !empty($row['is_required'])
////                    ];
////                    $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);
////                    break;
//            }
        }

        $entity->set('attributesDefs', $attributesDefs);
        $entity->setAsFetched();
    }
}