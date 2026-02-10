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

namespace Atro\SelectManagers;

use Atro\Core\SelectManagers\Base;
use Atro\Core\Utils\IdGenerator;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\IEntity;

class Cluster extends Base
{
    protected function access(&$result)
    {
        parent::access($result);

        if (!$this->getUser()->isAdmin()) {
            $result['callbacks'][] = [$this, 'onlyAllowedRecords'];
        }
    }

    public function applyAdditional(array &$result, array $params)
    {
        parent::applyAdditional($result, $params);

        if (!empty($this->selectParameters['select'])) {
            // For performance, we have to calculate count on the filtered result
            $callbackParam = 'subQueryCallbacks';
            if (in_array($this->selectParameters['sortBy'] ?? '', ['stagingItemCount', 'masterItemCount'])) {
                // We have to calculate count on the main query
                $callbackParam = 'callbacks';
            }
            if (in_array('stagingItemCount', $this->selectParameters['select'])) {
                $result[$callbackParam][] = [$this, 'stagingItemCountCallback'];
            }

            if (in_array('masterItemCount', $this->selectParameters['select'])) {
                $result[$callbackParam][] = [$this, 'masterItemCountCallback'];
            }

            if (in_array('state', $this->selectParameters['select'])) {
                $result['subQueryCallbacks'][] = [$this, 'stateCallback'];
            }
        }
    }

    public function stagingItemCountCallback(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $this->itemCountCallback($qb, $relEntity, $params, $mapper, 'stagingItemCount');;
    }

    public function masterItemCountCallback(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $this->itemCountCallback($qb, $relEntity, $params, $mapper, 'masterItemCount');;
    }


    public function itemCountCallback(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper, string $field): void
    {
        if (!empty($params['aggregation'])) {
            return;
        }

        $mtAlias = $mapper->getQueryConverter()->getMainTableAlias();

        $sp = $this->getSelectManagerFactory()->create('ClusterItem')->getSelectParams([], true, true);
        $sp['aggregation'] = 'COUNT';
        $sp['aggregationBy'] = 'id';
        $sp['skipBelongsToJoins'] = true;

        $column = $mapper->getQueryConverter()->fieldToAlias($field);
        $operator = $field === 'masterItemCount' ? '=' : '<>';
        $tableAlias = $field === 'masterItemCount' ? 'mic' : 'sic';

        $masterEntityColumn = 'atro_master_entity';
        if (in_array($this->selectParameters['sortBy'] ?? '', ['stagingItemCount', 'masterItemCount'])) {
            $qb->orderBy($mapper->getQueryConverter()->fieldToAlias($this->selectParameters['sortBy']), !empty($this->selectParameters['asc']) ? 'ASC' : 'DESC');
            $masterEntityColumn = 'master_entity';
        }

        $countQb = $mapper->createSelectQueryBuilder($this->getEntityManager()->getEntity('ClusterItem'), $sp);
        $countQb->andwhere("$mtAlias.cluster_id = mt_alias.id and $mtAlias.entity_name $operator mt_alias.$masterEntityColumn");

        $countSql = str_replace([$mtAlias, 'mt_alias'], [$tableAlias, $mtAlias], $countQb->getSQL());
        $qb->addSelect("({$countSql})  AS $column");

        foreach ($countQb->getParameters() as $pName => $pValue) {
            $qb->setParameter($pName, $pValue, $mapper::getParameterType($pValue));
        }
    }

