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

use Atro\ORM\DB\QueryCallbacks\JoinManyToMany;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\DB\IMapper;
use Espo\ORM\IEntity;
use Espo\ORM\EntityFactory;
use Espo\ORM\EntityCollection;

class Mapper implements IMapper
{
    public const TABLE_ALIAS = 't1';

    protected Connection $connection;
    protected EntityFactory $entityFactory;
    protected string $collectionClass = EntityCollection::class;

    protected array $fieldsMapCache = [];
    protected array $relationAliases = [];

    protected static array $selectParamList
        = [
            'select',
            'whereClause',
            'offset',
            'limit',
            'order',
            'orderBy',
            'customWhere',
            'customJoin',
            'joins',
            'leftJoins',
            'distinct',
            'joinConditions',
            'aggregation',
            'aggregationBy',
            'groupBy',
            'havingClause',
            'customHaving',
            'skipTextColumns',
            'maxTextColumnsLength'
        ];

    protected static array $sqlOperators
        = [
            'OR',
            'AND'
        ];

    protected static array $comparisonOperators
        = [
            '!=s' => 'NOT IN',
            '=s'  => 'IN',
            '!='  => '<>',
            '!*'  => 'NOT LIKE',
            '*'   => 'LIKE',
            '>='  => '>=',
            '<='  => '<=',
            '>'   => '>',
            '<'   => '<',
            '='   => '='
        ];

    public function __construct(Connection $connection, EntityFactory $entityFactory)
    {
        $this->connection = $connection;
        $this->entityFactory = $entityFactory;
    }

    public function selectById(IEntity $entity, $id, $params = []): IEntity
    {
        echo '<pre>';
        print_r('selectById');
        die();
    }

