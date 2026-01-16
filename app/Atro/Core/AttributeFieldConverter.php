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
use Atro\Core\EventManager\Event;
use Atro\Core\EventManager\Manager;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Utils\Config;
use Atro\Core\Utils\Metadata;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\IEntity;

class AttributeFieldConverter
{
    protected Metadata $metadata;
    protected Config $config;
    protected Connection $conn;
    protected Manager $eventManager;
    private Container $container;
    private array $attributes = [];

    public function __construct(Container $container)
    {
        $this->metadata = $container->get('metadata');
        $this->config = $container->get('config');
        $this->conn = $container->get('connection');
        $this->eventManager = $container->get('eventManager');
        $this->container = $container;
    }

    public static function isValidCode(string $code): bool
    {
        return preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $code) === 1;
    }

    public static function prepareFieldName(array $row): string
    {
        if (!empty($row['code']) && self::isValidCode($row['code'])) {
            return $row['code'];
        }

        return $row['id'];
    }

    public static function getAttributeIdFromFieldName(string $name): string
    {
        $id = $name;

        if (str_ends_with($id, 'UnitId')) {
            $id = substr($id, 0, -6);
        } elseif (str_ends_with($id, 'From')) {
            $id = substr($id, 0, -4);
        } elseif (str_ends_with($id, 'Id') || str_ends_with($id, 'To')) {
            $id = substr($id, 0, -2);
        }

        return $id;
    }

    public function getWherePart(IEntity $entity, array &$item, array &$result): void
    {
        $id = self::getAttributeIdFromFieldName($item['attribute']);

        if (!in_array($id, $result['attributesIds'] ?? [])) {
            $result['attributesIds'][] = $id;
        }

        if (!isset($this->attributes[$id])) {
            $this->attributes = [];
            $attributeIds = [];
            foreach ($result['attributesIds'] as $attributeId) {
                $attributeIds[] = self::getAttributeIdFromFieldName($attributeId);
            }

            $attributes = $this->conn->createQueryBuilder()
                ->select('*')
                ->from($this->conn->quoteIdentifier('attribute'))
                ->where('id IN (:ids)')
                ->andWhere('deleted = :false')
                ->setParameter('ids', array_unique($attributeIds), Connection::PARAM_INT_ARRAY)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();

            foreach ($attributes as $attribute) {
                $this->attributes[$attribute['id']] = $attribute;
            }

            if (empty($this->attributes[$id])) {
                throw new BadRequest('The attribute "' . $id . '" does not exist.');
            }
        }

        $attribute = $this->attributes[$id];

        $this->getFieldType($attribute['type'])->getWherePart($entity, $attribute, $item);

    }

    public function putAttributesToSelect(QueryBuilder $qb, IEntity $entity, array $params, Mapper $mapper): void
    {
        if (!empty($params['aggregation']) || empty($params['attributesIds'])) {
            return;
        }

        $attributes = $this->getAttributesRowsByIds($params['attributesIds']);
        if (empty($attributes)) {
            return;
        }

        $attributesDefs = [];

        $tableName = Util::toUnderScore(lcfirst($entity->getEntityType()));
        $alias = $mapper->getQueryConverter()::TABLE_ALIAS;
        foreach ($attributes as $attribute) {
            $attributeAlias = Util::generateId();
            $qb->leftJoin(
                $alias,
                "{$tableName}_attribute_value",
                $attributeAlias,
                "{$attributeAlias}.{$tableName}_id={$alias}.id AND {$attributeAlias}.deleted=:false AND {$attributeAlias}.attribute_id=:{$attributeAlias}AttributeId"
            );
            $qb->setParameter("{$attributeAlias}AttributeId", $attribute['id']);
            $qb->setParameter('false', false, ParameterType::BOOLEAN);

            $this->prepareSelect($attribute, $attributeAlias, $qb, $mapper, $params);
            $this->convert($entity, $attribute, $attributesDefs, true);
        }

        $entity->set('attributesDefs', $attributesDefs);
    }

    public function putAdditionalDataAfterSelect(IEntity $entity, array $attributeIds): void
    {
        foreach ($entity->entityDefs['fields'] as $key => $defs) {
            if (!empty($defs['attributeId']) && in_array($defs['attributeId'], $attributeIds)) {
                $entity->entityDefs['fields'][$key]['attributeValueId'] = $entity->rowData[$this->getAttributeValueIdField($defs['mainField'] ?? $defs['multilangField'] ?? $key)] ?? null;
            }
        }
    }

    public function putAttributesToEntity(IEntity $entity): void
    {
        if ($entity->hasAllEntityAttributes || !$this->metadata->get("scopes.{$entity->getEntityType()}.hasAttribute")) {
            return;
        }

        $tableName = Util::toUnderScore(lcfirst($entity->getEntityType()));

        $select = [
            'a.*',
            'av.id as av_id',
            'av.bool_value',
            'av.date_value',
            'av.datetime_value',
            'av.int_value',
            'av.int_value1',
            'av.float_value',
            'av.float_value1',
            'av.varchar_value',
            'av.text_value',
            'av.reference_value',
            'av.json_value',
            'f.name as file_name',
            'eeo.name as extensible_enum_option_name',
            'ag.id as attribute_group_id',
            'ag.name as attribute_group_name',
            'ag.sort_order as attribute_group_sort_order',
            'a.sort_order as sort_order',
            'a.attribute_group_sort_order as sort_order_in_attribute_group',
            'a.modified_extended_disabled as modified_extended_disabled'
        ];

        if (class_exists("\\Pim\\Module")) {
            $select[] = 'c.name as channel_name';
        }

        if (!empty($this->config->get('isMultilangActive'))) {
            foreach ($this->config->get('inputLanguageList', []) as $code) {
                $select[] = 'av.varchar_value_' . strtolower($code);
                $select[] = 'av.text_value_' . strtolower($code);
            }
        }

        $qb = $this->conn->createQueryBuilder()
            ->select(implode(',', $select))
            ->from("{$tableName}_attribute_value", 'av')
            ->innerJoin('av', $this->conn->quoteIdentifier('attribute'), 'a', 'a.id=av.attribute_id AND a.deleted=:false')
            ->leftJoin('a', 'attribute_group', 'ag', 'ag.id=a.attribute_group_id AND ag.deleted=:false')
            ->leftJoin('av', $this->conn->quoteIdentifier('file'), 'f', 'f.id=av.reference_value AND a.type=:fileType AND f.deleted=:false')
            ->leftJoin('av', $this->conn->quoteIdentifier('extensible_enum_option'), 'eeo', 'eeo.id=av.reference_value AND a.type=:eeType AND eeo.deleted=:false')
            ->where('av.deleted=:false')
            ->andWhere("av.{$tableName}_id=:id")
            ->orderBy('a.sort_order', 'ASC')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('id', $entity->get('id'))
            ->setParameter('fileType', 'file')
            ->setParameter('eeType', 'extensibleEnum');

        if (class_exists("\\Pim\\Module")) {
            $qb->leftJoin('a', $this->conn->quoteIdentifier('channel'), 'c', 'c.id=a.channel_id AND c.deleted=:false');
        }

        $res = $qb->fetchAllAssociative();

        foreach ($res as $k => $attribute) {
            if (!empty($attribute['channel_name'])) {
                $res[$k]['name'] = $attribute['name'] . ' / ' . $attribute['channel_name'];
            }
        }

        if (!empty($res) && $this->metadata->get("scopes.{$entity->getEntityType()}.hasClassification")) {
            $propertyFields = [];
            foreach ($this->metadata->get('entityDefs.ClassificationAttribute.fields', []) as $f => $fDefs) {
                if (!empty($fDefs['fieldProperty'])) {
                    $propertyFields[] = $f;
                }
            }

            $classificationAttrs = $this->conn->createQueryBuilder()
                ->select('ca.*')
                ->from("{$tableName}_classification", 'r')
                ->innerJoin('r', 'classification', 'c', 'c.id=r.classification_id AND c.deleted=:false')
                ->leftJoin('c', 'classification_attribute', 'ca', 'c.id=ca.classification_id AND ca.deleted=:false')
                ->where("r.{$tableName}_id=:id")
                ->andWhere('r.deleted=:false')
                ->andWhere('ca.attribute_id IN (:attributesIds)')
                ->orderBy('ca.is_required', 'ASC')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setParameter('id', $entity->get('id'))
                ->setParameter('attributesIds', array_column($res, 'id'), $this->conn::PARAM_STR_ARRAY)
                ->fetchAllAssociative();

            foreach ($res as $k => $attribute) {
                foreach ($classificationAttrs as $classificationAttribute) {
                    if ($attribute['id'] === $classificationAttribute['attribute_id']) {
                        $res[$k]['classification_attribute_id'] = $classificationAttribute['id'];
                        $res[$k]['is_required'] = $classificationAttribute['is_required'];
                        $res[$k]['is_read_only'] = $classificationAttribute['is_read_only'];
                        $res[$k]['is_protected'] = $classificationAttribute['is_protected'];

                        foreach (['conditional_required', 'conditional_visible', 'conditional_protected', 'conditional_read_only', 'conditional_disable_options'] as $key) {
                            if (!empty($classificationAttribute["enable_$key"])) {
                                $res[$k][$key] = $classificationAttribute[$key];
                            }
                        }

                        $attributeData = @json_decode($attribute['data'] ?? '', true);
                        if (empty($attributeData)) {
                            $attributeData = [];
                        }

                        foreach ($propertyFields as $field) {
                            $attributeData['field'][$field] = null;
                        }

                        $classificationAttributeData = @json_decode($classificationAttribute['data'] ?? '', true);
                        if (!empty($classificationAttributeData['field'])) {
                            foreach ($classificationAttributeData['field'] as $param => $paramValue) {
                                $attributeData['field'][$param] = $paramValue;
                            }
                        }

                        $res[$k]['data'] = json_encode($attributeData);
                    }
                }
            }
        }

        // it needs because we should be able to create attribute value on entity update
        if (!empty($entity->_originalInput)) {
            $attributesIds = $entity->_originalInput->__attributes ?? [];
            foreach ($entity->_originalInput as $field => $value) {
                $attributeId = $this->metadata->get("entityDefs.{$entity->getEntityType()}.fields.{$field}.attributeId");
                if ($attributeId) {
                    $attributesIds[] = $attributeId;
                }
            }

            if (!empty($entity->_originalInput->attributesDefs)) {
                foreach ($entity->_originalInput->attributesDefs as $defs) {
                    if (!in_array($defs->attributeId, $attributesIds)) {
                        $attributesIds[] = $defs->attributeId;
                    }
                }
            }

            if (!empty($entity->_originalInput->attributesValues)) {
                foreach ($entity->_originalInput->attributesValues as $attributeValue) {
                    if (!empty($attributeValue->attributeId) && !in_array($attributeValue->attributeId, $attributesIds)) {
                        $attributesIds[] = $attributeValue->attributeId;
                    }
                }
            }


            $preparedAttributesIds = [];
            foreach ($attributesIds as $attributeId) {
                if (!in_array($attributeId, array_column($res, 'id'))) {
                    $preparedAttributesIds[] = $attributeId;
                }
            }

            if (!empty($preparedAttributesIds)) {
                $attrs = $this->conn->createQueryBuilder()
                    ->select('*')
                    ->from($this->conn->quoteIdentifier('attribute'))
                    ->where('id IN (:ids)')
                    ->setParameter('ids', $preparedAttributesIds, $this->conn::PARAM_STR_ARRAY)
                    ->fetchAllAssociative();

                foreach ($attrs as $attr) {
                    $res[] = array_merge($attr, ['entity_id' => $entity->get('id')]);
                }
            }
        }

        $attributePanelsIds = array_column($this->config->get('referenceData.AttributePanel', []), 'id');

        $attributesDefs = [];

        $isDerivative = !empty($this->metadata->get("scopes.{$entity->getEntityType()}.primaryEntityId"));

        foreach ($res as $row) {
            // set null if attribute-panel does not exist
            if (!empty($row['attribute_panel_id']) && !in_array($row['attribute_panel_id'], $attributePanelsIds)) {
                $row['attribute_panel_id'] = null;
            }

            // remove required property for derivatives
            if ($isDerivative) {
                if (!empty($row['is_required'])) {
                    $row['is_required'] = false;
                }
                if (!empty($row['conditional_required'])) {
                    unset($row['conditional_required']);
                }
            }
            $this->convert($entity, $row, $attributesDefs);
        }

        $attributesDefs = $this->eventManager->dispatch('AttributeFieldConverter', 'afterPutAttributesToEntity', new Event(['entity' => $entity, 'attributes' => $res, 'attributesDefs' => $attributesDefs]))
            ->getArgument('attributesDefs');

        $entity->set('attributesDefs', $attributesDefs);
        $entity->setAsFetched();

        foreach ($entity->_originalInput->__attributes ?? [] as $attributeId) {
            foreach ($entity->fields ?? [] as $name => $defs) {
                if (!empty($defs['attributeId']) && $defs['attributeId'] === $attributeId) {
                    $entity->unsetFetched($name);
                }
            }
        }

        $entity->hasAllEntityAttributes = true;
    }

    public function prepareInputForAttributesValuesArray(IEntity $entity, \stdClass $input): void
    {
        if (!isset($input->attributesValues)) {
            return;
        }

        // flat attribute values from array
        foreach ($input->attributesValues ?? [] as $attributeValue) {
            if (!empty($attributeValue->attributeId)) {
                $attributeField = null;
                foreach ($entity->entityDefs['fields'] as $field => $defs) {
                    if (!empty($defs['attributeId']) && $defs['attributeId'] === $attributeValue->attributeId) {
                        $attributeField = $field;
                        break;
                    }
                }

                if (empty($attributeField)) {
                    continue;
                }

                foreach ($entity->fields ?? [] as $name => $defs) {
                    if (!empty($defs['attributeId']) && $defs['attributeId'] === $attributeValue->attributeId) {
                        $property = str_replace($attributeField, 'value', $name);
                        if (property_exists($attributeValue, $property)) {
                            $input->$name = $attributeValue->$property;;
                        }
                    }
                }
            }
        }

        unset($input->attributesValues);
    }

    public function getAttributesRowsByIds(array $attributesIds): array
    {
        if (class_exists("\\Pim\\Module")) {
            return $this->conn->createQueryBuilder()
                ->select('a.*, c.name as channel_name')
                ->from($this->conn->quoteIdentifier('attribute'), 'a')
                ->leftJoin('a', $this->conn->quoteIdentifier('channel'), 'c', 'c.id = a.channel_id AND c.deleted=:false')
                ->where('a.id IN (:ids) or a.code IN (:ids)')
                ->andWhere('a.deleted=:false')
                ->setParameter('ids', $attributesIds, Connection::PARAM_STR_ARRAY)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();
        }

        return $this->conn->createQueryBuilder()
            ->select('*')
            ->from($this->conn->quoteIdentifier('attribute'))
            ->where('id IN (:ids) or code IN (:ids)')
            ->andWhere('deleted=:false')
            ->setParameter('ids', $attributesIds, Connection::PARAM_STR_ARRAY)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();
    }

    public function prepareSelect(array $attribute, string $alias, QueryBuilder $qb, Mapper $mapper, array $params): void
    {
        // Add attribute value id to know if attribute is linked
        $qb->addSelect("$alias.id as " . $mapper->getQueryConverter()->fieldToAlias($this->getAttributeValueIdField(AttributeFieldConverter::prepareFieldName($attribute))));

        $this->getFieldType($attribute['type'])->select($attribute, $alias, $qb, $mapper, $params);
    }

    public function getAttributeValueIdField(string $fieldName): string
    {
        return $fieldName . 'AvId';
    }

    public function convert(IEntity $entity, array $attribute, array &$attributesDefs, bool $skipValueProcessing = false): void
    {
        $this->getFieldType($attribute['type'])->convert($entity, $attribute, $attributesDefs, $skipValueProcessing);
    }

    public function getFieldType(string $type): AttributeFieldTypeInterface
    {
        $className = $this->metadata->get("app.attributeFieldConverter.{$type}");
        if (!class_exists($className)) {
            $className = "\\Atro\\Core\\AttributeFieldTypes\\" . ucfirst($type) . "Type";
            if (!class_exists($className)) {
                $className = "\\Atro\\Core\\AttributeFieldTypes\\VarcharType";
            }
        }

        if (!is_a($className, AttributeFieldTypeInterface::class, true)) {
            throw new Error("No such attribute field type '$type'.");
        }

        return $this->container->get($className);
    }
}