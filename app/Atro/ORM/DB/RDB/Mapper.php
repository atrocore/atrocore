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

namespace Atro\ORM\DB\RDB;

use Atro\ORM\DB\MapperInterface;
use Atro\ORM\DB\RDB\Query\QueryConverter;
use Atro\ORM\DB\RDB\QueryCallbacks\JoinManyToMany;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\EntityFactory;
use Espo\ORM\IEntity;

class Mapper implements MapperInterface
{
    protected Connection $connection;
    protected EntityFactory $entityFactory;
    protected QueryConverter $queryConverter;

    public function __construct(Connection $connection, EntityFactory $entityFactory)
    {
        $this->connection = $connection;
        $this->entityFactory = $entityFactory;
        $this->queryConverter = new QueryConverter($this->entityFactory, $this->connection);
    }

    public function selectById(IEntity $entity, string $id, $params = []): IEntity
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

    public function select(IEntity $entity, array $params): array
    {
        try {
            $queryData = $this->queryConverter->createSelectQuery($entity->getEntityType(), $params, !empty($params['withDeleted']));
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
            echo '2023-10-17 TODO: group by' . PHP_EOL;
            print_r($queryData);
            die();
        }

        if (!empty($queryData['having'])) {
            echo '2023-10-17 TODO: having' . PHP_EOL;
            print_r($queryData);
            die();
        }

        if (!empty($params['callbacks'])) {
            foreach ($params['callbacks'] as $callback) {
                call_user_func($callback, $qb, $entity, $params);
            }
        }

        $res = $qb->fetchAllAssociative();

        foreach ($res as $k => $row) {
            foreach ($row as $field => $value) {
                $res[$k][$this->queryConverter->aliasToField($field)] = $value;
            }
        }

        return $res;
    }

    public function count(IEntity $entity, array $params = []): int
    {
        $params['aggregation'] = 'COUNT';
        $params['aggregationBy'] = 'id';

        $res = $this->select($entity, $params);
        foreach ($res as $row) {
            return $row[QueryConverter::AGGREGATE_VALUE] ?? 0;
        }

        return 0;
    }