    public function select(IEntity $entity, $params): array
    {
        /**
         * Prepare params
         */
        foreach (self::$selectParamList as $k) {
            $params[$k] = array_key_exists($k, $params) ? $params[$k] : null;
        }
        if (empty($params['joins'])) {
            $params['joins'] = [];
        }
        if (empty($params['leftJoins'])) {
            $params['leftJoins'] = [];
        }
        if (empty($params['customJoin'])) {
            $params['customJoin'] = '';
        }

        $qb = $this->connection->createQueryBuilder();

        $qb->from($this->connection->quoteIdentifier($this->toDb($entity->getEntityType())), self::TABLE_ALIAS);
        $this->prepareWhere($entity, $qb, $params);

        if (!empty($params['havingClause'])) {
            print_r('$havingPart: Stop here!');
            die();
//            $havingPart = $this->getWhere($entity, $params['havingClause'], 'AND', $params);
        }
//
        if (empty($params['aggregation'])) {
            $this->prepareSelect($entity, $qb, $params);
            $this->prepareOrder($entity, $qb, $params);

            if (!empty($params['additionalColumns']) && is_array($params['additionalColumns']) && !empty($params['relationName'])) {
                foreach ($params['additionalColumns'] as $column => $field) {
                    $relationTableAlias = $this->getRelationAlias($entity, $params['relationName']);
                    $relColumnName = $this->toDb(self::sanitize($column));
                    $qb->addSelect("{$relationTableAlias}.{$relColumnName} AS {$this->connection->quoteIdentifier($field)}");
                    if ($params['orderBy'] === $field) {
                        $qb->addOrderBy("{$relationTableAlias}.{$relColumnName}", $this->prepareOrderParameter($params['order']));
                    }
                }
            }

            if (!empty($params['additionalSelectColumns']) && is_array($params['additionalSelectColumns'])) {
                echo '<pre>';
                print_r('q333');
                die();
//                foreach ($params['additionalSelectColumns'] as $column => $field) {
//                    $selectPart .= ", " . $this->selectFieldSQL($column, $field);
//                }
            }

        } else {
            if (!isset($params['aggregationBy']) || !isset($params['aggregation']) || !isset($entity->fields[$params['aggregationBy']])) {
                throw new \Error('Error in building aggregation select');
            }

            $aggregation = strtoupper($params['aggregation']);
            $distinctPart = '';
            if ($params['distinct'] && $params['aggregation'] == 'COUNT') {
                $distinctPart = 'DISTINCT ';
            }
            $qb->select("{$aggregation}({$distinctPart}" . self::TABLE_ALIAS . "." . $this->toDb($this->sanitize($params['aggregationBy'])) . ") AS AggregateValue");
        }

        $this->prepareBelongsToJoins($entity, $qb, $params);

        if (!empty($params['customWhere'])) {
            print_r('customWhere die here');
            die();
//            if (!empty($wherePart)) {
//                $wherePart .= ' ';
//            }
//            $wherePart .= $params['customWhere'];
        }

        if (!empty($params['customHaving'])) {
            print_r('customHaving die here');
            die();
//            if (!empty($havingPart)) {
//                $havingPart .= ' ';
//            }
//            $havingPart .= $params['customHaving'];
        }

        if (!empty($params['joins']) && is_array($params['joins'])) {
            print_r('joins die here');
            die();
//            // TODO array unique
//            $joinsRelated = $this->getJoins($entity, $params['joins'], false, $params['joinConditions']);
//            if (!empty($joinsRelated)) {
//                if (!empty($joinsPart)) {
//                    $joinsPart .= ' ';
//                }
//                $joinsPart .= $joinsRelated;
//            }
        }

        if (!empty($params['leftJoins']) && is_array($params['leftJoins'])) {
            print_r('leftJoins die here');
            die();
//            // TODO array unique
//            $joinsRelated = $this->getJoins($entity, $params['leftJoins'], true, $params['joinConditions']);
//            if (!empty($joinsRelated)) {
//                if (!empty($joinsPart)) {
//                    $joinsPart .= ' ';
//                }
//                $joinsPart .= $joinsRelated;
//            }
        }

        if (!empty($params['customJoin'])) {
            print_r('customJoin die here');
            die();
//            if (!empty($joinsPart)) {
//                $joinsPart .= ' ';
//            }
//            $joinsPart .= '' . $params['customJoin'] . '';
        }

        if (!empty($params['groupBy']) && is_array($params['groupBy'])) {
            print_r('groupBy die here');
            die();
//            $arr = array();
//            foreach ($params['groupBy'] as $field) {
//                $arr[] = $this->convertComplexExpression($entity, $field);
//            }
//            $groupByPart = implode(', ', $arr);
        }

        if (empty($params['aggregation'])) {
            $qb->setFirstResult($params['offset']);
            $qb->setMaxResults($params['limit']);
            if (!empty($params['distinct'])) {
                $qb->distinct();
            }
        } else {
            $groupByPart = !empty($params['groupBy']);
            $havingPart = !empty($params['havingClause']) || !empty($params['customHaving']);

            if ($params['aggregation'] === 'COUNT' && $groupByPart && $havingPart) {
                print_r('aggregation die here');
                die();
//                $sql = "SELECT COUNT(*) AS `AggregateValue` FROM ({$sql}) AS `countAlias`";
            }
        }

        if (!empty($params['callbacks'])) {
            foreach ($params['callbacks'] as $callback) {
                call_user_func($callback, $this, $qb, $entity, $params);
            }
        }

        try {
            $sql = $qb->getSQL();
            $res = $qb->fetchAllAssociative();
        } catch (\Throwable $e) {
            $GLOBALS['log']->error("RDB Mapper failed: {$e->getMessage()}" . PHP_EOL . "SQL: $sql");
        }

        return $res;
    }

    protected function prepareBelongsToJoins(IEntity $entity, QueryBuilder $qb, array &$params): void
    {
        $select = $params['select'] ?? null;
        $skipList = array_merge($params['joins'], $params['leftJoins']);

        $relationsToJoin = [];
        if (is_array($select)) {
            foreach ($select as $item) {
                $field = $item;
                if (is_array($item)) {
                    if (count($field) == 0) {
                        continue;
                    }
                    $field = $item[0];
                }
                if ($entity->getAttributeType($field) == 'foreign' && $entity->getAttributeParam($field, 'relation')) {
                    $relationsToJoin[] = $entity->getAttributeParam($field, 'relation');
                }
            }
        }

        foreach ($entity->relations as $relationName => $r) {
            if ($r['type'] == IEntity::BELONGS_TO) {
                if (!empty($r['noJoin'])) {
                    continue;
                }
                if (in_array($relationName, $skipList)) {
                    continue;
                }

                if (!empty($select) && !in_array($relationName, $relationsToJoin)) {
                    continue;
                }

                $this->prepareBelongsToJoin($entity, $qb, $relationName, 'left', $r);
            }
        }
    }

