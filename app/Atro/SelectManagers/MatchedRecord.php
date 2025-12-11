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

namespace Atro\SelectManagers;

use Atro\Core\Utils\Util;
use Atro\Core\SelectManagers\Base;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Query\QueryBuilder;

class MatchedRecord extends Base
{
    protected function access(&$result)
    {
        parent::access($result);

        if ($this->getUser()->isAdmin()) {
            return;
        }

        $a = $this->getRepository()->getMapper()->getQueryConverter()->getMainTableAlias();

        $whereGroups = [];
        foreach ($this->getEntityManager()->getRepository('Matching')->find() as $matching) {
            $where = [];
            foreach (['entity', 'masterEntity'] as $field) {
                $column = $field === 'entity' ? 'source_entity' : Util::toUnderScore($field);
                $repository = $this->getEntityManager()->getRepository($matching->get($field));
                $sp = $this->createSelectManager($matching->get($field))->getSelectParams([], true, true);
                $sp['select'] = ['id'];

                $subQb = $repository->getMapper()->createSelectQueryBuilder($repository->get(), $sp, true);
                $subQb->setParameter("{$column}_{$matching->id}", $matching->get($field));
                $innerSql = str_replace($a, "t_".$matching->id, $subQb->getSql());

                $where[] = [
                    'innerSql' => [
                        "sql"        => "$a.$column = :{$column}_{$matching->id} AND $a.{$column}_id IN ({$innerSql})",
                        "parameters" => $subQb->getParameters(),
                    ],
                ];
            }

            $whereGroups[] = ['AND' => $where];
        }

        $result['whereClause'][] = ['OR' => $whereGroups];
    }

    public function putInnerQueryForAclCheck(QueryBuilder $qb): void
    {
        if ($this->getUser()->isAdmin()) {
            return;
        }

        $alias = $this->getRepository()->getMapper()->getQueryConverter()->getMainTableAlias();

        $sp = $this->getSelectParams([], true, true);
        $sp['select'] = ['id'];

        $subQb = $this
            ->getRepository()
            ->getMapper()
            ->createSelectQueryBuilder($this->getRepository()->get(), $sp, true);

        $qb->andWhere("mr.id IN (".str_replace($alias, Util::generateId(), $subQb->getSql()).")");
        foreach ($subQb->getParameters() as $parameterName => $value) {
            $qb->setParameter($parameterName, $value, Mapper::getParameterType($value));
        }
    }
}
