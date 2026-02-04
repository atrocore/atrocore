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
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\IEntity;

class SelectionItem extends Base
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

        $result['callbacks'][] = [$this, 'filterDeletedRecords'];
    }

    public function onlyAllowedRecords(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();

        $entities = $this->getEntities();

        if (empty($entities)) {
            return;
        }

        $andWhereParts = [];

        foreach ($entities as $k => $entityName) {
            $sp = $this->createSelectManager($entityName)->getSelectParams([], true, true);
            $sp['select'] = ['id'];

            $qb1 = $mapper->createSelectQueryBuilder($this->getEntityManager()->getRepository($entityName)->get(), $sp);
            $qb1->select("{$tableAlias}.id");

            $andWhereParts[] = "({$tableAlias}.entity_name=:entityName{$k} AND {$tableAlias}.entity_id IN (" . str_replace($tableAlias, $tableAlias . $k, $qb1->getSql()) . "))";

            $qb->setParameter("entityName{$k}", $entityName);
            foreach ($qb1->getParameters() as $param => $val) {
                $qb->setParameter($param, $val, Mapper::getParameterType($val));
            }
        }

        if (!empty($andWhereParts)) {
            $qb->andWhere(implode(' OR ', $andWhereParts));
        }
    }

    public function filterDeletedRecords(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $tableAlias = $mapper->getQueryConverter()->getMainTableAlias();

        $entities = $this->getEntities();

        if (empty($entities)) {
            return;
        }

        $andWhereParts = [];

        foreach ($entities as $k => $entityName) {
            $tableName = $this->getEntityManager()->getConnection()->quoteIdentifier(Util::toUnderScore($entityName));

            $andWhereParts[] = "({$tableAlias}.entity_name=:entityName{$k} AND EXISTS (SELECT 1 FROM $tableName WHERE id = {$tableAlias}.entity_id AND deleted = :false))";

            $qb->setParameter("entityName{$k}", $entityName);
            $qb->setParameter("false", false, ParameterType::BOOLEAN);
        }

        if (!empty($andWhereParts)) {
            $qb->andWhere(implode(' OR ', $andWhereParts));
        }
    }

    public function getEntities(): array
    {
        $entities = $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->select('entity_name')
            ->distinct()
            ->from(Util::toUnderScore($this->getEntityType()))
            ->where('deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchFirstColumn();

        $filtered = [];

        foreach ($entities as $entity) {
            $type = $this->getMetadata()->get(['scopes', $entity, 'type']);
            if (in_array($type, ['Base', 'Hierarchy'])) {
                $filtered[] = $entity;
            }
        }

        return $filtered;
    }
}