    protected function prepareBelongsToJoin(IEntity $entity, QueryBuilder $qb, string $relationName, string $type = 'left', array $r = null, string $alias = null)
    {
        if (empty($r)) {
            $r = $entity->relations[$relationName];
        }

        $keySet = $this->getRelationKeySet($entity, $relationName);
        $key = $keySet['key'];
        $foreignKey = $keySet['foreignKey'];

        if (!$alias) {
            $alias = $this->getRelationAlias($entity, $relationName);
        }

        switch ($type) {
            case 'left':
                $qb->leftJoin(self::TABLE_ALIAS, $this->toDb($r['entity']), $alias, self::TABLE_ALIAS . "." . $this->toDb($key) . " = " . $alias . "." . $this->toDb($foreignKey));
                break;
        }
    }

    protected function prepareSelect(IEntity $entity, QueryBuilder $qb, array &$params): void
    {
        $fields = $params['select'] ?? [];
        $distinct = $params['distinct'] ?? false;
        $skipTextColumns = !empty($params['skipTextColumns']);

//        $selectPart = $this->getSelect($entity, $params['select'], $params['distinct'], $params['skipTextColumns'], $params['maxTextColumnsLength']);

        $select = "";
        $arr = [];

        if (empty($fields)) {
            $attributeList = array_keys($entity->fields);
        } else {
            $attributeList = $fields;
            foreach ($attributeList as $i => $attribute) {
                if (!is_array($attribute)) {
                    $attributeList[$i] = self::sanitizeAlias($attribute);
                }
            }
        }

        foreach ($attributeList as $attribute) {
            $attributeType = null;
            if (is_string($attribute)) {
                $attributeType = $entity->getAttributeType($attribute);
            }
            if ($skipTextColumns) {
                if ($attributeType === $entity::TEXT) {
                    continue;
                }
            }

            if (is_array($attribute) && count($attribute) == 2) {
                if (stripos($attribute[0], 'VALUE:') === 0) {
                    $part = substr($attribute[0], 6);
                    print_r('VALUE: Stop here!');
                    die();
//                    if ($part !== false) {
//                        $part = $this->quote($part);
//                    } else {
//                        $part = $this->quote('');
//                    }
                } else {
                    if (!array_key_exists($attribute[0], $entity->fields)) {
                        print_r('convertComplexExpression: Stop here!');
                        die();

//                        $part = $this->convertComplexExpression($entity, $attribute[0], $distinct);
                    } else {
                        $fieldDefs = $entity->fields[$attribute[0]];
                        if (!empty($fieldDefs['select'])) {
                            $part = $fieldDefs['select'];
                        } else {
                            if (!empty($fieldDefs['notStorable']) || !empty($fieldDefs['noSelect'])) {
                                continue;
                            }
                            $part = $this->getFieldPath($entity, $attribute[0]);
                        }
                    }
                }

                $alias = self::sanitizeAlias($attribute[1]);
                $qb->addSelect("$part AS {$this->connection->quoteIdentifier($alias)}");
                continue;
            }

            if (array_key_exists($attribute, $entity->fields)) {
                $fieldDefs = $entity->fields[$attribute];
            } else {
                print_r('convertComplexExpression stop here2');
                die();
//                $part = $this->convertComplexExpression($entity, $attribute, $distinct);
//                $arr[] = $this->selectFieldSQL($part, $attribute);
                continue;
            }

            if (!empty($fieldDefs['select'])) {
                $fieldPath = $fieldDefs['select'];
            } else {
                if (!empty($fieldDefs['notStorable'])) {
                    continue;
                }
                if ($attributeType === null) {
                    continue;
                }
                $fieldPath = $this->getFieldPath($entity, $attribute);
            }

            $qb->addSelect("$fieldPath AS {$this->connection->quoteIdentifier($attribute)}");
        }
    }

