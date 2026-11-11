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
use Atro\Core\ExpressionLanguage\Compiled\MatchingRuleScoreContext;
use Atro\Repositories\MatchingRule as MatchingRuleRepository;
use Espo\ORM\Entity;

class Expression extends AbstractMatchingRule
{
    public static function getSupportedFieldTypes(): array
    {
        return [];
    }

    public function isSymmetric(): bool
    {
        return false;
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
