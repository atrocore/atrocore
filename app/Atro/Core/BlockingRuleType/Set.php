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

namespace Atro\Core\BlockingRuleType;

use Atro\Core\Utils\IdGenerator;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\Entity;

class Set extends AbstractBlockingRule
{
    public static function getSupportedFieldTypes(): array
    {
        return [];
    }

    public function prepareSqlPart(QueryBuilder $qb, Entity $stageEntity): string
    {
        $alias = $qb->getQueryPart('from')[0]['alias'];

        $table = Util::toUnderScore(lcfirst($this->rule->getMatching()->get('masterEntity')));

        $subQb = $this->getConnection()->createQueryBuilder();
        $subAlias = IdGenerator::unsortableId();
        $subQb
            ->select("{$subAlias}.id")
            ->from($this->getConnection()->quoteIdentifier($table), $subAlias)
            ->where("$subAlias.deleted = :false")
            ->setParameter('false', false, ParameterType::BOOLEAN);

        $operator = $this->settings->get('operator') === 'and' ? ' AND ' : ' OR ';

        $rulesParts = [];
        foreach ($this->rule->getNestedRules() as $rule) {
            $sqlPart = $rule->prepareSqlPart($subQb, $stageEntity);
            if (!empty($sqlPart)) {
                $rulesParts[] = $sqlPart;
            }
        }

        if (!empty($rulesParts)) {
            $subQb->andWhere(implode($operator, $rulesParts));
        }

        foreach ($subQb->getParameters() as $name => $value) {
            $qb->setParameter($name, $value, Mapper::getParameterType($value));
        }

        return "{$alias}.id IN ({$subQb->getSQL()})";
    }
}