    protected function prepareWhere(IEntity $entity, QueryBuilder $qb, array &$params): void
    {
        $whereClause = $params['whereClause'] ?? [];
        if (empty($params['withDeleted'])) {
            $whereClause = $whereClause + ['deleted' => false];
        }

        foreach ($whereClause as $field => $value) {

            if (is_int($field)) {
                print_r('prepareWhere: Stop here!');
                die();
//                if (is_string($value)) {
//                    if (strpos($value, 'MATCH_') === 0) {
//                        $rightPart = $this->convertMatchExpression($entity, $value);
//                        $whereParts[] = $rightPart;
//                        continue;
//                    }
//                }
//                $field = 'AND';
            }

            if ($field === 'NOT') {
                print_r('prepareWhere: Stop here!!');
                die();
//                if ($level > 1) break;
//
//                $field = 'id!=s';
//                $value = array(
//                    'selectParams' => array(
//                        'select' => ['id'],
//                        'whereClause' => $value
//                    )
//                );
//                if (!empty($params['joins'])) {
//                    $value['selectParams']['joins'] = $params['joins'];
//                }
//                if (!empty($params['leftJoins'])) {
//                    $value['selectParams']['leftJoins'] = $params['leftJoins'];
//                }
//                if (!empty($params['customJoin'])) {
//                    $value['selectParams']['customJoin'] = $params['customJoin'];
//                }
            }

            if (!in_array($field, self::$sqlOperators)) {
                $isComplex = false;

                $operator = '=';
                $operatorOrm = '=';

                $leftPart = null;

                $isNotValue = false;
                if (substr($field, -1) === ':') {
                    $field = substr($field, 0, strlen($field) - 1);
                    $isNotValue = true;
                }

                if (!preg_match('/^[a-z0-9]+$/i', $field)) {
                    foreach (self::$comparisonOperators as $op => $opDb) {
                        if (strpos($field, $op) !== false) {
                            $field = trim(str_replace($op, '', $field));
                            $operatorOrm = $op;
                            $operator = $opDb;
                            break;
                        }
                    }
                }

                if (strpos($field, '.') !== false || strpos($field, ':') !== false) {
                    print_r('prepareWhere: Stop here!!22');
                    die();
//                    $leftPart = $this->convertComplexExpression($entity, $field);
//                    $isComplex = true;
                }

                if (empty($isComplex)) {
                    if (!isset($entity->fields[$field])) {
                        $whereParts[] = '0';
                        continue;
                    }

                    $fieldDefs = $entity->fields[$field];

                    $operatorModified = $operator;

                    $attributeType = null;
                    if (!empty($fieldDefs['type'])) {
                        $attributeType = $fieldDefs['type'];
                    }

                    if (
                        is_bool($value)
                        && in_array($operator, ['=', '<>'])
                        && $attributeType == IEntity::BOOL
                    ) {
                        if ($value) {
                            if ($operator === '=') {
                                $operatorModified = '= TRUE';
                            } else {
                                $operatorModified = '= FALSE';
                            }
                        } else {
                            if ($operator === '=') {
                                $operatorModified = '= FALSE';
                            } else {
                                $operatorModified = '= TRUE';
                            }
                        }
                    } else {
                        if (is_array($value)) {
                            if ($operator == '=') {
                                $operatorModified = 'IN';
                            } else {
                                if ($operator == '<>') {
                                    $operatorModified = 'NOT IN';
                                }
                            }
                        } else {
                            if (is_null($value)) {
                                if ($operator == '=') {
                                    $operatorModified = 'IS NULL';
                                } else {
                                    if ($operator == '<>') {
                                        $operatorModified = 'IS NOT NULL';
                                    }
                                }
                            }
                        }
                    }

                    if (!empty($fieldDefs['where']) && !empty($fieldDefs['where'][$operatorModified])) {
                        print_r('prepareWhere: Stop here!!2233');
                        die();
//                        $whereSqlPart = '';
//                        if (is_string($fieldDefs['where'][$operatorModified])) {
//                            $whereSqlPart = $fieldDefs['where'][$operatorModified];
//                        } else {
//                            if (!empty($fieldDefs['where'][$operatorModified]['sql'])) {
//                                $whereSqlPart = $fieldDefs['where'][$operatorModified]['sql'];
//                            }
//                        }
//                        if (!empty($fieldDefs['where'][$operatorModified]['leftJoins'])) {
//                            foreach ($fieldDefs['where'][$operatorModified]['leftJoins'] as $j) {
//                                $jAlias = $this->obtainJoinAlias($j);
//                                foreach ($params['leftJoins'] as $jE) {
//                                    $jEAlias = $this->obtainJoinAlias($jE);
//                                    if ($jEAlias === $jAlias) {
//                                        continue 2;
//                                    }
//                                }
//                                $params['leftJoins'][] = $j;
//                            }
//                        }
//                        if (!empty($fieldDefs['where'][$operatorModified]['joins'])) {
//                            foreach ($fieldDefs['where'][$operatorModified]['joins'] as $j) {
//                                $jAlias = $this->obtainJoinAlias($j);
//                                foreach ($params['joins'] as $jE) {
//                                    $jEAlias = $this->obtainJoinAlias($jE);
//                                    if ($jEAlias === $jAlias) {
//                                        continue 2;
//                                    }
//                                }
//                                $params['joins'][] = $j;
//                            }
//                        }
//                        if (!empty($fieldDefs['where'][$operatorModified]['customJoin'])) {
//                            $params['customJoin'] .= ' ' . $fieldDefs['where'][$operatorModified]['customJoin'];
//                        }
//                        if (!empty($fieldDefs['where'][$operatorModified]['distinct'])) {
//                            $params['distinct'] = true;
//                        }
//                        $whereParts[] = str_replace('{value}', $this->stringifyValue($value), $whereSqlPart);
                    } else {
                        if ($fieldDefs['type'] == IEntity::FOREIGN) {
                            print_r('prepareWhere: Stop here!!223344');
                            die();
//                            $leftPart = '';
//                            if (isset($fieldDefs['relation'])) {
//                                $relationName = $fieldDefs['relation'];
//                                if (isset($entity->relations[$relationName])) {
//
//                                    $alias = $this->getRelationAlias($entity, $relationName);
//                                    if ($alias) {
//                                        if (!is_array($fieldDefs['foreign'])) {
//                                            $leftPart = $alias . '.' . $this->toDb($fieldDefs['foreign']);
//                                        } else {
//                                            $leftPart = $this->getFieldPath($entity, $field);
//                                        }
//                                    }
//                                }
//                            }
                        } else {
                            $leftPart = self::TABLE_ALIAS . '.' . $this->toDb(self::sanitize($field));
                        }
                    }
                }

                if (!empty($leftPart)) {
                    if ($operatorOrm === '=s' || $operatorOrm === '!=s') {
                        if (!is_array($value)) {
                            continue;
                        }
                        if (!empty($value['entityType'])) {
                            $subQueryEntityType = $value['entityType'];
                        } else {
                            $subQueryEntityType = $entity->getEntityType();
                        }
                        $subQuerySelectParams = array();
                        if (!empty($value['selectParams'])) {
                            $subQuerySelectParams = $value['selectParams'];
                        }
                        $withDeleted = false;
                        if (!empty($value['withDeleted'])) {
                            $withDeleted = true;
                        }

                        print_r('prepareWhere: Stop here!!223344555');
                        die();
//                        $whereParts[] = $leftPart . " " . $operator . " (" . $this->createSelectQuery($subQueryEntityType, $subQuerySelectParams, $withDeleted) . ")";
                    } else {
                        if (!is_array($value)) {
                            if (!is_null($value)) {
                                if ($isNotValue) {
                                    print_r('prepareWhere: Stop here!!222222223344555');
                                    die();
//                                    $whereParts[] = $leftPart . " " . $operator . " " . $this->convertComplexExpression($entity, $value);
                                } else {
                                    $qb->andWhere("$leftPart $operator :{$field}");
                                    $qb->setParameter($field, $value, self::getParameterType($value));
                                }
                            } else {
                                if ($operator == '=') {
                                    $whereParts[] = $leftPart . " IS NULL";
                                } else {
                                    if ($operator == '<>') {
                                        $whereParts[] = $leftPart . " IS NOT NULL";
                                    }
                                }
                            }
                        } else {
                            print_r('prepareWhere: Stop here!!dsd222222223344555');
                            die();
//                            $valArr = $value;
//                            foreach ($valArr as $k => $v) {
//                                $valArr[$k] = $this->pdo->quote($valArr[$k]);
//                            }
//                            $oppose = '';
//                            $emptyValue = '0';
//                            if ($operator == '<>') {
//                                $oppose = 'NOT ';
//                                $emptyValue = '1';
//                            }
//                            if (!empty($valArr)) {
//                                $whereParts[] = $leftPart . " {$oppose}IN " . "(" . implode(',', $valArr) . ")";
//                            } else {
//                                $whereParts[] = "" . $emptyValue;
//                            }
                        }
                    }
                }
            } else {
                echo '<pre>';
                print_r('prepareWhere: Stop here!!!!');
                die();
//                $internalPart = $this->getWhere($entity, $value, $field, $params, $level + 1);
//                if ($internalPart || $internalPart === '0') {
//                    $whereParts[] = "(" . $internalPart . ")";
//                }
            }
        }
    }

