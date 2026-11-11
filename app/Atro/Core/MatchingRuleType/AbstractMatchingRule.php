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

use Atro\Entities\MatchingRule as MatchingRuleEntity;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\Entity;

abstract class AbstractMatchingRule extends AbstractRuleType
{
    protected MatchingRuleEntity $rule;

    abstract public function match(Entity $stageEntity, array $masterEntityData): float;

    public function prepareMatchSelect(QueryBuilder $qb, Entity $stageEntity): void
    {
    }

    public function setRule(MatchingRuleEntity $rule): void
    {
        $this->rule = $rule;
        $this->settings = $rule->getSubtype() ?? $rule;
    }

    public function getWeight(): float
    {
        return (float)($this->rule->get('weight') ?? 0);
    }
}
