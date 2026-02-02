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

use Atro\Core\Utils\IdGenerator;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\Entity;

class Set extends AbstractMatchingRule
{
    public static function getSupportedFieldTypes(): array
    {
        return [];
    }

    public function getWeight(): int
    {
        $weight = 0;

        if ($this->rule->get('operator') === 'or') {
            foreach ($this->rule->get('matchingRules') ?? [] as $rule) {
                $ruleWeight = $rule->getWeight();
                if ($ruleWeight > $weight) {
                    $weight = $ruleWeight;
                }
            }
        } elseif ($this->rule->get('operator') === 'and') {
            foreach ($this->rule->get('matchingRules') ?? [] as $rule) {
                $weight += $rule->getWeight();
            }
        }

        return $weight;
    }

    public function prepareMatchingSqlPart(QueryBuilder $qb, Entity $stageEntity): string
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

        $rulesParts = [];
        foreach ($this->rule->get('matchingRules') ?? [] as $rule) {
            $sqlPart = $rule->prepareMatchingSqlPart($subQb, $stageEntity);
            if (!empty($sqlPart)) {
                $rulesParts[] = $sqlPart;
            }
        }

        if (!empty($rulesParts)) {
            $subQb->andWhere(implode(' OR ', $rulesParts));
        }

        foreach ($subQb->getParameters() as $name => $value) {
            $qb->setParameter($name, $value, Mapper::getParameterType($value));
        }

        return "{$alias}.id IN ({$subQb->getSQL()})";
    }

    public function match(Entity $stageEntity, array $masterEntityData): int
    {
        $weight = 0;
        if ($this->rule->get('operator') === 'or') {
            // convert EntityCollection to array
            $rules = [];
            foreach ($this->rule->get('matchingRules') ?? [] as $rule) {
                $rules[] = $rule;
            }

            // Sort rules by 'weight' in descending order
            usort($rules, function ($a, $b) {
                return $b->get('weight') <=> $a->get('weight');
            });

            foreach ($rules as $rule) {
                $weight = $rule->match($stageEntity, $masterEntityData);
                if ($weight > 0) {
                    return $weight;
                }
            }
        } elseif ($this->rule->get('operator') === 'and') {
            foreach ($this->rule->get('matchingRules') ?? [] as $rule) {
                $weight += $rule->match($stageEntity, $masterEntityData);
            }
        }

        return $weight;
    }
}