    protected function prepareOrder(IEntity $entity, QueryBuilder $qb, array &$params): void
    {
        if (empty($params['orderBy'])) {
            return;
        }

        $orderBy = $params['orderBy'] ?? 'id';
        $order = $params['order'] ?? 'ASC';

        if (is_array($orderBy)) {
            foreach ($orderBy as $item) {
                if (is_array($item)) {
                    $orderByInternal = $item[0];
                    $orderInternal = null;
                    if (!empty($item[1])) {
                        $orderInternal = $item[1];
                    }

                    print_r('prepareOrder: stop here 111');
                    die();

//                    $arr[] = $this->getOrderPart($entity, $orderByInternal, $orderInternal);
                }
            }
        }

        if (strpos($orderBy, 'LIST:') === 0) {
            print_r('prepareOrder: stop here 222');
            die();

//            list($l, $field, $list) = explode(':', $orderBy);
//            $fieldPath = $this->getFieldPathForOrderBy($entity, $field);
//            $listQuoted = [];
//            $list = array_reverse(explode(',', $list));
//            foreach ($list as $i => $listItem) {
//                $listItem = str_replace('_COMMA_', ',', $listItem);
//                $listQuoted[] = $this->quote($listItem);
//            }
//            $part = "FIELD(" . $fieldPath . ", " . implode(", ", $listQuoted) . ") DESC";
//            return $part;
        }

        $orderBy = (string)$orderBy;
        $order = $this->prepareOrderParameter($order);

        if (!empty($entity->fields[$orderBy]['isLinkEntity'])) {
            $orderBy = $orderBy . 'Id';
        }

        if (!empty($entity->fields[$orderBy])) {
            $fieldDefs = $entity->fields[$orderBy];
        }

        if (!empty($fieldDefs) && !empty($fieldDefs['orderBy'])) {
            echo '<pre>';
            print_r('$orderPart');
            die();
//            $orderPart = str_replace('{direction}', $order, $fieldDefs['orderBy']);
//            return "{$orderPart}";
        } else {
            $qb->addOrderBy($this->getFieldPathForOrderBy($entity, $orderBy), $order);
        }


//        $qb->addOrderBy('column1', 'ASC')
//            ->addOrderBy('column2', 'DESC');

//        $orderPart = $this->getOrder($entity, $params['orderBy'], $params['order']);

//        $orderPart = $this->getOrderPart($entity, $orderBy, $order);
//        if ($orderPart) {
//            return "ORDER BY " . $orderPart;
//        }
    }

