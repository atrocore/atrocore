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
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Utils\Config;
use Atro\Core\Utils\IdGenerator;
use Atro\Core\Utils\Language;
use Atro\Core\Utils\Metadata;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\Core\Acl;
use Espo\ORM\IEntity;

class AttributeFieldConverter
{
    protected Metadata $metadata;
    protected Config $config;
    protected Connection $conn;
    protected Manager $eventManager;
    private Container $container;
    private array $attributes = [];
    private ?array $subtypeColumns = null;

    public function __construct(Container $container)
    {
        $this->metadata     = $container->get('metadata');
        $this->config       = $container->get('config');
        $this->conn         = $container->get('connection');
        $this->eventManager = $container->get('eventManager');
        $this->container    = $container;
    }

    /**
     * The settings of each attribute type: the table of its subtype, the alias of the table in the queries of the
     * attributes and the columns of the fields of the subtype.
     */
    public function getSubtypeColumns(): array
    {
        if ($this->subtypeColumns !== null) {
            return $this->subtypeColumns;
        }

        $this->subtypeColumns = [];
        foreach ($this->metadata->get('scopes', []) as $scope => $scopeDefs) {
            if (($scopeDefs['type'] ?? null) !== 'Subtype' || ($scopeDefs['supertypeId'] ?? null) !== 'Attribute') {
                continue;
            }

            $columns = [];
            foreach ($this->metadata->get(['entityDefs', $scope, 'fields'], []) as $field => $defs) {
                if ($field === 'supertype' || !empty($defs['supertypeField']) || !empty($defs['notStorable'])
                    || ($defs['type'] ?? null) === 'linkMultiple') {
                    continue;
                }
                $columns[] = Util::toUnderScore($field) . (($defs['type'] ?? null) === 'link' ? '_id' : '');
            }

            $this->subtypeColumns[lcfirst(substr($scope, strlen('AttributeType')))] = [
                'table'   => Util::toUnderScore(lcfirst($scope)),
                'alias'   => 'st' . count($this->subtypeColumns),
                'columns' => $columns,
            ];
        }

        return $this->subtypeColumns;
    }

    /**
     * The settings of the attribute types are stored in the tables of the subtypes, they are joined to the query of
     * the attributes.
     */
    public function joinSubtypes(QueryBuilder $qb, string $alias): void
    {
        foreach ($this->getSubtypeColumns() as $subtype) {
            $qb->leftJoin($alias, $this->conn->quoteIdentifier($subtype['table']), $subtype['alias'], "{$subtype['alias']}.supertype_id = $alias.id");
            foreach ($subtype['columns'] as $column) {
                $qb->addSelect("{$subtype['alias']}." . $this->conn->quoteIdentifier($column) . " AS {$subtype['alias']}__$column");
            }
        }
    }

    /**
     * The value of a setting of the attribute types in a query with the joined subtypes: the column of the subtype that
     * has it. Null when no attribute type has such a setting.
     */
    public function getSubtypeColumnExpression(string $column): ?string
    {
        $parts = [];
        foreach ($this->getSubtypeColumns() as $subtype) {
            if (in_array($column, $subtype['columns'])) {
                $parts[] = "{$subtype['alias']}." . $this->conn->quoteIdentifier($column);
            }
        }

        if (empty($parts)) {
            return null;
        }

        return count($parts) === 1 ? $parts[0] : 'COALESCE(' . implode(', ', $parts) . ')';
    }

    /**
     * Puts the settings of the type of the attribute into its row under the names of their columns.
     */
    public function prepareSubtypeColumns(array $row): array
    {
        $own = $this->getSubtypeColumns()[$row['type'] ?? ''] ?? null;

        foreach ($this->getSubtypeColumns() as $subtype) {
            foreach ($subtype['columns'] as $column) {
                $key = "{$subtype['alias']}__$column";
                if ($own === $subtype) {
                    $row[$column] = $row[$key] ?? null;
                } elseif (!in_array($column, $own['columns'] ?? [])) {
                    unset($row[$column]);
                }
                unset($row[$key]);
            }
        }

        return $row;
    }

