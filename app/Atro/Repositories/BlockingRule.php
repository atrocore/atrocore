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

namespace Atro\Repositories;

use Atro\Core\BlockingRuleType\AbstractBlockingRule;
use Atro\Core\ExpressionLanguage\Compiled\CompiledMatchingRuleWhereExpression;
use Atro\Core\ExpressionLanguage\Compiled\MatchingRuleWhereContext;
use Atro\Entities\BlockingRule as BlockingRuleEntity;
use Espo\ORM\Entity as OrmEntity;

class BlockingRule extends AbstractRule
{
    public const string EXPRESSION_NAMESPACE = 'Compiled\\BlockingRule';

    public const array EXPRESSION_FIELDS = [
        'matchedWhereExpression' => [
            'prefix'     => self::WHERE_EXPRESSION_PREFIX,
            'names'      => ['stageEntity'],
            'interface'  => CompiledMatchingRuleWhereExpression::class,
            'context'    => MatchingRuleWhereContext::class,
            'cast'       => '(array) ',
            'returnType' => 'array',
        ],
    ];

    protected const string RULE_SET_FIELD = 'blockingRuleSetId';

    protected const string RULE_TYPES_METADATA_KEY = 'blockingRuleTypes';

    public function createBlockingType(BlockingRuleEntity $rule): AbstractBlockingRule
    {
        return $this->getInjection('matchingManager')->createBlockingType($rule);
    }

    protected function afterRemove(OrmEntity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $matching = $this->getMatching($entity);
        if (!empty($matching) && !empty($matching->get('isActive'))) {
            $this->getMatchingRepository()->unmarkAllMatchingSearched($matching);
        }
    }
}