    protected function getFieldPathForOrderBy(IEntity $entity, string $orderBy): string
    {
        if (strpos($orderBy, '.') !== false) {
            list($alias, $field) = explode('.', $orderBy);
            $fieldPath = self::sanitize($alias) . '.' . $this->toDb(self::sanitize($field));
        } else {
            $fieldPath = $this->getFieldPath($entity, $orderBy);
        }
        return $fieldPath;
    }

    protected function prepareOrderParameter($order): string
    {
        if (!is_null($order)) {
            if (is_bool($order)) {
                $order = $order ? 'DESC' : 'ASC';
            }
            $order = strtoupper($order);
            if (!in_array($order, ['ASC', 'DESC'])) {
                $order = 'ASC';
            }
        } else {
            $order = 'ASC';
        }

        return $order;
    }

    public function aggregate(IEntity $entity, $params, $aggregation, $aggregationBy, $deleted = false)
    {
        echo '<pre>';
        print_r('aggregate');
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
        echo '<pre>';
        print_r('max');
        die();
    }

    public function min(IEntity $entity, $params, $field, $deleted = false)
    {
        echo '<pre>';
        print_r('min');
        die();
    }

    public function sum(IEntity $entity, $params)
    {
        echo '<pre>';
        print_r('sum');
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

        $keySet = $this->getRelationKeySet($entity, $relName);

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
                $params['callbacks'][] = [new JoinManyToMany($entity, $relName, $keySet), 'run'];

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
        echo '<pre>';
        print_r('countRelated');
        die();
    }

