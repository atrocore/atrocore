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

namespace Atro\Core\Templates\Repositories;

use Atro\Core\AttributeFieldConverter;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\ORM\Repositories\RDB;
use Atro\Core\PseudoTransactionManager;
use Atro\Core\Utils\IdGenerator;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\IEntity;

class Base extends RDB
{
    protected function getNewEntity()
    {
        $entity = parent::getNewEntity();

        $this->afterEntityPopulated($entity);

        return $entity;
    }

    protected function getEntityById($id)
    {
        $entity = parent::getEntityById($id);

        if ($entity !== null) {
            $this->afterEntityPopulated($entity);
        }

        return $entity;
    }

    protected function afterEntityPopulated(Entity $entity): void
    {
    }

    public function find(array $params = [])
    {
        $subtypeFields = $this->getSubtypeFieldsToSelect($params);
        if (!empty($subtypeFields)) {
            $params['subtypeFieldsToSelect'] = $subtypeFields;
            $params['callbacks'][] = [$this, 'putSubtypeFieldsToSelect'];
        }

        /** @var EntityCollection $collection */
        $collection = parent::find($params);

        foreach ($collection as $entity) {
            $this->afterEntityPopulated($entity);
        }

        $firstEntity = $collection[0] ?? null;
        if (!empty($firstEntity) && $this->getMetadata()->get("scopes.{$firstEntity->getEntityName()}.hasAttribute")) {
            $this->prepareAttributesForOutput($collection, $params);
        }

        if (!empty($subtypeFields)) {
            $this->putSubtypeFieldsToMeta($collection, $subtypeFields, $params['subtypeFields'] ?? []);
        }

        return $collection;
    }

    /**
     * The fields of the subtypes to join to the select of the records, grouped by subtype: the requested ones and the
     * one the records are sorted by.
     *
     * @return array<string, string[]>
     */
    protected function getSubtypeFieldsToSelect(array $params): array
    {
        if (!empty($params['aggregation'])) {
            return [];
        }

        $names = $params['subtypeFields'] ?? [];
        if (!empty($params['orderBy']) && is_string($params['orderBy'])) {
            $names[] = $params['orderBy'];
        }

        $result = [];
        foreach ($names as $name) {
            $subtypeField = $this->getMetadata()->getSubtypeField($this->entityType, (string)$name);
            if ($subtypeField !== null && !in_array($subtypeField['field'], $result[$subtypeField['entityName']] ?? [], true)) {
                $result[$subtypeField['entityName']][] = $subtypeField['field'];
            }
        }

        return $result;
    }

