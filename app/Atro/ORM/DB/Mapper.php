<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\ORM\DB;

use Atro\ORM\DB\Query\QueryMapper;
use Atro\ORM\DB\QueryCallbacks\JoinManyToMany;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\DB\IMapper;
use Espo\ORM\IEntity;
use Espo\ORM\EntityFactory;
use Espo\ORM\EntityCollection;
use PDO;

class Mapper implements IMapper
{
    protected Connection $connection;
    protected EntityFactory $entityFactory;
    protected QueryMapper $queryMapper;

    protected string $collectionClass = EntityCollection::class;

    public function __construct(Connection $connection, EntityFactory $entityFactory)
    {
        $this->connection = $connection;
        $this->entityFactory = $entityFactory;
        $this->queryMapper = new \Atro\ORM\DB\Query\QueryMapper($this->entityFactory, $this->connection);
    }

    public function selectById(IEntity $entity, $id, $params = []): IEntity
    {
        $params['whereClause']['id'] = $id;

        $res = $this->select($entity, $params);
        foreach ($res as $row) {
            $entity->set($row);
            $entity->setAsFetched();
            break;
        }

        return $entity;
    }

    public function select(IEntity $entity, $params): array
    {
        try {
            $queryData = $this->queryMapper->createSelectQuery($entity->getEntityType(), $params, !empty($params['withDeleted']));
        } catch (\Throwable $e) {
            $GLOBALS['log']->error("RDB QUERY failed: {$e->getMessage()}");
        }

        $qb = $this->connection->createQueryBuilder();

        foreach ($queryData['select'] ?? [] as $item) {
            $qb->addSelect($item);
        }

        if (!empty($queryData['distinct'])) {
            $qb->distinct();
        }

        $qb->from($this->connection->quoteIdentifier($queryData['table']['tableName']), $queryData['table']['tableAlias']);
        $qb->andWhere($queryData['where']);

        if (!empty($queryData['joins'])) {
            foreach ($queryData['joins'] as $v) {
                $qb->add('join', [
                    $v['fromAlias'] => [
                        'joinType'      => $v['type'],
                        'joinTable'     => $v['table'],
                        'joinAlias'     => $v['alias'],
                        'joinCondition' => $v['condition'],
                    ],
                ], true);
            }
        }

        foreach ($queryData['parameters'] ?? [] as $parameterName => $value) {
            $qb->setParameter($parameterName, $value, self::getParameterType($value));
        }

        if (isset($queryData['offset'])) {
            $qb->setFirstResult($params['offset']);
        }

        if (isset($queryData['limit'])) {
            $qb->setMaxResults($params['limit']);
        }

        if (!empty($queryData['order'])) {
            if (is_string($queryData['order'])) {
                $qb->add('orderBy', $queryData['order'], true);
            } elseif (is_array($queryData['order'])) {
                foreach ($queryData['order'] as $v) {
                    $qb->add('orderBy', $v, true);
                }
            }
        }

        if (!empty($queryData['groupBy'])) {
            echo 'TODO: group by' . PHP_EOL;
            print_r($queryData);
            die();
        }

        if (!empty($queryData['having'])) {
            echo 'TODO: having' . PHP_EOL;
            print_r($queryData);
            die();
        }

        if (!empty($params['callbacks'])) {
            foreach ($params['callbacks'] as $callback) {
                call_user_func($callback, $qb, $entity, $params);
            }
        }

        try {
            $res = $qb->fetchAllAssociative();
        } catch (\Throwable $e) {
            $GLOBALS['log']->error("RDB SELECT failed: {$e->getMessage()}");
        }

        return $res;
    }

    public function aggregate(IEntity $entity, $params, $aggregation, $aggregationBy, $deleted = false)
    {
        echo 'TODO: aggregate' . PHP_EOL;
        die();
    }

    public function count(IEntity $entity, $params = []): int
    {
        $params['aggregation'] = 'COUNT';
        $params['aggregationBy'] = 'id';

        $res = $this->select($entity, $params);
        foreach ($res as $row) {
            return $row['AggregateValue'] ?? 0;
        }

        return 0;
    }

    public function max(IEntity $entity, $params, $field, $deleted = false)
    {
        echo 'TODO: max' . PHP_EOL;
        die();
    }

    public function min(IEntity $entity, $params, $field, $deleted = false)
    {
        echo 'TODO: min' . PHP_EOL;
        die();
    }