    public function addRelation(IEntity $entity, $relName, $id)
    {
        echo '<pre>';
        print_r('addRelation');
        die();
    }

    public function removeRelation(IEntity $entity, $relName, $id)
    {
        echo '<pre>';
        print_r('removeRelation');
        die();
    }

    public function removeAllRelations(IEntity $entity, $relName)
    {
        echo '<pre>';
        print_r('removeAllRelations');
        die();
    }

    public function insert(IEntity $entity)
    {
        echo '<pre>';
        print_r('insert');
        die();
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

            $setArr[$attribute] = $value;
        }

        if (count($setArr) == 0) {
            return $entity->id;
        }

        $qb = $this->connection->createQueryBuilder();

        $qb->update($this->connection->quoteIdentifier($this->toDb($entity->getEntityType())));
        foreach ($setArr as $field => $value) {
            $qb->set($this->toDb($field), ":$field");
            $qb->setParameter($field, $value, self::getParameterType($value));
        }

        $qb->where('id = :id');
        $qb->setParameter('id', self::getParameterType($entity->id));
        $qb->andWhere('deleted = :deleted');
        $qb->setParameter('deleted', self::getParameterType(false));

        try {
            $sql = $qb->getSQL();
            $qb->executeQuery();
        } catch (\Throwable $e) {
            $GLOBALS['log']->error("RDB Mapper failed: {$e->getMessage()}" . PHP_EOL . "SQL: $sql");
        }

