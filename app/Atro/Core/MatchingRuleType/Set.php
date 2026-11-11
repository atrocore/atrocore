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

use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\Entity;

class Set extends AbstractMatchingRule
{
    public static function getSupportedFieldTypes(): array
    {
        return [];
    }

    public function getWeight(): float
    {
        $weight = 0;

        if ($this->settings->get('operator') === 'or') {
            foreach ($this->rule->getNestedRules() as $rule) {
                $ruleWeight = $rule->getWeight();
                if ($ruleWeight > $weight) {
                    $weight = $ruleWeight;
                }
            }
        } elseif ($this->settings->get('operator') === 'and') {
            foreach ($this->rule->getNestedRules() as $rule) {
                $weight += $rule->getWeight();
            }
        }

        return (float)$weight;
    }

    public function prepareMatchSelect(QueryBuilder $qb, Entity $stageEntity): void
    {
        foreach ($this->rule->getNestedRules() as $rule) {
            $rule->prepareMatchSelect($qb, $stageEntity);
        }
    }

    public function match(Entity $stageEntity, array $masterEntityData): float
    {
        $weight = 0.0;
        if ($this->settings->get('operator') === 'or') {
            // convert EntityCollection to array
            $rules = [];
            foreach ($this->rule->getNestedRules() as $rule) {
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
        } elseif ($this->settings->get('operator') === 'and') {
            foreach ($this->rule->getNestedRules() as $rule) {
                $weight += $rule->match($stageEntity, $masterEntityData);
            }
        }

        return $weight;
    }
}
