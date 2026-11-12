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

use Atro\Core\MatchingRuleType\AbstractRuleType;
use Atro\Entities\BlockingRule as BlockingRuleEntity;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\Entity;

abstract class AbstractBlockingRule extends AbstractRuleType
{
    protected BlockingRuleEntity $rule;

    abstract public function prepareSqlPart(QueryBuilder $qb, Entity $stageEntity): string;

    public function setRule(BlockingRuleEntity $rule): void
    {
        $this->rule = $rule;
        $this->settings = $rule->getSubtype() ?? $rule;
    }
}
