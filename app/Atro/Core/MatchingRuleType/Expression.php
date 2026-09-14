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

namespace Atro\Core\MatchingRuleType;

use Atro\Core\ExpressionLanguage\Compiled\CompiledMatchingRuleScoreExpression;
use Atro\Core\ExpressionLanguage\Compiled\CompiledMatchingRuleWhereExpression;
use Atro\Core\ExpressionLanguage\Compiled\MatchingRuleScoreContext;
use Atro\Core\ExpressionLanguage\Compiled\MatchingRuleWhereContext;
use Atro\ORM\DB\RDB\Mapper;
use Atro\ORM\DB\RDB\Query\QueryConverter;
use Atro\Repositories\MatchingRule as MatchingRuleRepository;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\Entity;

class Expression extends AbstractMatchingRule
{
    public static function getSupportedFieldTypes(): array
    {
        return [];
    }

    public function prepareMatchingSqlPart(QueryBuilder $qb, Entity $stageEntity): string
    {
        $className = MatchingRuleRepository::getCompiledWhereExpressionClassName($this->rule);
        if (!class_exists($className) || !is_a($className, CompiledMatchingRuleWhereExpression::class, true)) {
            return '1=0';
        }

        $context = new MatchingRuleWhereContext($stageEntity);

        try {
            $where = $this->getContainer()->get($className)->eval($context);
        } catch (\Throwable $e) {
            $GLOBALS['log']->error("MatchingRule '{$this->rule->get('id')}' matchedWhereExpression failed: " . $e->getMessage());
            return '1=0';
        }

        if (empty($where) || !is_array($where)) {
            return '1=0';
        }

        try {
            $mapper = $this->getEntityManager()->getRepository($stageEntity->getEntityName())->getMapper();
            $queryConverter = $mapper->getQueryConverter();
            $sql = $queryConverter->getWhere($stageEntity, $where);
        } catch (\Throwable $e) {
            $GLOBALS['log']->error("MatchingRule '{$this->rule->get('id')}' failed to build SQL from matchedWhereExpression: " . $e->getMessage());
            return '1=0';
        }

        if (empty($sql)) {
            return '1=0';
        }

        // getWhere() binds some conditions inline (quoted literals) and others as named parameters
        // accumulated on the (cached, reused) QueryConverter - only pull the ones this SQL actually
        // references, since stale entries from an unrelated earlier call could still be sitting there
        preg_match_all('/:([a-zA-Z0-9_]+)/', $sql, $matches);
        $allParameters = $queryConverter->getParameters();
        foreach (array_unique($matches[1]) as $paramName) {
            if (array_key_exists($paramName, $allParameters)) {
                $qb->setParameter($paramName, $allParameters[$paramName], Mapper::getParameterType($allParameters[$paramName]));
            }
        }

        $alias = $qb->getQueryPart('from')[0]['alias'];

        return str_replace(QueryConverter::TABLE_ALIAS . '.', $alias . '.', $sql);
    }

    public function match(Entity $stageEntity, array $masterEntityData): float
    {
        $className = MatchingRuleRepository::getCompiledScoreExpressionClassName($this->rule);
        if (!class_exists($className) || !is_a($className, CompiledMatchingRuleScoreExpression::class, true)) {
            return 0.0;
        }

        $context = new MatchingRuleScoreContext($stageEntity, $masterEntityData);

        try {
            $score = (float)$this->getContainer()->get($className)->eval($context);
        } catch (\Throwable $e) {
            $GLOBALS['log']->error("MatchingRule '{$this->rule->get('id')}' matchScoreExpression failed: " . $e->getMessage());
            return 0.0;
        }

        $score = max(0.0, min(1.0, $score));

        return $score * $this->getWeight();
    }
}
