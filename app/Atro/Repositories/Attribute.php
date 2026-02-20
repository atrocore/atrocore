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

declare(strict_types=1);

namespace Atro\Repositories;

use Atro\Core\AttributeFieldConverter;
use Atro\Core\EventManager\Event;
use Atro\Core\EventManager\Manager;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Repositories\Base;
use Atro\Core\Utils\Database\DBAL\Schema\Converter;
use Atro\Core\Utils\IdGenerator;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Atro\Core\Exceptions\BadRequest;
use Espo\Core\AclManager;
use Espo\ORM\Entity;
use Espo\ORM\IEntity;

class Attribute extends Base
{
    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('dataManager');
        $this->addDependency('container');
    }

    public function getAllAttributesIdsForEntity(string $entityName, array $where, array $channels = []): array
    {
        if (!$this->getMetadata()->get("scopes.$entityName.hasAttribute")) {
            return [];
        }

        $tableName = Util::toUnderScore(lcfirst($entityName));

        /* @var $repository Base */
        $repository = $this->getEntityManager()->getRepository($entityName);

        $sp = $this->getInjection('container')->get('serviceFactory')->create($entityName)->getSelectParams([
            'where' => json_decode(json_encode($where), true),
        ]);
        $sp['select'] = ['id'];

        $qb1 = $repository->getMapper()->createSelectQueryBuilder($repository->get(), $sp, true);

        $qb = $repository->getConnection()->createQueryBuilder()
            ->select('a.id, a.sort_order')
            ->distinct()
            ->from("{$tableName}_attribute_value", 'av')
            ->innerJoin('av', "attribute", 'a', 'av.attribute_id = a.id AND a.deleted=:false')
            ->where("av.{$tableName}_id in ({$qb1->getSQL()})")
            ->andWhere('av.deleted=:false')
            ->orderBy('a.sort_order', 'ASC')
            ->setParameter('false', false, ParameterType::BOOLEAN);

        foreach ($qb1->getParameters() as $parameterName => $value) {
            $qb->setParameter($parameterName, $value, Mapper::getParameterType($value));
        }

        if (class_exists("\\Pim\\Module") && !empty($channels)) {
            $channelSqlParts = [];
            foreach ($channels as $channelId) {
                if ($channelId === 'withoutChannel') {
                    $channelSqlParts[] = "a.channel_id IS NULL";
                } else {
                    $channelSqlParts[] = "a.channel_id = :$channelId";
                    $qb->setParameter($channelId, $channelId);
                }
            }
            $qb->andWhere(implode(' OR ', $channelSqlParts));
        }

        return array_column($qb->fetchAllAssociative(), 'id');
    }

    public function getAttributesByIds(array $attributesIds): array
    {
        $conn = $this->getConnection();

        if (class_exists("\\Pim\\Module")) {
            return $conn->createQueryBuilder()
                ->select('a.*, c.id as channel_id, c.name as channel_name')
                ->from($conn->quoteIdentifier('attribute'), 'a')
                ->where('a.id IN (:ids)')
                ->andWhere('a.deleted=:false')
                ->leftJoin('a', $conn->quoteIdentifier('channel'), 'c', 'a.channel_id=c.id AND c.deleted=:false')
                ->setParameter('ids', $attributesIds, Connection::PARAM_STR_ARRAY)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();
        }

        return $conn->createQueryBuilder()
            ->select('*')
            ->from($conn->quoteIdentifier('attribute'))
            ->where('id IN (:ids)')
            ->andWhere('deleted=:false')
            ->setParameter('ids', $attributesIds, Connection::PARAM_STR_ARRAY)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();
    }

    public function prepareAllParentsCompositeAttributesIds(string $attributeId, array &$ids = []): void
    {
        $attribute = $this->get($attributeId);
        if (!empty(!empty($attribute->get('compositeAttributeId')))) {
            $ids[] = $attribute->get('compositeAttributeId');
            $this->prepareAllParentsCompositeAttributesIds($attribute->get('compositeAttributeId'), $ids);
        }
    }

    public function prepareAllChildrenCompositeAttributesIds(string $attributeId, array &$ids = []): void
    {
        $children = $this
            ->where([
                'compositeAttributeId' => $attributeId,
                'type'                 => 'composite'
            ])
            ->find();

        foreach ($children as $child) {
            $ids[] = $child->get('id');
            $this->prepareAllChildrenCompositeAttributesIds($child->get('id'), $ids);
        }
    }

    public function prepareAllChildrenAttributesIdsForComposite(string $attributeId, array &$ids = []): void
    {
        $children = $this
            ->where(['compositeAttributeId' => $attributeId])
            ->find();

        foreach ($children as $child) {
            $ids[] = $child->get('id');
            if ($child->get('type') === 'composite') {
                $this->prepareAllChildrenAttributesIdsForComposite($child->get('id'), $ids);
            }
        }
    }

    public function getCompositeAttributeIdsFromClassification(string $classificationId): array
    {
        return $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->select('a.id')
            ->from('classification_attribute', 'ca')
            ->innerJoin('ca', 'attribute', 'a', 'a.id=ca.attribute_id')
            ->where('ca.classification_id = :classification_id')
            ->setParameter('classification_id', $classificationId)
            ->andWhere('a.type = :type')
            ->setParameter('type', 'composite')
            ->andWhere('a.deleted = :false and ca.deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchFirstColumn();
    }

    public function clearCache(): void
    {
        $this->getInjection('dataManager')->setCacheData('attribute_product_fields', null);
    }

    public function inheritAllAttributeValuesFromParents(Entity $entity): void
    {
        $parentsIds = $entity->get('parentsIds') ?? [];
        if (empty($parentsIds)) {
            return;
        }

        $tableName = Util::toUnderScore(lcfirst($entity->getEntityName()));

        foreach ($parentsIds as $parentId) {
            $attrs = $this->getEntityManager()->getConnection()->createQueryBuilder()
                ->select('*')
                ->from("{$tableName}_attribute_value")
                ->where("deleted=:false")
                ->andWhere("{$tableName}_id=:id")
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setParameter('id', $parentId)
                ->fetchAllAssociative();

            if (empty($attrs)) {
                continue;
            }

            foreach ($attrs as $attr) {
                $this->getEntityManager()->getConnection()->createQueryBuilder()
                    ->delete("{$tableName}_attribute_value")
                    ->where('deleted=:false')
                    ->andWhere("{$tableName}_id = :recordId")
                    ->andWhere("attribute_id = :attributeId")
                    ->setParameter('recordId', $entity->get('id'))
                    ->setParameter('attributeId', $attr['attribute_id'])
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->executeQuery();


                $attr['id'] = IdGenerator::uuid();
                $attr["{$tableName}_id"] = $entity->get('id');

                $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
                $qb->insert("{$tableName}_attribute_value");
                foreach ($attr as $column => $value) {
                    $qb->setValue($column, ":{$column}");
                    $qb->setParameter($column, $value, Mapper::getParameterType($value));
                }
                $qb->executeQuery();
            }
        }
    }

    public function addAttributeValueForComposite(Entity $entity): void
    {
        if (empty($entity->get('compositeAttributeId'))) {
            return;
        }

        $attributesIds = [$entity->get('compositeAttributeId')];
        $this->prepareAllParentsCompositeAttributesIds($entity->get('compositeAttributeId'), $attributesIds);

        $name = Util::toUnderScore(lcfirst($entity->get('entityId')));

        $avIds = $this->getConnection()->createQueryBuilder()
            ->select("{$name}_id")
            ->distinct()
            ->from("{$name}_attribute_value")
            ->where('attribute_id IN (:attributesIds)')
            ->setParameter('attributesIds', $attributesIds, $this->getConnection()::PARAM_STR_ARRAY)
            ->fetchFirstColumn();

        foreach ($avIds as $avId) {
            try {
                $this->addAttributeValue($entity->get('entityId'), $avId, $entity->get('id'));
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    public function addAttributeValue(string $entityName, string $entityId, string $attributeId): void
    {
        $attribute = $this->get($attributeId);
        if (empty($attribute)) {
            throw new NotFound();
        }

        $checkEntity = $this->getMetadata()->get("scopes.$entityName.primaryEntityId") ?? $entityName;
        if ($attribute->get('entityId') !== $checkEntity) {
            throw new BadRequest($this->exception('attributeNotBelongToEntity'));
        }

        $name = Util::toUnderScore(lcfirst($entityName));

        $qb = $this->getConnection()->createQueryBuilder()
            ->insert("{$name}_attribute_value")
            ->setValue('id', ':id')
            ->setValue('attribute_id', ':attributeId')
            ->setValue("{$name}_id", ':entityId')
            ->setParameter('id', IdGenerator::uuid())
            ->setParameter('attributeId', $attributeId)
            ->setParameter('entityId', $entityId);

        if (!empty($attribute->get('defaultUnit'))) {
            $qb->setValue('reference_value', ':unitId')
                ->setparameter('unitId', $attribute->get('defaultUnit'));
        }

        $qb->executeQuery();


        $note = $this->getEntityManager()->getEntity('Note');
        $note->set([
            'type'       => 'Relate',
            'parentType' => $entityName,
            'parentId'   => $entityId,
            'data'       => [
                'relatedType' => 'Attribute',
                'relatedId'   => $attributeId,
                'link'        => lcfirst($entityName) . "AttributeValues"
            ],
        ]);
        $this->getEntityManager()->saveEntity($note);

        if ($this->shouldLinkAddedAttributeToClassification($entityName)) {
            $this->addAttributeToClassification($this->getEntityManager()->getEntity($entityName, $entityId), $attributeId);
        }
    }

    public function shouldLinkAddedAttributeToClassification(string $entityName): bool
    {
        return !empty($this->getMetadata()->get(['scopes', $entityName, 'hasClassification'])) &&
            !empty($this->getMetadata()->get(['scopes', $entityName, 'linkAttributesWithClassificationAutomatically']));
    }

    public function addAttributeToClassification(IEntity $entity, string $attributeId): void
    {
        $classifications = $entity->get('classifications');

        if (!empty($classifications) && count($classifications) === 1) {
            $classification = $classifications[0];

            $exists = $this->getEntityManager()->getConnection()->createQueryBuilder()
                ->select('id')
                ->from('classification_attribute')
                ->where('attribute_id=:attributeId')
                ->andWhere('classification_id=:classificationId')
                ->setParameter('attributeId', $attributeId)
                ->setParameter('classificationId', $classification->get('id'))
                ->fetchOne();

            if (empty($exists)) {
                $input = new \stdClass();
                $input->classificationId = $classification->get('id');
                $input->attributeId = $attributeId;
                $this->getInjection('container')->get('serviceFactory')->create('ClassificationAttribute')->createEntity($input);
            }
        }
    }

    public function upsertAttributeValue(IEntity $entity, string $fieldName, $value, bool $insertOnly = false): void
    {
        $name = Util::toUnderScore(lcfirst($entity->getEntityName()));
        $valColumn = $entity->fields[$fieldName]['column'] ?? null;

        if (empty($valColumn)) {
            return;
        }

        if (is_array($value)) {
            $value = json_encode($value);
        }

        if (!$this->getAcl()->check($entity->getEntityName(), 'createAttributeValue')) {
            if ($insertOnly) {
                return;
            }

            $this->getConnection()->createQueryBuilder()
                ->update("{$name}_attribute_value")
                ->set($valColumn, ":value")
                ->where("{$name}_id=:entityId")
                ->andWhere("attribute_id=:attributeId")
                ->andWhere("deleted=:false")
                ->setParameter('value', $value, Mapper::getParameterType($value))
                ->setParameter('entityId', $entity->id)
                ->setParameter('attributeId', $entity->fields[$fieldName]['attributeId'])
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->executeQuery();
        } else {
            $sql = "INSERT INTO {$name}_attribute_value (id, {$name}_id, attribute_id, $valColumn) VALUES (:id, :entityId, :attributeId, :value)";
            if (!$insertOnly) {
                if (Converter::isPgSQL($this->getConnection())) {
                    $sql .= " ON CONFLICT (deleted, {$name}_id, attribute_id) DO UPDATE SET $valColumn = EXCLUDED.$valColumn RETURNING xmax";
                } else {
                    $sql .= " ON DUPLICATE KEY UPDATE $valColumn = VALUES($valColumn)";
                }
            } else {
                if (Converter::isPgSQL($this->getConnection())) {
                    $sql .= ' ON CONFLICT DO NOTHING';
                } else {
                    $sql = str_replace('INSERT INTO', 'INSERT IGNORE INTO', $sql);
                }
            }

            $stmt = $this->getEntityManager()->getPDO()->prepare($sql);
            $attributeValueId = IdGenerator::uuid();

            $stmt->bindValue(':id', $attributeValueId);
            $stmt->bindValue(':entityId', $entity->id);
            $stmt->bindValue(':attributeId', $entity->fields[$fieldName]['attributeId']);

            if ($entity->fields[$fieldName]['type'] === 'bool') {
                $stmt->bindValue(':value', $value, \PDO::PARAM_BOOL);
            } else {
                $stmt->bindValue(':value', $value);
            }

            try {
                $stmt->execute();

                $attributesDefs = $entity->get('attributesDefs');
                if (empty($attributesDefs[$fieldName]['attributeValueId'])) {
                    foreach ($attributesDefs as $key => $attributeDef) {
                        if ($attributeDef['attributeId'] === $entity->fields[$fieldName]['attributeId']) {
                            $attributesDefs[$key]['attributeValueId'] = $attributeValueId;
                            $entity->entityDefs['fields'][$fieldName]['attributeValueId'] = $attributeValueId;
                        }
                    }
                    $entity->set('attributesDefs', $attributesDefs);
                }

                if ($this->shouldLinkAddedAttributeToClassification($entity->getEntityType())) {
                    if (Converter::isPgSQL($this->getConnection())) {
                        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                        $wasInserted = $result['xmax'] == 0;
                    } else {
                        $wasInserted = $stmt->rowCount() === 1;
                    }

                    if ($wasInserted) {
                        $this->addAttributeToClassification($entity, $entity->fields[$fieldName]['attributeId']);
                    }
                }
            } catch (\PDOException $e) {
                $GLOBALS['log']->error('Upsert attribute error: ' . $e->getMessage());
            }
        }
    }

    public function hasAttributeValue(string $entityName, string $entityId, string $attributeId): bool
    {
        $name = Util::toUnderScore(lcfirst($entityName));

        $res = $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from("{$name}_attribute_value")
            ->where('attribute_id=:attributeId')
            ->andWhere("{$name}_id=:entityId")
            ->andWhere('deleted=:false')
            ->setParameter('attributeId', $attributeId)
            ->setParameter('entityId', $entityId)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAssociative();

        return !empty($res);
    }

    public function removeAttributeValue(string $entityName, string $entityId, string $attributeId): bool
    {
        $name = Util::toUnderScore(lcfirst($entityName));

        $res = $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from("{$name}_attribute_value")
            ->where('attribute_id=:attributeId')
            ->andWhere("{$name}_id=:entityId")
            ->andWhere('deleted=:false')
            ->setParameter('attributeId', $attributeId)
            ->setParameter('entityId', $entityId)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAssociative();

        if (!empty($res)) {
            $this->getConnection()->createQueryBuilder()
                ->delete("{$name}_attribute_value")
                ->where('attribute_id=:attributeId')
                ->andWhere("{$name}_id=:entityId")
                ->setParameter('attributeId', $attributeId)
                ->setParameter('entityId', $entityId)
                ->executeQuery();

            $note = $this->getEntityManager()->getEntity('Note');
            $note->set([
                'type'       => 'Unrelate',
                'parentType' => $entityName,
                'parentId'   => $entityId,
                'data'       => [
                    'relatedType' => 'Attribute',
                    'relatedId'   => $attributeId,
                    'link'        => lcfirst($entityName) . "AttributeValues"
                ],
            ]);
            $this->getEntityManager()->saveEntity($note);

            $this->getEventManager()->dispatch('AttributeRepository', 'removeAttributeValue',
                new Event(['entityName' => $entityName, 'entityId' => $entityId, 'attributeId' => $attributeId]));
        }

        return true;
    }

    public function updateSortOrder(array $ids, string $field): void
    {
        $column = Util::toUnderScore($field);
        foreach ($ids as $k => $id) {
            $this->getConnection()->createQueryBuilder()
                ->update($this->getConnection()->quoteIdentifier('attribute'), 'a')
                ->set($column, ':sortOrder')
                ->where('a.id = :id')
                ->setParameter('sortOrder', $k * 10)
                ->setParameter('id', $id)
                ->executeQuery();
        }
    }

    public function getMultilingualAttributeTypes(): array
    {
        $attributes = [];
        foreach ($this->getMetadata()->get(['attributes'], []) as $attribute => $attributeDefs) {
            if (!empty($attributeDefs['multilingual'])) {
                $attributes[] = $attribute;
            }
        }

        return $attributes;
    }

    public function beforeSave(Entity $entity, array $options = [])
    {
        if (!$entity->isNew() && $entity->isAttributeChanged('type')) {
            $converterName = $this
                ->getMetadata()
                ->get(['attributes', $entity->getFetched('type'), 'convert', $entity->get('type')]);

            if (empty($converterName)) {
                $message = $this
                    ->getInjection('language')
                    ->translate('noAttributeConverterFound', 'exceptions', 'Attribute');
                throw new BadRequest(sprintf($message, $entity->getFetched('type'), $entity->get('type')));
            }

            $this->getInjection('container')->get($converterName)->convert($entity);
        }

        if ($entity->get('code') === '') {
            $entity->set('code', null);
        }

        if ($entity->get('code') !== null) {
            if (!AttributeFieldConverter::isValidCode($entity->get('code'))) {
                throw new BadRequest("The code must start with a letter and can contain only letters, numbers, and underscores. No spaces or other special characters are allowed.");
            }
            if ($this->getMetadata()->get("entityDefs.{$entity->get('entityId')}.fields.{$entity->get('code')}")) {
                throw new BadRequest("Field with such code exists for the {$entity->get('entityId')}.");
            }
        }

        if (!in_array($entity->get('type'), $this->getMultilingualAttributeTypes())) {
            $entity->set('isMultilang', false);
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('entityId')) {
            throw new BadRequest($this->exception('entityCannotBeChanged'));
        }

        if ($entity->isAttributeChanged('entityId')) {
            if (
                !$this->getMetadata()->get("scopes.{$entity->get('entityId')}.hasAttribute")
                || $this->getMetadata()->get("scopes.{$entity->get('entityId')}.primaryEntityId")
            ) {
                throw new BadRequest($this->exception('entityNotAllowToChoose'));
            }
        }

        $attributePanel = $this->getEntityManager()->getEntity('AttributePanel', $entity->get('attributePanelId'));
        if (empty($attributePanel)) {
            throw new BadRequest("Attribute panel '{$entity->get('attributePanelId')}' does not exist.");
        }

        if ($entity->get('entityId') !== $attributePanel->get('entityId')) {
            throw new BadRequest($this->exception('wrongAttributeEntity'));
        }

        if (!empty($entity->get('attributeGroupId'))) {
            $attributeGroup = $this->getEntityManager()->getEntity('AttributeGroup', $entity->get('attributeGroupId'));
            if (empty($attributeGroup)) {
                throw new BadRequest("Attribute Group '{$entity->get('attributeGroupId')}' does not exist.");
            }

            if ($entity->get('entityId') !== $attributeGroup->get('entityId')) {
                throw new BadRequest($this->exception('wrongAttributeEntity'));
            }

            if ($entity->get('attributeGroupSortOrder') === null) {
                $entity->set('attributeGroupSortOrder', 0);
            }
        }

        if ($entity->get('sortOrder') === null) {
            $entity->set('sortOrder', 0);
        }

        if (!empty($entity->get('compositeAttributeId'))) {
            if ($entity->get('compositeAttributeId') === $entity->get('id')) {
                throw new BadRequest($this->exception('compositeAttributeCannotBeChild'));
            }

            $composite = $this->get($entity->get('compositeAttributeId'));
            if (empty($composite) || $composite->get('type') !== 'composite') {
                throw new BadRequest($this->exception('compositeAttributeNotFound'));
            }

            if ($composite->get('entityId') !== $entity->get('entityId')) {
                throw new BadRequest($this->exception('attributeEntityMustBeSameAsInCompositeAttribute'));
            }

            while (!empty($composite->get('compositeAttributeId'))) {
                if ($composite->get('id') === $entity->get('id')) {
                    throw new BadRequest($this->exception('compositeAttributeCannotBeChild'));
                }
                $composite = $this->get($composite->get('compositeAttributeId'));
            }
        }

        if ($entity->get('type') === 'composite') {
            $entity->set('fullWidth', true);
        }

        parent::beforeSave($entity, $options);

        $this->validateMinMax($entity);

        if ($entity->isAttributeChanged('extensibleEnumId') && in_array($entity->get('type'), ['link', 'linkMultiple'])) {
            if (empty($entity->get('extensibleEnumId'))) {
                $where = [];
            } else {
                $where = [
                    [
                        "condition" => "AND",
                        "rules"     => [
                            [
                                "id"       => "extensibleEnums",
                                "field"    => "extensibleEnums",
                                "type"     => "string",
                                "operator" => "linked_with",
                                "value"    => [$entity->get('extensibleEnumId')],
                            ]
                        ],
                        "valid"     => true
                    ]
                ];
            }

            $entity->set('extensibleEnumId', null);

            $data = $entity->get('data') ?? [];
            $data['where'] = $where;

            $entity->set('data', $data);
        }
    }

    public function validateMinMax(Entity $entity): void
    {
        if (
            ($entity->isAttributeChanged('max') || $entity->isAttributeChanged('min'))
            && $entity->get('min') !== null
            && $entity->get('max') !== null
            && $entity->get('max') < $entity->get('min')
        ) {
            throw new BadRequest($this->getInjection('language')->translate('maxLessThanMin', 'exceptions',
                'Attribute'));
        }
    }


    public function clearAttributeValue(string $scope, string $attributeId, string $column): void
    {
        $table = Util::toUnderScore(lcfirst($scope) . '_attribute_value');
        $conn = $this->getEntityManager()->getConnection();
        $conn->createQueryBuilder()
            ->update($conn->quoteIdentifier($table))
            ->set($column, ':null')
            ->where('attribute_id = :attributeId')
            ->andWhere("$column is NOT NULL AND deleted=:false")
            ->setParameter('null', null, ParameterType::NULL)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('attributeId', $attributeId)
            ->executeQuery();
    }

    public function getEntityAttributes(string $scope): array
    {
        $select = ['id', 'code', 'type'];

        if (class_exists('\AdvancedDataTypes\Module')) {
            $select[] = 'output_type';
        }

        return $this->getConnection()->createQueryBuilder()
            ->select(join(',', $select))
            ->from('attribute')
            ->where('entity_id = :entityName and deleted = :false')
            ->setParameter('entityName', $scope)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();
    }

    /**
     * @inheritDoc
     */
    protected function afterSave(Entity $entity, array $options = array())
    {
        parent::afterSave($entity, $options);

        if ($entity->isNew() && !empty($entity->get('compositeAttributeId'))) {
            $this->addAttributeValueForComposite($entity);
        }

        if ($entity->isAttributeChanged('attributePanelId')) {
            $this
                ->getAclManager()
                ->clearAclCache();
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        // Remove attribute from layouts
        $this->removeFromLayout('LayoutListItem', $entity->get('id'));
    }

    protected function removeFromLayout(string $layoutName, string $attributeId): void
    {
        $repository = $this->getEntityManager()->getRepository($layoutName);

        $layoutItem = $repository
            ->where(['attributeId' => $attributeId])
            ->findOne();

        if (!empty($layoutItem)) {
            $repository->remove($layoutItem);
        }
    }

    protected function getAcl()
    {
        return $this->getInjection('container')->get('acl');
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->getInjection('language')->translate($key, "exceptions", "Attribute");
    }

    protected function getEventManager(): Manager
    {
        return $this->getInjection('container')->get('eventManager');
    }

    protected function getAclManager(): AclManager
    {
        return $this->getInjection('container')->get('aclManager');
    }
}