    public function onlyAllowedRecords(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();

        $entities = $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->select('master_entity')
            ->distinct()
            ->from('cluster')
            ->where('deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchFirstColumn();

        if (empty($entities)) {
            return;
        }

        $forbiddenEntities = [];
        foreach ($entities as $entityName) {
            if (!$this->getAcl()->checkScope($entityName, 'read')) {
                $forbiddenEntities[] = $entityName;
            }
        }

        if (!empty($forbiddenEntities)) {
            $qb->andWhere("{$tableAlias}.master_entity NOT IN (:forbiddenEntities)")
                ->setParameter("forbiddenEntities", $forbiddenEntities, Mapper::getParameterType($forbiddenEntities));

            $entities = array_diff($entities, $forbiddenEntities);
        }

        $andWhereParts = ["{$tableAlias}.golden_record_id is null"];

        foreach ($entities as $k => $entityName) {
            $sp = $this->createSelectManager($entityName)->getSelectParams([], true, true);
            $sp['select'] = ['id'];

            $qb1 = $mapper->createSelectQueryBuilder($this->getEntityManager()->getRepository($entityName)->get(), $sp);
            $qb1->select("{$tableAlias}.id");

            $andWhereParts[] = "({$tableAlias}.master_entity=:entityName{$k} AND {$tableAlias}.golden_record_id IN (" . str_replace($tableAlias, $tableAlias . $k, $qb1->getSql()) . "))";

            $qb->setParameter("entityName{$k}", $entityName);
            foreach ($qb1->getParameters() as $param => $val) {
                $qb->setParameter($param, $val, Mapper::getParameterType($val));
            }
        }

        if (!empty($andWhereParts)) {
            $qb->andWhere(implode(' OR ', $andWhereParts));
        }
    }

    public function getWherePartForStagingItemCount(array $item, array $result): array
    {
        return $this->getItemCountPart($item, 'stagingItemCount');
    }

    public function getWherePartForMasterItemCount(array $item, array $result): array
    {
        return $this->getItemCountPart($item, 'masterItemCount');
    }

    public function getItemCountPart(array $item, string $field)
    {
        $sp = $this->getSelectManagerFactory()->create('ClusterItem')->getSelectParams([], true);
        $sp['aggregation'] = 'COUNT';
        $sp['aggregationBy'] = 'id';
        $sp['skipBelongsToJoins'] = true;

        $ciMapper = $this->getEntityManager()->getRepository('ClusterItem')->getMapper();
        $mtAlias = $ciMapper->getQueryConverter()->getMainTableAlias();

        $operator = $field === 'masterItemCount' ? '=' : '<>';

        $countQb = $ciMapper->createSelectQueryBuilder($this->getEntityManager()->getEntity('ClusterItem'), $sp, true);
        $countQb->andwhere("$mtAlias.cluster_id = mt_alias.id and $mtAlias.entity_name $operator mt_alias.master_entity");

        $countSql = str_replace([$mtAlias, 'mt_alias'], ['sbq_' . IdGenerator::unsortableId(), $mtAlias], $countQb->getSQL());

        if ($item['type'] == 'isNull') {
            $innerSql = "($countSql) = 0";
        } else if ($item['type'] == 'isNotNull') {
            $innerSql = "($countSql) > 0";
        } else if ($item['type'] == 'between') {
            $from = intval($item['value'][0]);
            $to = intval($item['value'][1]);
            $innerSql = "($countSql) >= $from AND ($countSql) <= $to";
        }

        if (empty($innerSql)) {
            switch ($item['type']) {
                case 'equals':
                    $sqlOperator = '=';
                    break;
                case "notEqual":
                    $sqlOperator = '<>';
                    break;
                case "greaterThan":
                    $sqlOperator = '>';
                    break;
                case "greaterThanOrEquals":
                    $sqlOperator = '>=';
                    break;
                case "lessThan":
                    $sqlOperator = '<';
                    break;
                case "lessThanOrEquals":
                    $sqlOperator = '<=';
                    break;
            }

            if (empty($sqlOperator)) {
                throw new \Exception("Invalid filter type '${item['type']}' for field '$field'");
            }
            $value = intval($item['value']);
            $innerSql = "($countSql) $sqlOperator $value";
        }

        return [
            'innerSql' => [
                'sql'        => $innerSql,
                'parameters' => $countQb->getParameters(),
            ]
        ];
    }

    public function stateCallback(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        if (!empty($params['aggregation'])) {
            return;
        }

        $column = $mapper->getQueryConverter()->fieldToAlias('state');
        $stateQueryData = $this->getStateQueryData($mapper, true);

        $qb->addSelect("({$stateQueryData['sql']})  AS $column");

        foreach ($stateQueryData['parameters'] as $pName => $pValue) {
            $qb->setParameter($pName, $pValue, $mapper::getParameterType($pValue));
        }
    }

    private function getStateQueryData(Mapper $mapper, bool $inSubQueryCallback = false): array
    {
        $mtAlias = $mapper->getQueryConverter()->getMainTableAlias();

        $entities = $this->getSelectManagerFactory()->create('ClusterItem')->getEntities();

        if (empty($entities)) {
            return [
                'sql'        => "'empty'",
                'parameters' => []
            ];
        }

        $sp = $this->getSelectManagerFactory()->create('ClusterItem')->getSelectParams([]);
        $sp['skipBelongsToJoins'] = true;

        $stateQb = $mapper->createSelectQueryBuilder($this->getEntityManager()->getEntity('ClusterItem'), $sp);
        $goldenRecordCaseParts = [];

        foreach ($entities as $k => $entityName) {
            $tableName = $this->getEntityManager()->getConnection()->quoteIdentifier(Util::toUnderScore($entityName));

            if (!empty($this->getMetadata()->get(['scopes', $entityName, 'primaryEntityId']))) {
                $goldenRecordCaseParts[] = sprintf(
                    "WHEN %s.entity_name = :entityName%d THEN " .
                    "(SELECT golden_record_id FROM %s WHERE id = %s.entity_id)",
                    $mtAlias,
                    $k,
                    $tableName,
                    $mtAlias
                );
            }

            $stateQb->setParameter("entityName{$k}", $entityName);
        }

        $goldenRecordCase = 'CASE ' . implode(' ', $goldenRecordCaseParts) . ' ELSE null END';

        $masterEntityColumn = 'master_entity';
        $goldenRecordColumn = 'golden_record_id';
        if ($inSubQueryCallback){
            $masterEntityColumn = 'atro_master_entity';
            $goldenRecordColumn = 'atro_golden_record_id';
        }

        $stateQb->select("CASE " .
            // Empty state
            "WHEN COUNT($mtAlias.id) = 0 THEN 'empty' " .

            // Invalid state
            "WHEN COUNT($mtAlias.id) > 0 AND (COUNT(CASE WHEN $mtAlias.entity_name <> mt_alias.$masterEntityColumn THEN 1 END) = 0 OR " .
            "COUNT(CASE WHEN $mtAlias.entity_name = mt_alias.$masterEntityColumn THEN 1 END) > 1) THEN 'invalid' " .

            // Merged state
            "WHEN mt_alias.$goldenRecordColumn IS NOT NULL AND " .
            "COUNT(CASE WHEN $mtAlias.entity_name = mt_alias.$masterEntityColumn THEN 1 END) = 1 AND " .
            "MAX(CASE WHEN $mtAlias.entity_name = mt_alias.$masterEntityColumn THEN $mtAlias.entity_id END) = mt_alias.$goldenRecordColumn AND " .
            "COUNT(CASE WHEN $mtAlias.entity_name <> mt_alias.$masterEntityColumn THEN 1 END) > 0 AND " .
            "COUNT(CASE WHEN $mtAlias.entity_name <> mt_alias.$masterEntityColumn AND ({$goldenRecordCase}) <> mt_alias.$goldenRecordColumn THEN 1 END) = 0 THEN 'merged' " .

            // Review state (default for everything else)
            "ELSE 'review' END")
            ->andwhere("$mtAlias.cluster_id = mt_alias.id")
            ->resetqueryparts(['orderBy', 'limit', 'offset']);

        return [
            'sql'        => str_replace([$mtAlias, 'mt_alias'], [ 'sbq_' . IdGenerator::unsortableId(), $mtAlias], $stateQb->getSQL()),
            'parameters' => $stateQb->getParameters(),
        ];
    }

    public function getWherePartForState(array $item, array $result): array
    {
        $stateQueryData = $this->getStateQueryData($this->getEntityManager()->getRepository('ClusterItem')->getMapper() );

        if ($item['type'] == 'isNull') {
            return [
                'id' => null
            ];
        } else if ($item['type'] == 'isNotNull') {
            return [];
        }

        $sql = $stateQueryData['sql'];
        $paramName = 'state_' . IdGenerator::unsortableId();
        $stateQueryData['parameters'][$paramName] = $item['value'];


        if ($item['type'] == 'in') {
            $innerSql = "({$sql}) IN (:$paramName)";
        } else if ($item['type'] == 'notIn') {
            $innerSql = "({$sql}) NOT IN (:$paramName)";
        } else {
            throw new \Exception("Invalid filter type '${item['type']}' for field 'state'");
        }

        return [
            'innerSql' => [
                'sql'        => $innerSql,
                'parameters' => $stateQueryData['parameters'],
            ]
        ];
    }

}