    public function sum(IEntity $entity, $params)
    {
        echo 'TODO: sum' . PHP_EOL;
        die();
    }

    public function selectRelated(IEntity $entity, $relName, $params = [], $totalCount = false)
    {
        $relOpt = $entity->relations[$relName];

        if (!isset($relOpt['type'])) {
            throw new \LogicException("Missing 'type' in definition for relationship {$relName} in " . $entity->getEntityType() . " entity");
        }

        if ($relOpt['type'] !== IEntity::BELONGS_TO_PARENT) {
            if (!isset($relOpt['entity'])) {
                throw new \LogicException("Missing 'entity' in defenition for relationship {$relName} in " . $entity->getEntityType() . " entity");
            }

            $relEntityName = (!empty($relOpt['class'])) ? $relOpt['class'] : $relOpt['entity'];
            $relEntity = $this->entityFactory->create($relEntityName);

            if (!$relEntity) {
                return null;
            }
        }

        if ($totalCount) {
            $params['aggregation'] = 'COUNT';
            $params['aggregationBy'] = 'id';
        }

        if (empty($params['whereClause'])) {
            $params['whereClause'] = [];
        }

        $relType = $relOpt['type'];

        $keySet = $this->queryMapper->getKeys($entity, $relName);

        $key = $keySet['key'];
        $foreignKey = $keySet['foreignKey'];

        switch ($relType) {
            case IEntity::BELONGS_TO:
                $params['whereClause'][$foreignKey] = $entity->get($key);
                $params['offset'] = 0;
                $params['limit'] = 1;

                $rows = $this->select($relEntity, $params);

                if ($rows) {
                    foreach ($rows as $row) {
                        if (!$totalCount) {
                            $relEntity->set($row);
                            $relEntity->setAsFetched();
                            return $relEntity;
                        } else {
                            return $row['AggregateValue'];
                        }
                    }
                }
                return null;
            case IEntity::HAS_MANY:
            case IEntity::HAS_CHILDREN:
            case IEntity::HAS_ONE:
                $params['whereClause'][$foreignKey] = $entity->get($key);

                if ($relType == IEntity::HAS_CHILDREN) {
                    $foreignType = $keySet['foreignType'];
                    $params['whereClause'][$foreignType] = $entity->getEntityType();
                }

                if ($relType == IEntity::HAS_ONE) {
                    $params['offset'] = 0;
                    $params['limit'] = 1;
                }

                $resultArr = [];

                $rows = $this->select($relEntity, $params);
                if ($rows) {
                    if (!$totalCount) {
                        $resultArr = $rows;
                    } else {
                        foreach ($rows as $row) {
                            return $row['AggregateValue'];
                        }
                    }
                }

                if ($relType == IEntity::HAS_ONE) {
                    if (count($resultArr)) {
                        $relEntity->set($resultArr[0]);
                        $relEntity->setAsFetched();
                        return $relEntity;
                    }
                    return null;
                } else {
                    return $resultArr;
                }

            case IEntity::MANY_MANY:
                $params['relationName'] = $relOpt['relationName'];
                $params['callbacks'][] = [new JoinManyToMany($entity, $relName, $keySet, $this->queryMapper), 'run'];

                $resultArr = [];
                $rows = $this->select($relEntity, $params);
                if ($rows) {
                    if (!$totalCount) {
                        $resultArr = $rows;
                    } else {
                        foreach ($rows as $row) {
                            return $row['AggregateValue'];
                        }
                    }
                }
                return $resultArr;
            case IEntity::BELONGS_TO_PARENT:
                $foreignEntityType = $entity->get($keySet['typeKey']);
                $foreignEntityId = $entity->get($key);
                if (!$foreignEntityType || !$foreignEntityId) {
                    return null;
                }
                $params['whereClause'][$foreignKey] = $foreignEntityId;
                $params['offset'] = 0;
                $params['limit'] = 1;

                $relEntity = $this->entityFactory->create($foreignEntityType);

                $rows = $this->select($relEntity, $params);

                if ($rows) {
                    foreach ($rows as $row) {
                        if (!$totalCount) {
                            $relEntity->set($row);
                            return $relEntity;
                        } else {
                            return $row['AggregateValue'];
                        }
                    }
                }
                return null;
        }

        return null;
    }

    public function countRelated(IEntity $entity, $relName, $params)
    {
        echo 'TODO: countRelated' . PHP_EOL;
        die();
    }

    public function addRelation(IEntity $entity, $relName, $id)
    {
        echo 'TODO: addRelation' . PHP_EOL;
        die();
    }