    /**
     * A select callback for a list - the same way the attributes join their values: joins the table of every subtype,
     * whose fields are requested, selecting the attributes of these fields under the names "<Subtype>_<attribute>",
     * and sorts the list by such a field when asked. The table is joined as the select of the subtype itself, so the
     * attributes of every type are selected as they are for the subtype.
     */
    public function putSubtypeFieldsToSelect(QueryBuilder $qb, IEntity $entity, array $params, Mapper $mapper): void
    {
        $queryConverter = $mapper->getQueryConverter();
        $mainAlias = $queryConverter->getMainTableAlias();

        $orderBy = is_string($params['orderBy'] ?? null) ? $this->getMetadata()->getSubtypeField($this->entityType, $params['orderBy']) : null;
        $order = strtoupper((string)($params['order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        foreach ($params['subtypeFieldsToSelect'] ?? [] as $subtype => $fields) {
            $attributes = $this->getSubtypeFieldsAttributes($subtype, $fields);

            $subtypeRepository = $this->getEntityManager()->getRepository($subtype);
            $subtypeQb = $subtypeRepository->getMapper()->createSelectQueryBuilder($subtypeRepository->get(), [
                'select'               => array_merge(['id'], array_merge(...array_values($attributes))),
                'maxTextColumnsLength' => $params['maxTextColumnsLength'] ?? null,
                // the deleted records are left out by the select of the supertype
                'withDeleted'          => true,
            ], true);

            $alias = 'st_' . IdGenerator::unsortableId();
            $sql = preg_replace('/\b' . preg_quote($mainAlias, '/') . '\b/', 'sti_' . IdGenerator::unsortableId(), $subtypeQb->getSQL());

            $qb->leftJoin($mainAlias, "($sql)", $alias, "$alias.{$queryConverter->fieldToAlias('id')} = $mainAlias.id");
            foreach ($subtypeQb->getParameters() as $name => $value) {
                $qb->setParameter($name, $value, $subtypeQb->getParameterType($name));
            }

            // a record of another subtype has no row in the table
            $qb->addSelect("$alias.{$queryConverter->fieldToAlias('id')} AS " . $queryConverter->fieldToAlias("{$subtype}_id"));

            foreach ($attributes as $field => $fieldAttributes) {
                foreach ($fieldAttributes as $attribute) {
                    $qb->addSelect("$alias.{$queryConverter->fieldToAlias($attribute)} AS " . $queryConverter->fieldToAlias("{$subtype}_{$attribute}"));
                }

                // the field is not a field the query converter knows, so the list is sorted by it here
                if ($orderBy !== null && $orderBy['entityName'] === $subtype && $orderBy['field'] === $field) {
                    $sortAttribute = in_array($orderBy['type'], ['link', 'file', 'image'], true) ? "{$field}Name" : $field;
                    if (in_array($sortAttribute, $fieldAttributes, true)) {
                        $qb->add('orderBy', $queryConverter->fieldToAlias("{$subtype}_{$sortAttribute}") . " $order");
                    }
                }
            }
        }
    }

    /**
     * The attributes of the fields of the subtype that are selected from its table, keyed by field.
     *
     * @param string[] $fields
     *
     * @return array<string, string[]>
     */
    protected function getSubtypeFieldsAttributes(string $subtype, array $fields): array
    {
        $seed = $this->getEntityManager()->getRepository($subtype)->get();

        $result = [];
        foreach ($fields as $field) {
            foreach ($this->container->get('fieldManager')->getAttributeList($subtype, $field) as $attribute) {
                if ($seed->hasAttribute($attribute) && empty($seed->getAttributeParam($attribute, 'notStorable'))) {
                    $result[$field][] = $attribute;
                }
            }
        }

        return $result;
    }

    /**
     * Puts the values of the requested fields of the subtypes into the meta of the records of these subtypes, under
     * the names "<Subtype>_<attribute>". A record of another subtype gets nothing.
     *
     * @param array<string, string[]> $subtypeFields
     * @param string[]                $requested
     */
    protected function putSubtypeFieldsToMeta(EntityCollection $collection, array $subtypeFields, array $requested): void
    {
        $requestedFields = [];
        foreach ($requested as $name) {
            $subtypeField = $this->getMetadata()->getSubtypeField($this->entityType, (string)$name);
            if ($subtypeField !== null) {
                $requestedFields[$subtypeField['entityName']][] = $subtypeField['field'];
            }
        }

        foreach ($subtypeFields as $subtype => $fields) {
            $fields = array_values(array_intersect($fields, $requestedFields[$subtype] ?? []));
            if (empty($fields)) {
                continue;
            }

            $attributes = array_merge(...array_values($this->getSubtypeFieldsAttributes($subtype, $fields)));
            $seed = $this->getEntityManager()->getRepository($subtype)->get();

            foreach ($collection as $entity) {
                if (empty($entity->rowData["{$subtype}_id"])) {
                    continue;
                }

                // the values are typed the same way as for a record of the subtype
                $seed->reset();
                $values = [];
                foreach ($attributes as $attribute) {
                    $values[$attribute] = $entity->rowData["{$subtype}_{$attribute}"] ?? null;
                }
                $seed->set($values);

                foreach ($attributes as $attribute) {
                    $entity->setMeta('subtypeFields', "{$subtype}_{$attribute}", $seed->get($attribute));
                }
            }
        }
    }

    public function findRelated(Entity $entity, $relationName, array $params = [])
    {
        /** @var EntityCollection $collection */
        $res = parent::findRelated($entity, $relationName, $params);

        if ($res instanceof EntityCollection) {
            $collection = $res;
        } else if (!empty($res)) {
            $collection = new EntityCollection();
            $collection->append($res);
        }

        if (isset($collection)) {
            $firstEntity = $collection[0] ?? null;
            if (!empty($firstEntity) && $this->getMetadata()->get("scopes.{$firstEntity->getEntityName()}.hasAttribute")) {
                $this->prepareAttributesForOutput($collection, $params);
            }
        }

        return $res;
    }

    public function getRecordPosition(string $id, array $selectParams): ?int
    {
        $selectParams['select'] = ['id'];
        unset($selectParams['offset'], $selectParams['limit']);

        $mapper = $this->getMapper();
        $qb     = $mapper->createSelectQueryBuilder($this->get(), $selectParams);

        $order = $qb->getQueryPart('orderBy');
        if (empty($order)) {
            return null;
        }

        $qb->addSelect('ROW_NUMBER() OVER (ORDER BY ' . implode(', ', $order) . ') AS atro_position');
        $qb->resetQueryPart('orderBy');

        $params                 = $qb->getParameters();
        $types                  = $qb->getParameterTypes();
        $params['atroRecordId'] = $id;
        $types['atroRecordId']  = ParameterType::STRING;

        $idAlias = $mapper->getQueryConverter()->fieldToAlias('id');

        $position = $this->getDbal()->fetchOne(
            "SELECT numbered.atro_position FROM ({$qb->getSQL()}) numbered WHERE numbered.$idAlias = :atroRecordId",
            $params,
            $types
        );

        return empty($position) ? null : (int) $position;
    }

    /**
     * Whether the records of the entity are organized in the tree via the generated "parent" relation.
     */
    public function isTree(): bool
    {
        return !empty($this->getMetadata()->get(['scopes', $this->entityName, 'hasTree']))
            && $this->getMetadata()->get(['scopes', $this->entityName, 'type']) === 'Base';
    }

    /**
     * IDs of the ancestors of the record in the tree, from the root to the direct parent.
     */
    public function getTreeAncestorsIds(string $id): array
    {
        $dbal   = $this->getDbal();
        $table  = $dbal->quoteIdentifier($this->getMapper()->toDb($this->entityName));
        $column = $dbal->quoteIdentifier($this->getMapper()->toDb('parentId'));

        $ancestorsIds = [];
        $currentId    = $id;
        while (true) {
            $parentId = $dbal->createQueryBuilder()
                ->select($column)
                ->from($table)
                ->where('id = :id')
                ->andWhere('deleted = :false')
                ->setParameter('id', $currentId)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchOne();

            if (empty($parentId) || $parentId === $id || in_array($parentId, $ancestorsIds, true)) {
                break;
            }

            $ancestorsIds[] = $parentId;
            $currentId      = $parentId;
        }

        return array_reverse($ancestorsIds);
    }

    /**
     * Number of the children in the tree for each of the given records. Records without children are omitted.
     */
    public function getTreeChildrenCounts(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $dbal   = $this->getDbal();
        $column = $dbal->quoteIdentifier($this->getMapper()->toDb('parentId'));

        $rows = $dbal->createQueryBuilder()
            ->select("$column AS parent_id", 'COUNT(id) AS total')
            ->from($dbal->quoteIdentifier($this->getMapper()->toDb($this->entityName)))
            ->where("$column IN (:ids)")
            ->andWhere('deleted = :false')
            ->groupBy($column)
            ->setParameter('ids', array_values($ids), Connection::PARAM_STR_ARRAY)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        return array_map('intval', array_column($rows, 'total', 'parent_id'));
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        $this->validateTreeParent($entity);
    }

    /**
     * The parent of a record in the tree cannot be the record itself or one of its descendants.
     */
    protected function validateTreeParent(Entity $entity): void
    {
        if (!$this->isTree() || !$entity->isAttributeChanged('parentId') || empty($entity->get('parentId'))) {
            return;
        }

        $parentId = (string)$entity->get('parentId');
        if ($parentId === $entity->get('id') || in_array($entity->get('id'), $this->getTreeAncestorsIds($parentId), true)) {
            throw new BadRequest($this->translateException('treeParentCycle'));
        }
    }

    public function prepareAttributesForOutput(EntityCollection $collection, array $params): void
    {
        if (empty($params['attributesIds']) && empty($params['allAttributes'])) {
            return;
        }

        if (!empty($params['allAttributes'])) {
            foreach ($collection as $entity) {
                $this->getAttributeFieldConverter()->putAttributesToEntity($entity);
            }
        }

        if (empty($params['completeAttrDefs'])) {
            foreach ($collection as $entity) {
                if (!empty($entity->get('attributesDefs'))) {
                    $attributesDefs = [];
                    foreach ($entity->get('attributesDefs') as $field => $defs) {
                        $attributesDefs[$field]['attributeId'] = $defs['attributeId'];
                        $attributesDefs[$field]['type']        = $defs['type'];
                    }
                    $entity->set('attributesDefs', $attributesDefs);
                }
            }
        }
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->has('modifiedAt') && $entity->isAttributeChanged('modifiedAt') && empty($options['skipUpdateMasterRecord'])) {
            $this->updateMasterRecord($entity);
        }
    }

    protected function updateMasterRecord(Entity $entity): void
    {
        $masterEntityName = $this->getMetadata()->get("scopes.{$entity->getEntityName()}.primaryEntityId");
        if (!$masterEntityName) {
            return;
        }

        $consolidation = $this->getEntityManager()->getRepository('Consolidation')->getByEntityName($masterEntityName);
        if (empty($consolidation) || empty($consolidation->get('updateMasterAutomatically'))) {
            return;
        }

        try {
            $this->container->get('serviceFactory')->create('Consolidation')->updateMasterForContributor($entity);
        } catch (Forbidden|BadRequest $e) {
            // ignore
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->unlinkAllStagings($entity);

        // update modifiedAt for related entities
        foreach ($this->getMetadata()->get(['entityDefs', $this->entityName, 'links'], []) as $link => $defs) {
            if (empty($defs['entity']) || empty($defs['relationName']) || empty($defs['foreign'])) {
                continue;
            }
            if (in_array($defs['foreign'], $this->getMetadata()->get(['scopes', $defs['entity'], 'modifiedExtendedRelations'], []))) {
                $entity->loadLinkMultipleField($link);
                foreach ($entity->get($link . 'Ids') ?? [] as $id) {
                    $this->getPseudoTransactionManager()->pushUpdateEntityJob($defs['entity'], $id, [
                        'modifiedAt'   => (new \DateTime())->format('Y-m-d H:i') . ':00',
                        'modifiedById' => $this->getEntityManager()->getUser()->get('id')
                    ]);
                }
            }
        }

        $this->getEntityManager()->getRepository('MatchedRecord')->afterRemoveRecord($entity->getEntityName(), $entity->get('id'));

        $this->getEntityManager()->getRepository('ClusterItem')->afterRemoveRecord($entity->getEntityName(), $entity->get('id'));

        $this->getEntityManager()->getRepository('SelectionItem')->afterRemoveRecord($entity->getEntityName(), $entity->get('id'));
    }

    /**
     * Unlinks all staging records associated with the given entity by setting
     * the `master_record` field to null where it matches the entity's ID.
     *
     * @param Entity $entity The entity whose associated staging records should be unlinked.
     *
     * @return void
     */
    public function unlinkAllStagings(Entity $entity): void
    {
        foreach ($this->getMetadata()->get("scopes") ?? [] as $scope => $scopeData) {
            if (!empty($scopeData['primaryEntityId']) && $scopeData['primaryEntityId'] === $this->entityName) {
                $this->getDbal()->createQueryBuilder()
                    ->update($this->getDbal()->quoteIdentifier(Util::toUnderScore(lcfirst($scope))))
                    ->set('master_record_id', ':null')
                    ->where('master_record_id=:id')
                    ->setParameter('id', $entity->id)
                    ->setParameter('null', null, ParameterType::NULL)
                    ->executeQuery();
            }
        }
    }

    public function getLinkedIds(string $field, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $column = $this->getMapper()->toDb($field);

        $dbal = $this->getDbal();
        $rows = $dbal->createQueryBuilder()
            ->select('id', $dbal->quoteIdentifier($column) . ' AS foreign_id')
            ->from($dbal->quoteIdentifier($this->getMapper()->toDb($this->entityType)))
            ->where('id IN (:ids)')
            ->andWhere($dbal->quoteIdentifier($column) . ' IS NOT NULL')
            ->andWhere('deleted=:false')
            ->setParameter('ids', array_values($ids), Connection::PARAM_STR_ARRAY)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        return array_column($rows, 'foreign_id', 'id');
    }

    public function getReferencingIds(string $field, array $referencedIds): array
    {
        if (empty($referencedIds)) {
            return [];
        }

        $column = $this->getMapper()->toDb($field);

        $dbal = $this->getDbal();
        $rows = $dbal->createQueryBuilder()
            ->select('id', $dbal->quoteIdentifier($column) . ' AS foreign_id')
            ->from($dbal->quoteIdentifier($this->getMapper()->toDb($this->entityType)))
            ->where($dbal->quoteIdentifier($column) . ' IN (:referencedIds)')
            ->andWhere('deleted=:false')
            ->orderBy('id')
            ->setParameter('referencedIds', array_values($referencedIds), Connection::PARAM_STR_ARRAY)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        return array_column($rows, 'foreign_id', 'id');
    }

    /**
     * The record of the subtype the record belongs to. Null when the entity has no subtypes or the record has no
     * subtype data. It is kept in the memory storage for the request, like the records got by ID.
     */
    public function getSubtypeRecord(Entity $entity): ?Entity
    {
        if (empty($entity->get('id')) || empty($entity->get('type'))) {
            return null;
        }

        if (empty($this->getMetadata()->get(['scopes', $this->entityName, 'hasSubtypes']))) {
            return null;
        }

        $subtype = $this->getMetadata()->getSubtypeScope($this->entityName, (string)$entity->get('type'));
        if (empty($subtype)) {
            return null;
        }

        $key = self::getSubtypeRecordCacheKey($this->entityName, (string)$entity->get('id'));
        $memoryStorage = $this->getEntityManager()->getMemoryStorage();
        if (!$memoryStorage->has($key)) {
            $memoryStorage->set($key, $this->getEntityManager()->getRepository($subtype)->get($entity->get('id')));
        }

        return $memoryStorage->get($key);
    }

    public static function getSubtypeRecordCacheKey(string $supertype, string $id): string
    {
        return "subtype_record_{$supertype}_{$id}";
    }

    protected function beforeRestore($id)
    {
        parent::beforeRestore($id);

        $this->validateSubtypeOfRestoredRecord((string)$id);
    }

    /**
     * When the entity has subtypes, each record must belong to one of them. A record without the subtype data
     * cannot be restored.
     */
    protected function validateSubtypeOfRestoredRecord(string $id): void
    {
        if (empty($this->getMetadata()->get(['scopes', $this->entityName, 'hasSubtypes']))) {
            return;
        }

        $connection = $this->getDbal();

        $type = $connection->createQueryBuilder()
            ->select('type')
            ->from($connection->quoteIdentifier($this->getMapper()->toDb($this->entityName)))
            ->where('id = :id')
            ->setParameter('id', $id)
            ->fetchOne();

        $subtype = $this->getMetadata()->getSubtypeScope($this->entityName, empty($type) ? null : (string)$type);
        if (!empty($subtype)) {
            $subtypeRecordId = $connection->createQueryBuilder()
                ->select('id')
                ->from($connection->quoteIdentifier($this->getMapper()->toDb($subtype)))
                ->where('id = :id')
                ->setParameter('id', $id)
                ->fetchOne();

            if (!empty($subtypeRecordId)) {
                return;
            }
        }

        throw new BadRequest($this->translateException('recordWithoutSubtypeCannotBeRestored'));
    }

    public function hasDeletedRecordsToClear(): bool
    {
        if (empty($this->seed)) {
            return false;
        }

        $clearDays = $this->getMetadata()->get(['scopes', $this->entityName, 'clearDeletedAfterDays']) ?? 60;

        $tableName = $this->getEntityManager()->getMapper()->toDb($this->entityName);

        $qb = $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from($this->getConnection()->quoteIdentifier($tableName))
            ->where('deleted=:true')
            ->setParameter('true', true, ParameterType::BOOLEAN);

        $date = new \DateTime();
        if ($clearDays > 0) {
            $date->modify("-{$clearDays} days");
        }
        $date = $date->format('Y-m-d H:i:s');

        if ($this->seed->hasField('modifiedAt')) {
            if ($this->seed->hasField('createdAt')) {
                $qb->andWhere('modified_at<:date OR (modified_at IS NULL AND created_at<:date)');
            } else {
                $qb->andWhere('modified_at<:date OR modified_at IS NULL');
            }
            $qb->setParameter('date', $date);
        } elseif ($this->seed->hasField('createdAt')) {
            $qb->andWhere('created_at<:date OR created_at IS NULL');
            $qb->setParameter('date', $date);
        }

        return !empty($qb->fetchOne());
    }

    public function clearDeletedRecords(): void
    {
        if (empty($this->seed)) {
            return;
        }

        $clearDays = $this->getMetadata()->get(['scopes', $this->entityName, 'clearDeletedAfterDays']) ?? 60;

        $date = new \DateTime();
        if ($clearDays > 0) {
            $date->modify("-{$clearDays} days");
        }
        $date = $date->format('Y-m-d H:i:s');

        $tableName = $this->getEntityManager()->getMapper()->toDb($this->entityName);

        $qb = $this->getConnection()->createQueryBuilder()
            ->delete($this->getConnection()->quoteIdentifier($tableName))
            ->where('deleted=:true')
            ->setParameter('true', true, ParameterType::BOOLEAN);

        if ($this->seed->hasField('modifiedAt')) {
            if ($this->seed->hasField('createdAt')) {
                $qb->andWhere('modified_at<:date OR (modified_at IS NULL AND created_at<:date)');
            } else {
                $qb->andWhere('modified_at<:date OR modified_at IS NULL');
            }
            $qb->setParameter('date', $date);
        } elseif ($this->seed->hasField('createdAt')) {
            $qb->andWhere('created_at<:date OR created_at IS NULL');
            $qb->setParameter('date', $date);
        }

        $qb->executeQuery();
    }

    protected function getAttributeFieldConverter(): AttributeFieldConverter
    {
        return $this->container->get(AttributeFieldConverter::class);
    }

    protected function getPseudoTransactionManager(): PseudoTransactionManager
    {
        return $this->container->get('pseudoTransactionManager');
    }

    protected function translateException(string $key): string
    {
        return $this->container->get('language')->translate($key, 'exceptions', $this->entityName);
    }
}
