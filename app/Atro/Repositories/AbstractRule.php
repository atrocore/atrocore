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
use Atro\Core\Templates\Repositories\Base;
use Atro\Core\Utils\Util;
use Atro\Entities\Matching as MatchingEntity;
use Espo\ORM\Entity as OrmEntity;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;

abstract class AbstractRule extends Base
{
    public const string EXPRESSION_NAMESPACE = '';

    public const string WHERE_EXPRESSION_PREFIX = 'Where';

    public const string SCORE_EXPRESSION_PREFIX = 'Score';

    public const array EXPRESSION_FIELDS = [];

    protected const string RULE_SET_FIELD = '';

    protected const string RULE_TYPES_METADATA_KEY = '';

    /**
     * @param OrmEntity          $entity
     * @param array              $options
     *
     * @return void
     * @throws BadRequest
     */
    protected function beforeSave(OrmEntity $entity, array $options = [])
    {
        if (!empty($entity->get('matchingId')) && empty($this->getEntityManager()->getRepository('Matching')->get($entity->get('matchingId')))) {
            throw new BadRequest($this->getInjection('language')->translate('notValidMatching', 'exceptions', $this->entityType));
        }

        if (!empty($entity->get(static::RULE_SET_FIELD))) {
            $set = $this->get($entity->get(static::RULE_SET_FIELD));
            if (empty($set) || $set->get('type') !== 'set') {
                throw new BadRequest($this->getInjection('language')->translate('notValidMatchingRuleSet', 'exceptions', $this->entityType));
            }
        }

        $this->validateIsMatchingActive($entity);
        $this->validateFieldType($entity);
        $this->validateExpressions($entity);

        parent::beforeSave($entity, $options);
    }

    public static function getCompiledWhereExpressionClassName(OrmEntity $rule): string
    {
        return static::EXPRESSION_NAMESPACE . '\\' . self::WHERE_EXPRESSION_PREFIX . $rule->get('number');
    }

    public static function getCompiledScoreExpressionClassName(OrmEntity $rule): string
    {
        return static::EXPRESSION_NAMESPACE . '\\' . self::SCORE_EXPRESSION_PREFIX . $rule->get('number');
    }

    public static function getCompiledExpressionFullClassName(OrmEntity $rule, string $field): string
    {
        return static::EXPRESSION_NAMESPACE . '\\' . static::EXPRESSION_FIELDS[$field]['prefix'] . $rule->get('number');
    }

    /**
     * The class name is built from the rule's autoincrement number, which the database assigns
     * on insert - a just-inserted row has to be read back to learn it.
     */
    protected function getRuleNumber(OrmEntity $rule): ?string
    {
        if (!empty($rule->get('number'))) {
            return (string)$rule->get('number');
        }

        $conn = $this->getDbal();

        try {
            $number = $conn->createQueryBuilder()
                ->select('number')
                ->from($conn->quoteIdentifier(Util::toUnderScore(lcfirst($this->entityType))))
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

    protected function validateExpressions(OrmEntity $rule): void
    {
        if ($rule->get('type') !== 'expression') {
            return;
        }

        foreach (array_keys(static::EXPRESSION_FIELDS) as $field) {
            if (!$rule->isAttributeChanged($field)) {
                continue;
            }

            if (empty($rule->get($field))) {
                throw new BadRequest($this->getInjection('language')->translate('expressionCannotBeEmpty', 'exceptions', $this->entityType));
            }

            try {
                $this->getExpressionLanguage()->lint($rule->get($field), static::EXPRESSION_FIELDS[$field]['names']);
            } catch (SyntaxError $e) {
                throw new BadRequest($e->getMessage());
            }
        }
    }

    protected function saveExpressions(OrmEntity $rule): void
    {
        if ($rule->get('type') !== 'expression') {
            return;
        }

        $changed = array_filter(array_keys(static::EXPRESSION_FIELDS), fn(string $field) => $rule->isAttributeChanged($field));
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

    protected function compileExpression(OrmEntity $rule, string $field): void
    {
        $expression = (string)$rule->get($field);
        $config = static::EXPRESSION_FIELDS[$field];

        $code = $this->getExpressionLanguage()->compile($expression, $config['names']);
        $namespace = static::EXPRESSION_NAMESPACE;
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

    protected function deleteExpressions(OrmEntity $rule): void
    {
        foreach (array_keys(static::EXPRESSION_FIELDS) as $field) {
            $fileName = 'data/custom-code/' . str_replace('\\', '/', static::getCompiledExpressionFullClassName($rule, $field)) . '.php';
            if (file_exists($fileName)) {
                unlink($fileName);
            }
        }
    }

    protected function getExpressionLanguage(): ExpressionLanguage
    {
        return $this->getInjection('expressionLanguage');
    }

    public function getMatching(OrmEntity $rule): ?MatchingEntity
    {
        while (true) {
            $matchingRule = null;
            if (!empty($rule->get(static::RULE_SET_FIELD))) {
                $matchingRule = $this->get($rule->get(static::RULE_SET_FIELD));
            }
            if (!empty($matchingRule)) {
                $rule = $matchingRule;
            } else {
                break;
            }
        }

        return $this->getEntityManager()->getRepository('Matching')->get($rule->get('matchingId'));
    }

    public function validateIsMatchingActive(OrmEntity $entity): void
    {
        $matching = $this->getMatching($entity);
        if (!empty($matching) && !empty($matching->get('isActive'))) {
            throw new BadRequest($this->getInjection('language')->translate('notValidMatchingActivation', 'exceptions', $this->entityType));
        }
    }

    protected function validateFieldType(OrmEntity $entity): void
    {
        $type = $entity->get('type');
        if (empty($type) || $type === 'set') {
            return;
        }

        $className = $this->getInjection('metadata')->get(['app', static::RULE_TYPES_METADATA_KEY, $type, 'className']);
        if (!$className || !class_exists($className)) {
            return;
        }

        $supportedTypes = $className::getSupportedFieldTypes();

        if (!empty($entity->get('attributeId'))) {
            $attribute = $this->getEntityManager()->getEntity('Attribute', $entity->get('attributeId'));
            if ($attribute && !in_array($attribute->get('type'), $supportedTypes)) {
                throw new BadRequest($this->getInjection('language')->translate('notValidAttributeType', 'exceptions', $this->entityType));
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
            throw new BadRequest($this->getInjection('language')->translate('notValidFieldType', 'exceptions', $this->entityType));
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
     * @param OrmEntity          $entity
     * @param array              $options
     *
     * @return void
     */
    protected function afterSave(OrmEntity $entity, array $options = []): void
    {
        parent::afterSave($entity, $options);

        $this->saveExpressions($entity);
    }

    /**
     * @param OrmEntity          $entity
     * @param array              $options
     *
     * @return void
     */
    protected function beforeRemove(OrmEntity $entity, array $options = [])
    {
        parent::beforeRemove($entity, $options);

        if ($entity->get('type') === 'set') {
            foreach ($entity->getNestedRules() as $rule) {
                $this->getEntityManager()->removeEntity($rule);
            }
        }
    }

    /**
     * @param OrmEntity          $entity
     * @param array              $options
     *
     * @return void
     */
    protected function afterRemove(OrmEntity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->deleteExpressions($entity);
    }

    protected function getMatchingRepository(): Matching
    {
        return $this->getEntityManager()->getRepository('Matching');
    }
}