    public function removeRelation(IEntity $entity, $relName, $id)
    {
        echo 'TODO: removeRelation' . PHP_EOL;
        die();
    }

    public function removeAllRelations(IEntity $entity, $relName)
    {
        echo 'TODO: removeAllRelations' . PHP_EOL;
        die();
    }

    public function insert(IEntity $entity)
    {
        $dataArr = $this->toValueMap($entity);

        if (!empty($dataArr)) {
            $qb = $this->connection->createQueryBuilder();

            $qb->insert($this->connection->quoteIdentifier($this->queryMapper->toDb($entity->getEntityType())));
            foreach ($dataArr as $field => $value) {
                $value = $this->prepareValueForUpdate($entity->fields[$field]['type'], $value);
                $qb->setValue($this->connection->quoteIdentifier($this->queryMapper->toDb($field)), ":i_$field");
                $qb->setParameter("i_$field", $value, self::getParameterType($value));
            }

            try {
                $qb->executeQuery();
            } catch (\Throwable $e) {
                $GLOBALS['log']->error("RDB INSERT failed: {$e->getMessage()}");
                return false;
            }
        }

        return true;
    }

    public function update(IEntity $entity)
    {
        $setArr = [];
        foreach ($this->toValueMap($entity) as $attribute => $value) {
            if ($attribute == 'id') {
                continue;
            }
            $type = $entity->getAttributeType($attribute);

            if ($type == IEntity::FOREIGN) {
                continue;
            }

            if (!$entity->isAttributeChanged($attribute) && $type !== IEntity::JSON_OBJECT) {
                continue;
            }

            $setArr[$attribute] = $this->prepareValueForUpdate($type, $value);
        }

        if (count($setArr) == 0) {
            return $entity->id;
        }

        $qb = $this->connection->createQueryBuilder();

        $qb->update($this->connection->quoteIdentifier($this->queryMapper->toDb($entity->getEntityType())));
        foreach ($setArr as $field => $value) {
            $qb->set($this->connection->quoteIdentifier($this->queryMapper->toDb($field)), ":u_$field");
            $qb->setParameter("u_$field", $value, self::getParameterType($value));
        }

        $qb->where('id = :id');
        $qb->setParameter('id', $entity->id, self::getParameterType($entity->id));
        $qb->andWhere('deleted = :deleted');
        $qb->setParameter('deleted', false, self::getParameterType(false));

        try {
            $qb->executeQuery();
        } catch (\Throwable $e) {
            $GLOBALS['log']->error("RDB UPDATE failed: {$e->getMessage()}");
            return false;
        }

        return true;
    }

    public function delete(IEntity $entity)
    {
        $entity->set('deleted', true);

        return $this->update($entity);
    }

    public function setCollectionClass($collectionClass)
    {
        $this->collectionClass = $collectionClass;
    }

    public static function getParameterType($value): ?int
    {
        if (is_bool($value)) {
            return ParameterType::BOOLEAN;
        }

        if (is_array($value)) {
            $res = Connection::PARAM_INT_ARRAY;
            if (!empty($value[0]) && is_string($value[0])) {
                $res = Connection::PARAM_STR_ARRAY;;
            }

            return $res;
        }

        return null;
    }

    public function toValueMap(IEntity $entity, bool $onlyStorable = true): array
    {
        $data = [];
        foreach ($entity->getAttributes() as $attribute => $defs) {
            if ($entity->has($attribute)) {
                if ($onlyStorable) {
                    if (
                        !empty($defs['notStorable'])
                        || !empty($defs['autoincrement'])
                        || isset($defs['source']) && $defs['source'] != 'db'
                    ) {
                        continue;
                    }
                    if ($defs['type'] == IEntity::FOREIGN) {
                        continue;
                    }
                }
                $data[$attribute] = $entity->get($attribute);
            }
        }

        return $data;
    }

    public function prepareValueForUpdate($type, $value)
    {
        if ($type == IEntity::JSON_ARRAY && is_array($value)) {
            $value = json_encode($value, \JSON_UNESCAPED_UNICODE);
        } else {
            if ($type == IEntity::JSON_OBJECT && (is_array($value) || $value instanceof \stdClass)) {
                $value = json_encode($value, \JSON_UNESCAPED_UNICODE);
            }
        }

        if ($type === IEntity::BOOL && $value === null) {
            $value = false;
        }

        return $value;
    }
}
