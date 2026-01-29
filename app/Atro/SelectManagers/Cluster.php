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
}