        return $entity->id;
    }

    public function delete(IEntity $entity)
    {
        echo '<pre>';
        print_r('delete');
        die();
    }

    public function setCollectionClass($collectionClass)
    {
        $this->collectionClass = $collectionClass;
    }

    public static function sanitize(string $string): string
    {
        return preg_replace('/[^A-Za-z0-9_]+/', '', $string);
    }

    public static function sanitizeAlias(string $string): string
    {
        return preg_replace('/[^A-Za-z0-9_:.]+/', '', $string);
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

    public function toDb(string $field): string
    {
        if (array_key_exists($field, $this->fieldsMapCache)) {
            return $this->fieldsMapCache[$field];
        }

        $field = lcfirst($field);
        $dbField = preg_replace_callback('/([A-Z])/', array($this, 'toDbConversion'), $field);
        $this->fieldsMapCache[$field] = $dbField;

        return $dbField;
    }

    protected function toDbConversion(array $matches): string
    {
        return "_" . strtolower($matches[1]);
    }

    protected function getFieldPath(IEntity $entity, string $field): ?string
    {
        if (!isset($entity->fields[$field])) {
            return null;
        }

        $f = $entity->fields[$field];

        if (isset($f['source'])) {
            if ($f['source'] != 'db') {
                return null;
            }
        }

        if (!empty($f['notStorable'])) {
            return null;
        }

        $fieldPath = '';

        switch ($f['type']) {
            case 'foreign':
                if (isset($f['relation'])) {
                    $relationName = $f['relation'];
                    $foreign = $f['foreign'];
                    if (is_array($foreign)) {
                        print_r('getFieldPath: Stop here!!!!');
                        die();
//                        foreach ($foreign as $i => $value) {
//                            if ($value == ' ') {
//                                $foreign[$i] = '\' \'';
//                            } else {
//                                $foreign[$i] = $this->getRelationAlias($entity, $relationName) . '.' . $this->toDb($value);
//                            }
//                        }
//                        $fieldPath = 'TRIM(CONCAT(' . implode(', ', $foreign) . '))';
                    } else {
                        $fieldPath = $this->getRelationAlias($entity, $relationName) . '.' . $this->toDb($foreign);
                    }
                }
                break;
            default:
                $fieldPath = self::TABLE_ALIAS . '.' . $this->toDb($this->sanitize($field));
        }

        return $fieldPath;
    }

    public function getRelationAlias(IEntity $entity, $relationName): ?string
    {
        if (!isset($this->relationAliases[$entity->getEntityType()])) {
            $this->relationAliases[$entity->getEntityType()] = [];
            $occurrenceHash = [];
            foreach ($entity->relations as $name => $r) {
                if ($r['type'] == IEntity::BELONGS_TO) {
                    if (!array_key_exists($name, $this->relationAliases[$entity->getEntityType()])) {
                        if (array_key_exists($name, $occurrenceHash)) {
                            $occurrenceHash[$name]++;
                        } else {
                            $occurrenceHash[$name] = 0;
                        }
                        $suffix = '_a';
                        if ($occurrenceHash[$name] > 0) {
                            $suffix .= '_' . $occurrenceHash[$name];
                        }
                        $this->relationAliases[$entity->getEntityType()][$name] = $name . $suffix;
                    }
                }
            }
        }

        if (!isset($this->relationAliases[$entity->getEntityType()][$relationName])) {
            $this->relationAliases[$entity->getEntityType()][$relationName] = $this->toDb(self::sanitize($relationName)) . '_aa';
        }

        return $this->relationAliases[$entity->getEntityType()][$relationName];
    }

    protected function getRelationKeySet(IEntity $entity, string $relationName): array
    {
        $relOpt = $entity->relations[$relationName];
        $relType = $relOpt['type'];

        switch ($relType) {
            case IEntity::BELONGS_TO:
                $key = $this->toDb($entity->getEntityType()) . 'Id';
                if (isset($relOpt['key'])) {
                    $key = $relOpt['key'];
                }
                $foreignKey = 'id';
                if (isset($relOpt['foreignKey'])) {
                    $foreignKey = $relOpt['foreignKey'];
                }
                return [
                    'key'        => $key,
                    'foreignKey' => $foreignKey,
                ];
            case IEntity::HAS_MANY:
            case IEntity::HAS_ONE:
                $key = 'id';
                if (isset($relOpt['key'])) {
                    $key = $relOpt['key'];
                }
                $foreignKey = $this->toDb($entity->getEntityType()) . 'Id';
                if (isset($relOpt['foreignKey'])) {
                    $foreignKey = $relOpt['foreignKey'];
                }
                return [
                    'key'        => $key,
                    'foreignKey' => $foreignKey,
                ];
            case IEntity::HAS_CHILDREN:
                $key = 'id';
                if (isset($relOpt['key'])) {
                    $key = $relOpt['key'];
                }
                $foreignKey = 'parentId';
                if (isset($relOpt['foreignKey'])) {
                    $foreignKey = $relOpt['foreignKey'];
                }
                $foreignType = 'parentType';
                if (isset($relOpt['foreignType'])) {
                    $foreignType = $relOpt['foreignType'];
                }
                return [
                    'key'         => $key,
                    'foreignKey'  => $foreignKey,
                    'foreignType' => $foreignType,
                ];
            case IEntity::MANY_MANY:
                $key = 'id';
                if (isset($relOpt['key'])) {
                    $key = $relOpt['key'];
                }
                $foreignKey = 'id';
                if (isset($relOpt['foreignKey'])) {
                    $foreignKey = $relOpt['foreignKey'];
                }
                $nearKey = $this->toDb($entity->getEntityType()) . 'Id';
                $distantKey = $this->toDb($relOpt['entity']) . 'Id';
                if (isset($relOpt['midKeys']) && is_array($relOpt['midKeys'])) {
                    $nearKey = $relOpt['midKeys'][0];
                    $distantKey = $relOpt['midKeys'][1];
                }
                return [
                    'key'        => $key,
                    'foreignKey' => $foreignKey,
                    'nearKey'    => $nearKey,
                    'distantKey' => $distantKey
                ];
            case IEntity::BELONGS_TO_PARENT:
                $key = $relationName . 'Id';
                $typeKey = $relationName . 'Type';
                return [
                    'key'        => $key,
                    'typeKey'    => $typeKey,
                    'foreignKey' => 'id'
                ];
        }

        return [];
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
}
