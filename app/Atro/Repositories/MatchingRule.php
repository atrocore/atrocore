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
use Atro\Core\ExpressionLanguage\Compiled\CompiledExpression;
use Atro\Core\ExpressionLanguage\Compiled\CompiledMatchingRuleScoreExpression;
use Atro\Core\ExpressionLanguage\Compiled\CompiledMatchingRuleWhereExpression;
use Atro\Core\MatchingRuleType\AbstractMatchingRule;
use Atro\Core\Templates\Repositories\Base;
use Atro\Entities\Matching as MatchingEntity;
use Atro\Entities\MatchingRule as MatchingRuleEntity;
use Espo\ORM\Entity as OrmEntity;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;

class MatchingRule extends Base
{
    public const string EXPRESSION_NAMESPACE = 'Compiled\\MatchingRule';

    public const array EXPRESSION_FIELDS = [
        'matchedWhereExpression' => [
            'prefix'     => 'Where',
            'names'      => ['stageEntity'],
            'interface'  => CompiledMatchingRuleWhereExpression::class,
            'context'    => \Atro\Core\ExpressionLanguage\Compiled\MatchingRuleWhereContext::class,
            'cast'       => '(array) ',
            'returnType' => 'array',
        ],
        'matchScoreExpression' => [
            'prefix'     => 'Score',
            'names'      => ['stageEntity', 'masterEntity'],
            'interface'  => CompiledMatchingRuleScoreExpression::class,
            'context'    => \Atro\Core\ExpressionLanguage\Compiled\MatchingRuleScoreContext::class,
            'cast'       => '(float) ',
            'returnType' => 'float',
        ],
    ];

    /**
     * @param MatchingRuleEntity $entity
     * @param array              $options
     *
     * @return void
     * @throws BadRequest
     */
    protected function beforeSave(OrmEntity $entity, array $options = [])
    {
        if (!empty($entity->get('matchingId')) && empty($this->getEntityManager()->getRepository('Matching')->get($entity->get('matchingId')))) {
            throw new BadRequest($this->getInjection('language')->translate('notValidMatching', 'exceptions', 'MatchingRule'));
        }

        if (!empty($entity->get('matchingRuleSetId'))) {
            $set = $this->getEntityManager()->getRepository('MatchingRule')->get($entity->get('matchingRuleSetId'));
            if (empty($set) || $set->get('type') !== 'set') {
                throw new BadRequest($this->getInjection('language')->translate('notValidMatchingRuleSet', 'exceptions', 'MatchingRule'));
            }
        }

        $this->validateIsMatchingActive($entity);
        $this->validateFieldType($entity);
        $this->validateExpressions($entity);

        parent::beforeSave($entity, $options);
    }

    public static function getCompiledWhereExpressionClassName(MatchingRuleEntity $rule): string
    {
        return self::getCompiledExpressionFullClassName($rule, 'matchedWhereExpression');
    }

    public static function getCompiledScoreExpressionClassName(MatchingRuleEntity $rule): string
    {
        return self::getCompiledExpressionFullClassName($rule, 'matchScoreExpression');
    }

    protected static function getCompiledExpressionFullClassName(MatchingRuleEntity $rule, string $field): string
    {
        $prefix = self::EXPRESSION_FIELDS[$field]['prefix'];

        return self::EXPRESSION_NAMESPACE . '\\' . $prefix . $rule->get('number');
    }

    /**
     * The class name is built from the rule's autoincrement number, which the database assigns
     * on insert - a just-inserted row has to be read back to learn it.
     */
    protected function getRuleNumber(MatchingRuleEntity $rule): ?string
    {
        if (!empty($rule->get('number'))) {
            return (string)$rule->get('number');
        }

        $conn = $this->getDbal();

        try {
            $number = $conn->createQueryBuilder()
                ->select('number')
                ->from($conn->quoteIdentifier('matching_rule'))
                ->where('id = :id')
                ->setParameter('id', $rule->get('id'))
                ->fetchOne();
        } catch (\Throwable $e) {
            return null;
        }

        if (empty($number)) {
            return null;
        }

        $rule->set('number', $number);

        return (string)$number;
    }