    public function selectRelated(IEntity $entity, string $relationName, array $params = [], bool $totalCount = false)
    {
        $relOpt = $entity->relations[$relationName];

        if (!isset($relOpt['type'])) {
            throw new \LogicException("Missing 'type' in definition for relationship {$relationName} in " . $entity->getEntityType() . " entity");
        }

        if ($relOpt['type'] !== IEntity::BELONGS_TO_PARENT) {
            if (!isset($relOpt['entity'])) {
                throw new \LogicException("Missing 'entity' in definition for relationship {$relationName} in " . $entity->getEntityType() . " entity");
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

        $keySet = $this->getKeys($entity, $relationName);

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
                            return $row[QueryConverter::AGGREGATE_VALUE];
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
                            return $row[QueryConverter::AGGREGATE_VALUE];
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
                $params['callbacks'][] = [new JoinManyToMany($entity, $relationName, $keySet, $this->queryConverter), 'run'];

                $resultArr = [];
                $rows = $this->select($relEntity, $params);
                if ($rows) {
                    if (!$totalCount) {
                        $resultArr = $rows;
                    } else {
                        foreach ($rows as $row) {
                            return $row[QueryConverter::AGGREGATE_VALUE];
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
                            return $row[QueryConverter::AGGREGATE_VALUE];
                        }
                    }
                }
                return null;
        }

        return null;
    }

    public function countRelated(IEntity $entity, string $relName, array $params)
    {
        return $this->selectRelated($entity, $relName, $params, true);
    }

    public function addRelation(IEntity $entity, string $relName, string $id = null, IEntity $relEntity = null, array $data = null): bool
    {
        if (!is_null($relEntity)) {
            $id = $relEntity->id;
        }

        if (empty($id) || empty($relName)) {
            return false;
        }

        $relOpt = $entity->relations[$relName];

        if (!isset($relOpt['entity']) || !isset($relOpt['type'])) {
            return false;
        }

        $relType = $relOpt['type'];

        $className = (!empty($relOpt['class'])) ? $relOpt['class'] : $relOpt['entity'];

        if (is_null($relEntity)) {
            $relEntity = $this->entityFactory->create($className);
            if (!$relEntity) {
                return false;
            }
            $relEntity->id = $id;
        }

        $keySet = $this->getKeys($entity, $relName);

        if ($relType !== IEntity::MANY_MANY) {
            return false;
        }

        $nearKey = $keySet['nearKey'];
        $distantKey = $keySet['distantKey'];

        if ($this->count($relEntity, ['whereClause' => ['id' => $id]]) > 0) {
            $relTable = $this->toDb($relOpt['relationName']);

            $qb = $this->connection->createQueryBuilder();
            $qb
                ->select('*')
                ->from($this->connection->quoteIdentifier($relTable))
                ->where("{$this->toDb($nearKey)} = :$nearKey")
                ->setParameter($nearKey, $entity->id, self::getParameterType($entity->id))
                ->andWhere("{$this->toDb($distantKey)} = :$distantKey")
                ->setParameter($distantKey, $relEntity->id, self::getParameterType($relEntity->id));

            if (!empty($relOpt['conditions']) && is_array($relOpt['conditions'])) {
                foreach ($relOpt['conditions'] as $f => $v) {
                    $qb->andWhere("{$this->toDb($f)} = :$f");
                    $qb->setParameter($f, $v, self::getParameterType($v));
                }
            }

            $res = $qb->fetchAssociative();

            if (empty($res)) {
                $qb = $this->connection->createQueryBuilder();
                $qb->insert($relTable);

                $qb->setValue($this->toDb($nearKey), ":$nearKey")->setParameter($nearKey, $entity->id);
                $qb->setValue($this->toDb($distantKey), ":$distantKey")->setParameter($distantKey, $relEntity->id);

                if (!empty($relOpt['conditions']) && is_array($relOpt['conditions'])) {
                    foreach ($relOpt['conditions'] as $f => $v) {
                        $qb->setValue($this->toDb($f), ":$f")->setParameter($f, $v);
                    }
                }
                if (!empty($data) && is_array($data)) {
                    foreach ($data as $column => $columnValue) {
                        $qb->setValue($this->toDb($column), ":$column")->setParameter($column, $columnValue);
                    }
                }

                $qb->executeQuery();

                $this->updateModifiedAtForManyToMany($entity, $relEntity);

                return true;
            } else {
                $qb = $this->connection->createQueryBuilder();
                $qb->update($relTable)
                    ->set('deleted', ':deleted')->setParameter('deleted', false, self::getParameterType(false));
                if (!empty($data) && is_array($data)) {
                    foreach ($data as $column => $value) {
                        $qb->set($this->toDb($column), ":$column")->setParameter($column, $value, self::getParameterType($value));
                    }
                }

                $qb->where("{$this->toDb($nearKey)} = :$nearKey")->setParameter($nearKey, $entity->id, self::getParameterType($entity->id));
                $qb->andWhere("{$this->toDb($distantKey)} = :$distantKey")->setParameter($distantKey, $relEntity->id, self::getParameterType($relEntity->id));

                if (!empty($relOpt['conditions']) && is_array($relOpt['conditions'])) {
                    foreach ($relOpt['conditions'] as $f => $v) {
                        $qb->andWhere("{$this->toDb($f)} = :$f")->setParameter($f, $v, self::getParameterType($v));
                    }
                }

                $qb->executeQuery();
                $this->updateModifiedAtForManyToMany($entity, $relEntity);

                return true;
            }
        }

        return false;
    }

    public function removeRelation(IEntity $entity, string $relationName, string $id = null, bool $all = false, IEntity $relEntity = null, bool $force = false): bool
    {
        if (!is_null($relEntity)) {
            $id = $relEntity->id;
        }

        if (empty($id) && empty($all) || empty($relationName)) {
            return false;
        }

        $relOpt = $entity->relations[$relationName];

        if (!isset($relOpt['entity']) || !isset($relOpt['type'])) {
            throw new \LogicException("Not appropriate definition for relationship {$relationName} in " . $entity->getEntityType() . " entity");
        }

        $relType = $relOpt['type'];

        $className = (!empty($relOpt['class'])) ? $relOpt['class'] : $relOpt['entity'];

        if (is_null($relEntity)) {
            $relEntity = $this->entityFactory->create($className);
            if (!$relEntity) {
                return false;
            }
            $relEntity->id = $id;
        }

        $keySet = $this->getKeys($entity, $relationName);

        if ($relType !== IEntity::MANY_MANY) {
            return false;
        }

        $nearKey = $keySet['nearKey'];
        $distantKey = $keySet['distantKey'];

        $relTable = $this->toDb($relOpt['relationName']);

        $qb = $this->connection->createQueryBuilder();

        if ($force) {
            $qb->delete($this->connection->quoteIdentifier($relTable));
        } else {
            $qb
                ->update($this->connection->quoteIdentifier($relTable))
                ->set('deleted', ':deleted')
                ->setParameter('deleted', true, self::getParameterType(true));
        }

        $qb->where("{$this->toDb($nearKey)} = :$nearKey");
        $qb->setParameter($nearKey, $entity->id, self::getParameterType($entity->id));

        if (empty($all)) {
            $qb->andWhere("{$this->toDb($distantKey)} = :$distantKey");
            $qb->setParameter($distantKey, $id, self::getParameterType($id));
        }

        if (!empty($relOpt['conditions']) && is_array($relOpt['conditions'])) {
            foreach ($relOpt['conditions'] as $f => $v) {
                $qb->andWhere("{$this->toDb($f)} = :$f");
                $qb->setParameter($f, $v, self::getParameterType($v));
            }
        }

        $qb->executeQuery();

        $this->updateModifiedAtForManyToMany($entity, $relEntity);

        return true;
    }

    public function insert(IEntity $entity): bool
    {
        $dataArr = $this->toValueMap($entity);

        if (!empty($dataArr)) {
            $qb = $this->connection->createQueryBuilder();

            $qb->insert($this->connection->quoteIdentifier($this->toDb($entity->getEntityType())));
            foreach ($dataArr as $field => $value) {
                $value = $this->prepareValueForUpdate($entity->fields[$field]['type'], $value);
                $qb->setValue($this->connection->quoteIdentifier($this->toDb($field)), ":i_$field");
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

    public function update(IEntity $entity): bool
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
            return false;
        }

        $qb = $this->connection->createQueryBuilder();

        $qb->update($this->connection->quoteIdentifier($this->toDb($entity->getEntityType())));
        foreach ($setArr as $field => $value) {
            $qb->set($this->connection->quoteIdentifier($this->toDb($field)), ":u_$field");
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

    public function delete(IEntity $entity): bool
    {
        $entity->set('deleted', true);

        return $this->update($entity);
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

    protected function updateModifiedAtForManyToMany(IEntity $entity, IEntity $relEntity): void
    {
        $this->updateModifiedAt($entity);
        $this->updateModifiedBy($entity);

        $this->updateModifiedAt($relEntity);
        $this->updateModifiedBy($relEntity);
    }

    protected function updateModifiedAt(IEntity $entity)
    {
        try {
            $this->connection->createQueryBuilder()
                ->update($this->connection->quoteIdentifier($this->toDb($entity->getEntityType())))
                ->set('modified_at', date('Y-m-d H:i:s'))
                ->where('id = :id')
                ->setParameter('id', $entity->get('id'))
                ->executeQuery();
        } catch (\Throwable $e) {
        }
    }

    protected function updateModifiedBy(IEntity $entity)
    {
        try {
            $userId = $this->entityFactory->getEntityManager()->getUser()->get('id');
            $this->connection->createQueryBuilder()
                ->update($this->connection->quoteIdentifier($this->toDb($entity->getEntityType())))
                ->set('modified_by_id', $userId)
                ->where('id = :id')
                ->setParameter('id', $entity->get('id'))
                ->executeQuery();
        } catch (\Throwable $e) {
        }
    }

    public function toDb(string $field): string
    {
        return $this->queryConverter->toDb($field);
    }

    public function getKeys(IEntity $entity, string $relationName): array
    {
        return $this->queryConverter->getKeys($entity, $relationName);
    }
}