    public static function isValidSystemName(string $name): bool
    {
        return preg_match('/^[a-z]([a-zA-Z0-9_]*[a-zA-Z0-9])?$/', $name) === 1;
    }

    public static function prepareFieldName(array $row): string
    {
        return $row['system_name'];
    }

    public static function getAttributeIdFromFieldName(string $name): string
    {
        $id = $name;

        if (str_ends_with($id, 'PrefixId')) {
            $id = substr($id, 0, -8);
        } elseif (str_ends_with($id, 'UnitId')) {
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
            $attributeIds     = [];
            foreach ($result['attributesIds'] as $attributeId) {
                $attributeIds[] = self::getAttributeIdFromFieldName($attributeId);
            }

            $qb = $this->conn->createQueryBuilder()
                ->select('a.*')
                ->from($this->conn->quoteIdentifier('attribute'), 'a')
                ->where('a.id IN (:ids)')
                ->andWhere('a.deleted = :false')
                ->setParameter('ids', array_unique($attributeIds), Connection::PARAM_INT_ARRAY)
                ->setParameter('false', false, ParameterType::BOOLEAN);
            $this->joinSubtypes($qb, 'a');

            foreach ($qb->fetchAllAssociative() as $attribute) {
                $this->attributes[$attribute['id']] = $this->prepareSubtypeColumns($attribute);
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

        $tableName = Util::toUnderScore(lcfirst($entity->getEntityName()));
        $alias     = $mapper->getQueryConverter()::TABLE_ALIAS;
        foreach ($attributes as $attribute) {
            $attributeAlias = IdGenerator::unsortableId();
            $qb->leftJoin(
                $alias,
                "{$tableName}_attribute_value",
                $attributeAlias,
                "{$attributeAlias}.{$tableName}_id={$alias}.id AND {$attributeAlias}.attribute_id=:{$attributeAlias}AttributeId"
            );
            $qb->setParameter("{$attributeAlias}AttributeId", $attribute['id']);
            $qb->setParameter('false', false, ParameterType::BOOLEAN);

            $this->prepareSelect($attribute, $attributeAlias, $qb, $mapper, $params);
            $this->convert($entity, $attribute, $attributesDefs, true);
        }

        $entity->set('attributesDefs', $attributesDefs);
    }

    public function putAdditionalDataAfterSelect(IEntity $entity, array $attributeIds, array $dataArr): void
    {
        $attributesDefs = $entity->get('attributesDefs');

        foreach ($entity->entityDefs['fields'] as $key => $defs) {
            if (!empty($defs['attributeId']) && in_array($defs['attributeId'], $attributeIds)) {
                $entity->entityDefs['fields'][$key]['attributeValueId'] = $entity->rowData[$this->getAttributeValueIdField($defs['mainField'] ?? $defs['multilangField'] ?? $key)] ?? null;
                if (!empty($attributesDefs[$key])) {
                    $attributesDefs[$key]['attributeValueId'] = $entity->entityDefs['fields'][$key]['attributeValueId'];
                }
                $this->getFieldType($entity->entityDefs['fields'][$key]['type'])->afterSelect($entity, $key, $dataArr);
            }
        }

        $entity->set('attributesDefs', $attributesDefs);
    }

    public function validateInput(IEntity $entity): void
    {
        if (empty($entity->_originalInput) || !$this->metadata->get("scopes.{$entity->getEntityName()}.hasAttribute")) {
            return;
        }

        if (!empty($entity->_originalInput->__attributes)) {
            if (!$entity->isNew() && !empty($this->metadata->get("scopes.{$entity->getEntityName()}.disableAttributeLinking"))) {
                throw new BadRequest($this->getLanguage()->translate('attributeLinkingDisabled', 'exceptions'));
            }

            if (!$this->container->get('acl')->check($entity, 'createAttributeValue')) {
                // check if record has such attributes during mass update
                if (!empty($entity->_originalInput->__massUpdate)) {
                    $this->putAttributesToEntity($entity);

                    foreach ($entity->_originalInput->__attributes as $attributeId) {
                        $hasAttribute = false;

                        foreach ($entity->entityDefs['fields'] as $defs) {
                            if (($defs['attributeId'] ?? null) === $attributeId && !empty($defs['attributeValueId'])) {
                                $hasAttribute = true;
                                break;
                            }
                        }

                        if (!$hasAttribute) {
                            throw new Forbidden($this->getLanguage()->translate('createAttributeValueForbidden', 'exceptions'));
                        }
                    }
                } else {
                    throw new Forbidden($this->getLanguage()->translate('createAttributeValueForbidden', 'exceptions'));
                }
            }
        }

        $attributeRepository = $this->container->get('entityManager')->getRepository('Attribute');

        if (!empty($entity->_originalInput->__attributesToRemove)) {
            $toRemove = $attributeRepository->getAttributeIdsByIdOrCode($entity->getEntityName(), $entity->_originalInput->__attributesToRemove);
            if (!empty($this->metadata->get("scopes.{$entity->getEntityName()}.disableAttributeLinking"))) {
                throw new BadRequest($this->getLanguage()->translate('attributeUnlinkingDisabled', 'exceptions'));
            }

            if (!$this->container->get('acl')->check($entity, 'deleteAttributeValue')) {
                throw new Forbidden($this->getLanguage()->translate('deleteAttributeValueForbidden', 'exceptions'));
            }

            // Validate composite children: a child may not be removed without its composite parent also being removed.
            $children = $attributeRepository->getCompositeDataForAttributes($toRemove);

            foreach ($children as $child) {
                if (!in_array($child['composite_attribute_id'], $toRemove)) {
                    throw new BadRequest($this->getLanguage()->translate('nestedAttributeRemovalForbidden', 'exceptions'));
                }
            }

            // mutate property
            $entity->_originalInput->__attributesToRemove = $toRemove;
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
            'av.prefix_value',
            'av.json_value',
            'f.name as file_name',
            'p.value as prefix_name',
            'ag.id as attribute_group_id',
            'ag.name as attribute_group_name',
            'ag.sort_order as attribute_group_sort_order',
            'a.sort_order as sort_order',
            'a.attribute_group_sort_order as sort_order_in_attribute_group',
            'a.modified_extended_disabled as modified_extended_disabled'
        ];

        $locales = [];
        if (!empty($this->config->get('isMultilangActive'))) {
            array_map(function ($code) use (&$locales) {
                $locales[] = strtolower($code);
            }, $this->config->get('inputLanguageList', []));
        }

        if (class_exists("\\Pim\\Module")) {
            $select[] = 'c.name as channel_name';

            if (!empty($this->metadata->get(['entityDefs', 'Channel', 'fields', 'name', 'isMultilang'], false))) {
                foreach ($locales as $locale) {
                    $select[] = 'c.name_' . $locale . ' as channel_name_' . $locale;
                }
            }
        }

        foreach ($locales as $locale) {
            $select[] = 'av.varchar_value_' . $locale;
            $select[] = 'av.text_value_' . $locale;
        }

        $qb = $this->conn->createQueryBuilder()
            ->select(implode(',', $select))
            ->from("{$tableName}_attribute_value", 'av')
            ->innerJoin('av', $this->conn->quoteIdentifier('attribute'), 'a', 'a.id=av.attribute_id AND a.deleted=:false')
            ->leftJoin('a', 'attribute_group', 'ag', 'ag.id=a.attribute_group_id AND ag.deleted=:false')
            ->leftJoin('av', $this->conn->quoteIdentifier('file'), 'f', 'f.id=av.reference_value AND a.type=:fileType AND f.deleted=:false')
            ->leftJoin('av', $this->conn->quoteIdentifier('prefix'), 'p', 'p.id=av.prefix_value AND p.deleted=:false')
            ->where("av.{$tableName}_id=:id")
            ->orderBy('a.sort_order', 'ASC')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('id', $entity->get('id'))
            ->setParameter('fileType', 'file');

        if (class_exists("\\Pim\\Module")) {
            $qb->leftJoin('a', $this->conn->quoteIdentifier('channel'), 'c', 'c.id=a.channel_id AND c.deleted=:false');
        }

        $this->joinSubtypes($qb, 'a');

        $res = array_map([$this, 'prepareSubtypeColumns'], $qb->fetchAllAssociative());

        foreach ($res as $k => $attribute) {
            if (!empty($attribute['channel_name'])) {
                $res[$k]['name'] = $attribute['name'] . ' / ' . $attribute['channel_name'];

                foreach ($locales as $locale) {
                    $attributeLabel = $attribute['name_' . $locale] ?? $attribute['name'];
                    $channelLabel   = $attribute['channel_name_' . $locale] ?? $attribute['channel_name'];

                    $res[$k]['name_' . $locale] = $attributeLabel . ' / ' . $channelLabel;
                }
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
                        $res[$k]['classification_id']           = $classificationAttribute['classification_id'];
                        $res[$k]['is_required']                 = $classificationAttribute['is_required'];
                        $res[$k]['is_read_only']                = $classificationAttribute['is_read_only'];
                        $res[$k]['is_protected']                = $classificationAttribute['is_protected'];

                        foreach (['conditional_required', 'conditional_visible', 'conditional_protected', 'conditional_read_only', 'conditional_disable_options'] as $key) {
                            if (!empty($classificationAttribute["enable_$key"])) {
                                $res[$k][$key] = $classificationAttribute[$key];
                            }
                        }

                        $attributeData = @json_decode($attribute['data'] ?? '', true);
                        if (empty($attributeData)) {
                            $attributeData = [];
                        }

                        // the properties of the classification attribute replace the settings of the attribute type
                        foreach ($propertyFields as $field) {
                            $res[$k][Util::toUnderScore($field)] = null;
                        }

                        $classificationAttributeData = @json_decode($classificationAttribute['data'] ?? '', true);
                        if (!empty($classificationAttributeData['field'])) {
                            foreach ($classificationAttributeData['field'] as $param => $paramValue) {
                                if (in_array($param, $propertyFields)) {
                                    $res[$k][Util::toUnderScore($param)] = $paramValue;
                                } else {
                                    $attributeData['field'][$param] = $paramValue;
                                }
                            }
                        }

                        if (!empty($classificationAttributeData['where'])
                            && !empty($classificationAttributeData['whereScope'])
                            && in_array($attribute['type'], ['link', 'linkMultiple'])
                            && ($attribute['entity_type'] ?? null) === $classificationAttributeData['whereScope']
                        ) {
                            $attributeData['where'] = $classificationAttributeData['where'];
                        }

                        $res[$k]['data'] = json_encode($attributeData);
                    }
                }
            }
        }

        $entityName = $this->metadata->get("scopes.{$entity->getEntityName()}.primaryEntityId") ?? $entity->getEntityName();

        $attributesToAdd = [];
        if (empty($this->metadata->get("scopes.{$entity->getEntityName()}.disableAttributeLinking"))) {
            $values = $entity->_originalInput->__attributes ?? [];
            if (!empty($values)) {
                $attributesToAdd = $this->container->get('entityManager')->getRepository('Attribute')
                    ->getAttributeIdsByIdOrCode($entityName, $values);
            }
        }

        // a duplicate always gets the attributes of the duplicated record, even when linking is disabled
        $attributesToAdd = array_values(array_unique(array_merge($attributesToAdd, $this->getDuplicatingEntityAttributesIds($entity))));

        // it needs because we should be able to create attribute value on entity update
        if (!empty($entity->_originalInput)) {
            $attributesIds = $attributesToAdd;

            foreach ($entity->_originalInput as $field => $value) {
                $attributeId = $this->metadata->get("entityDefs.{$entityName}.fields.{$field}.attributeId");
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

            if (!empty($entity->_originalInput->attributeValues)) {
                foreach ($entity->_originalInput->attributeValues as $attributeValue) {
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
                $qb = $this->conn->createQueryBuilder()
                    ->select('a.*')
                    ->from($this->conn->quoteIdentifier('attribute'), 'a')
                    ->where('a.id IN (:ids)')
                    ->setParameter('ids', $preparedAttributesIds, $this->conn::PARAM_STR_ARRAY);
                $this->joinSubtypes($qb, 'a');

                foreach ($qb->fetchAllAssociative() as $attr) {
                    $res[] = array_merge($this->prepareSubtypeColumns($attr), ['entity_id' => $entity->get('id')]);
                }
            }
        }

        $attributePanelsIds = array_column($this->config->get('referenceData.AttributePanel', []), 'id');

        $attributesDefs = [];

        foreach ($res as $row) {
            // set null if attribute-panel does not exist
            if (!empty($row['attribute_panel_id']) && !in_array($row['attribute_panel_id'], $attributePanelsIds)) {
                $row['attribute_panel_id'] = null;
            }

            // remove required property for derivatives
            if ($entityName !== $entity->getEntityName()) {
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
        $entity->setAllAttributesAsFetched();

        foreach ($preparedAttributesIds ?? [] as $attributeId) {
            foreach ($entity->fields ?? [] as $name => $defs) {
                if (!empty($defs['attributeId']) && $defs['attributeId'] === $attributeId) {
                    $entity->unsetFetched($name);
                }
            }
        }

        $entity->hasAllEntityAttributes = true;
    }


    protected function getDuplicatingEntityAttributesIds(IEntity $entity): array
    {
        // __duplicatingEntityId is where the hierarchy service keeps it when it removes _duplicatingEntityId
        $duplicatingEntityId = $entity->_originalInput->_duplicatingEntityId ?? $entity->_originalInput->__duplicatingEntityId ?? null;
        if (!$entity->isNew() || empty($duplicatingEntityId) || !is_string($duplicatingEntityId)) {
            return [];
        }

        $tableName = Util::toUnderScore(lcfirst($entity->getEntityName()));

        return $this->conn->createQueryBuilder()
            ->select('av.attribute_id')
            ->from("{$tableName}_attribute_value", 'av')
            ->innerJoin('av', $this->conn->quoteIdentifier('attribute'), 'a', 'a.id=av.attribute_id AND a.deleted=:false')
            ->where("av.{$tableName}_id=:id")
            ->setParameter('id', $duplicatingEntityId)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchFirstColumn();
    }

    public function prepareInputForAttributeValuesArray(IEntity $entity, \stdClass $input): void
    {
        if (!isset($input->attributeValues)) {
            return;
        }

        // flat attribute values from array
        foreach ($input->attributeValues as $attributeValue) {
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

        unset($input->attributeValues);
    }

    public function getAttributesRowsByIds(array $attributesIds): array
    {
        $qb = $this->conn->createQueryBuilder()
            ->select('a.*')
            ->from($this->conn->quoteIdentifier('attribute'), 'a')
            ->where('a.id IN (:ids) or a.system_name IN (:ids)')
            ->andWhere('a.deleted=:false')
            ->setParameter('ids', $attributesIds, Connection::PARAM_STR_ARRAY)
            ->setParameter('false', false, ParameterType::BOOLEAN);

        if (class_exists("\\Pim\\Module")) {
            $qb->addSelect('c.name as channel_name')
                ->leftJoin('a', $this->conn->quoteIdentifier('channel'), 'c', 'c.id = a.channel_id AND c.deleted=:false');
        }

        $this->joinSubtypes($qb, 'a');

        return array_map([$this, 'prepareSubtypeColumns'], $qb->fetchAllAssociative());
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

    protected function getLanguage(): Language
    {
        return $this->container->get('language');
    }
}