    protected function validateExpressions(MatchingRuleEntity $rule): void
    {
        if ($rule->get('type') !== 'expression') {
            return;
        }

        foreach (array_keys(self::EXPRESSION_FIELDS) as $field) {
            if (!$rule->isAttributeChanged($field)) {
                continue;
            }

            if (empty($rule->get($field))) {
                throw new BadRequest($this->getInjection('language')->translate('expressionCannotBeEmpty', 'exceptions', 'MatchingRule'));
            }

            try {
                $this->getExpressionLanguage()->lint($rule->get($field), self::EXPRESSION_FIELDS[$field]['names']);
            } catch (SyntaxError $e) {
                throw new BadRequest($e->getMessage());
            }
        }
    }

    protected function saveExpressions(MatchingRuleEntity $rule): void
    {
        if ($rule->get('type') !== 'expression') {
            return;
        }

        $changed = array_filter(array_keys(self::EXPRESSION_FIELDS), fn(string $field) => $rule->isAttributeChanged($field));
        if (empty($changed)) {
            return;
        }

        if (empty($this->getRuleNumber($rule))) {
            return;
        }

        foreach ($changed as $field) {
            $this->compileExpression($rule, $field);
        }
    }

    protected function compileExpression(MatchingRuleEntity $rule, string $field): void
    {
        $expression = (string)$rule->get($field);
        $config = self::EXPRESSION_FIELDS[$field];

        $code = $this->getExpressionLanguage()->compile($expression, $config['names']);
        $namespace = self::EXPRESSION_NAMESPACE;
        $className = $config['prefix'] . $rule->get('number');
        $interface = $config['interface'];
        $context = $config['context'];
        $cast = $config['cast'];
        $returnType = $config['returnType'];

        $literal = var_export($expression, true);

        $prelude = [];
        foreach ($config['names'] as $name) {
            if (preg_match('/\$' . preg_quote($name, '/') . '\b/', $code) === 1) {
                $prelude[] = sprintf('        $%s = $context->%s;', $name, $name);
            }
        }
        $prelude = implode("\n", $prelude);

        $php = <<<PHP
    <?php

    namespace {$namespace};

    /**
     * GENERATED — do not edit. Regenerated from expression() below.
     */
    final class {$className} implements \\{$interface}
    {
        public static function expression(): string
        {
            return {$literal};
        }

        public function eval(\\{$context} \$context): {$returnType}
        {
    {$prelude}

            return {$cast}({$code});
        }
    }

    PHP;

        $dir = 'data/custom-code/' . str_replace('\\', '/', $namespace);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $file = $dir . '/' . $className . '.php';
        $tmp = $file . '.' . getmypid() . '.tmp';

        file_put_contents($tmp, $php);
        rename($tmp, $file);
    }

    protected function deleteExpressions(MatchingRuleEntity $rule): void
    {
        foreach (array_keys(self::EXPRESSION_FIELDS) as $field) {
            $fileName = 'data/custom-code/' . str_replace('\\', '/', self::getCompiledExpressionFullClassName($rule, $field)) . '.php';
            if (file_exists($fileName)) {
                unlink($fileName);
            }
        }
    }

    /**
     * @param MatchingRuleEntity $entity
     *
     * @return void
     */
    protected function afterEntityPopulated(OrmEntity $entity): void
    {
        if ($entity->isNew() || $entity->get('type') !== 'expression') {
            return;
        }

        foreach (array_keys(self::EXPRESSION_FIELDS) as $field) {
            $className = self::getCompiledExpressionFullClassName($entity, $field);
            if (class_exists($className) && is_a($className, CompiledExpression::class, true)) {
                $expression = $className::expression();

                $entity->set($field, $expression);
                // the field is not storable, so it has to be marked as fetched to stay unchanged until really edited
                $entity->setFetched($field, $expression);
            }
        }
    }

