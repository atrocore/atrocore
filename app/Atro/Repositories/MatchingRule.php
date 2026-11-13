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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\ExpressionLanguage\Compiled\CompiledMatchingRuleScoreExpression;
use Atro\Core\ExpressionLanguage\Compiled\MatchingRuleScoreContext;
use Atro\Core\MatchingRuleType\AbstractMatchingRule;
use Atro\Entities\MatchingRule as MatchingRuleEntity;
use Espo\ORM\Entity as OrmEntity;

class MatchingRule extends AbstractRule
{
    public const string EXPRESSION_NAMESPACE = 'Compiled\\MatchingRule';

    public const array EXPRESSION_FIELDS = [
        'matchScoreExpression' => [
            'prefix'     => self::SCORE_EXPRESSION_PREFIX,
            'names'      => ['stageEntity', 'masterEntity'],
            'interface'  => CompiledMatchingRuleScoreExpression::class,
            'context'    => MatchingRuleScoreContext::class,
            'cast'       => '(float) ',
            'returnType' => 'float',
        ],
    ];

    protected const string RULE_SET_FIELD = 'matchingRuleSetId';

    protected const string RULE_TYPES_METADATA_KEY = 'matchingRuleTypes';

    public function createMatchingType(MatchingRuleEntity $rule): AbstractMatchingRule
    {
        return $this->getInjection('matchingManager')->createMatchingType($rule);
    }

    protected function beforeSave(OrmEntity $entity, array $options = [])
    {
        if ($entity->get('type') === 'attributesSimilar') {
            $matching = $this->getMatching($entity);
            foreach (empty($matching) ? [] : [$matching->get('entity'), $matching->get('masterEntity')] as $entityName) {
                if (empty($this->getInjection('metadata')->get("scopes.{$entityName}.hasAttribute"))) {
                    throw new BadRequest($this->getInjection('language')->translate('notValidAttributesSimilar', 'exceptions', $this->entityType));
                }
            }
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(OrmEntity $entity, array $options = []): void
    {
        parent::afterSave($entity, $options);

        $this->recalculateWeightForSets();
    }

    protected function afterRemove(OrmEntity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->recalculateWeightForSets();
    }

    protected function recalculateWeightForSets(): void
    {
        foreach ($this->find() as $rule) {
            if ($rule->get('type') === 'set') {
                $ruleWeight = (int)$this->createMatchingType($rule)->getWeight();
                if ($rule->get('weight') !== $ruleWeight) {
                    $rule->set('weight', $ruleWeight);
                    $this->getEntityManager()->saveEntity($rule);
                }
            }
        }
    }
}
