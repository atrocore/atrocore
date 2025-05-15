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
    private Container $container;

    public function __construct(Container $container)
    {
        $this->metadata = $container->get('metadata');
        $this->config = $container->get('config');
        $this->conn = $container->get('connection');
        $this->container = $container;
    }

    public static function prepareFieldName(string $id): string
    {
        return $id;
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

            $this->prepareSelect($attribute, $attributeAlias, $qb, $mapper);
            $this->convert($entity, $attribute, $attributesDefs);
        }

        $entity->set('attributesDefs', $attributesDefs);
    }

    public function putAttributesToEntity(IEntity $entity): void
    {
        if (!$this->metadata->get("scopes.{$entity->getEntityType()}.hasAttribute")) {
            return;
        }

        $tableName = Util::toUnderScore(lcfirst($entity->getEntityType()));

        $select = [
            'a.*',
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
            'f.name as file_name'
        ];

        if (!empty($this->config->get('isMultilangActive'))) {
            foreach ($this->config->get('inputLanguageList', []) as $code) {
                $select[] = 'av.varchar_value_' . strtolower($code);
                $select[] = 'av.text_value_' . strtolower($code);
            }
        }

        $res = $this->conn->createQueryBuilder()
            ->select(implode(',', $select))
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

        if (!empty($res) && $this->metadata->get("scopes.{$entity->getEntityType()}.hasClassification")) {
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

                        $attributeData = @json_decode($attribute['data'] ?? '', true);
                        if (empty($attributeData)) {
                            $attributeData = [];
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

        $attributesDefs = [];

        foreach ($res as $row) {
            $this->convert($entity, $row, $attributesDefs);
        }

        $entity->set('attributesDefs', $attributesDefs);
        $entity->setAsFetched();

        foreach ($entity->_originalInput->__attributes ?? [] as $attributeId) {
            foreach ($attributesDefs as $name => $defs) {
                if (!empty($defs['attributeId']) && $defs['attributeId'] === $attributeId) {
                    $entity->unsetFetched($name);
                }
            }
        }
    }

    public function getAttributesRowsByIds(array $attributesIds): array
    {
        return $this->conn->createQueryBuilder()
            ->select('*')
            ->from($this->conn->quoteIdentifier('attribute'))
            ->where('id IN (:ids)')
            ->andWhere('deleted=:false')
            ->setParameter('ids', $attributesIds, Connection::PARAM_STR_ARRAY)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();
    }

    public function prepareSelect(array $attribute, string $alias, QueryBuilder $qb, Mapper $mapper): void
    {
        $this->getFieldType($attribute['type'])->select($attribute, $alias, $qb, $mapper);
    }

    public function convert(IEntity $entity, array $attribute, array &$attributesDefs): void
    {
        $this->getFieldType($attribute['type'])->convert($entity, $attribute, $attributesDefs);
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