    protected function getExpressionLanguage(): ExpressionLanguage
    {
        return $this->getInjection('expressionLanguage');
    }

    public function getMatching(MatchingRuleEntity $rule): ?MatchingEntity
    {
        while (true) {
            $matchingRule = null;
            if (!empty($rule->get('matchingRuleSetId'))) {
                $matchingRule = $this->getEntityManager()->getRepository('MatchingRule')->get($rule->get('matchingRuleSetId'));
            }
            if (!empty($matchingRule)) {
                $rule = $matchingRule;
            } else {
                break;
            }
        }

        return $this->getEntityManager()->getRepository('Matching')->get($rule->get('matchingId'));
    }

    public function validateIsMatchingActive(MatchingRuleEntity $entity): void
    {
        $matching = $this->getMatching($entity);
        if (!empty($matching) && !empty($matching->get('isActive'))) {
            throw new BadRequest($this->getInjection('language')->translate('notValidMatchingActivation', 'exceptions', 'MatchingRule'));
        }
    }

    public function createMatchingType(MatchingRuleEntity $rule): AbstractMatchingRule
    {
        return $this->getInjection('matchingManager')->createMatchingType($rule);
    }

    protected function validateFieldType(MatchingRuleEntity $entity): void
    {
        $type = $entity->get('type');
        if (empty($type) || $type === 'set') {
            return;
        }

        $className = $this->getInjection('metadata')->get(['app', 'matchingRuleTypes', $type, 'className']);
        if (!$className || !class_exists($className)) {
            return;
        }

        /** @var AbstractMatchingRule $className */
        $supportedTypes = $className::getSupportedFieldTypes();

        if (!empty($entity->get('attributeId'))) {
            $attribute = $this->getEntityManager()->getEntity('Attribute', $entity->get('attributeId'));
            if ($attribute && !in_array($attribute->get('type'), $supportedTypes)) {
                throw new BadRequest($this->getInjection('language')->translate('notValidAttributeType', 'exceptions', 'MatchingRule'));
            }
            return;
        }

        $field = $entity->get('field');
        if (empty($field)) {
            return;
        }

        $matching = $this->getMatching($entity);
        if (empty($matching)) {
            return;
        }

        $entityName = $matching->get('masterEntity');
        $fieldType  = $this->getInjection('metadata')->get(['entityDefs', $entityName, 'fields', $field, 'type']);
        if (empty($fieldType)) {
            return;
        }

        if (!in_array($fieldType, $supportedTypes)) {
            throw new BadRequest($this->getInjection('language')->translate('notValidFieldType', 'exceptions', 'MatchingRule'));
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('matchingManager');
        $this->addDependency('language');
        $this->addDependency('metadata');
        $this->addDependency('expressionLanguage');
    }

    /**
     * @param MatchingRuleEntity $entity
     * @param array              $options
     *
     * @return void
     */
    protected function afterSave(OrmEntity $entity, array $options = []): void
    {
        parent::afterSave($entity, $options);

        $this->saveExpressions($entity);
        $this->recalculateWeightForSets();
    }

    /**
     * @param MatchingRuleEntity $entity
     * @param array              $options
     *
     * @return void
     */
    protected function beforeRemove(OrmEntity $entity, array $options = [])
    {
        parent::beforeRemove($entity, $options);

        if ($entity->get('type') === 'set') {
            foreach ($entity->get('matchingRules') ?? [] as $rule) {
                $this->getEntityManager()->removeEntity($rule);
            }
        }
    }

    /**
     * @param MatchingRuleEntity $entity
     * @param array              $options
     *
     * @return void
     */
    protected function afterRemove(OrmEntity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->deleteExpressions($entity);
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

    protected function getMatchingRepository(): Matching
    {
        return $this->getEntityManager()->getRepository('Matching');
    }
}
