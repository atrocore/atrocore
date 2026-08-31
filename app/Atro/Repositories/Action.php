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
use Atro\Core\DataManager;
use Atro\Entities\Action as ActionEntity;
use Espo\ORM\Entity;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;

class Action extends Base
{
    public const array CONDITIONS_EXPRESSION_NAMES = ['entity', 'uiRecord', 'uiRecordFromName', 'uiRecordFrom'];

    public const string CONDITIONS_EXPRESSION_NAMESPACE = 'Compiled\\Condition';

    public static function getCompiledExpressionClassName(ActionEntity $action): string
    {
        return 'A' . md5($action->id);
    }

    public static function getCompiledExpressionFullClassName(ActionEntity $action): string
    {
        return self::CONDITIONS_EXPRESSION_NAMESPACE . '\\' . self::getCompiledExpressionClassName($action);
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if (
            $entity->isAttributeChanged('targetEntity')
            && in_array($entity->get('type'), ['update', 'email', 'delete'])
        ) {
            $entity->set('searchEntity', $entity->get('targetEntity'));
        }

        if (
            $entity->isAttributeChanged('searchEntity') && $entity->get('searchEntity')
            && in_array($entity->get('type'), ['create', 'createOrUpdate'])
            && $entity->get('updateType') !== 'script'
        ) {
            $entity->set('updateType', 'script');
        }

        $this->validateConditionsExpression($entity);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        $this->deleteCacheFile();

        parent::afterSave($entity, $options);

        $this->saveConditionsExpression($entity);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        $this->deleteCacheFile();

        parent::afterRemove($entity, $options);

        $this->deleteConditionsExpression($entity);
    }

    public function deleteCacheFile(): void
    {
        if (empty($this->getMemoryStorage()->get('importJobId'))) {
            $file = DataManager::CACHE_DIR_PATH . '/dynamic_action.json';
            if (file_exists($file)) {
                unlink($file);
            }

            $this->getConfig()->remove('cacheTimestamp');
            $this->getConfig()->save();

            DataManager::pushPublicData('dataTimestamp', (new \DateTime())->getTimestamp());
        }
    }

    protected function validateConditionsExpression(ActionEntity $action): void
    {
        if ($action->get('conditionsType') === 'expression' && $action->isAttributeChanged('conditionsExpression')) {
            if (empty($action->get('conditionsExpression'))) {
                throw new BadRequest($this->translateException('expressionCannotBeEmpty'));
            }

            try {
                $this->getExpressionLanguage()->lint($action->get('conditionsExpression'), self::CONDITIONS_EXPRESSION_NAMES);
            } catch (SyntaxError $e) {
                throw new BadRequest($e->getMessage());
            }
        }
    }

    protected function saveConditionsExpression(ActionEntity $action): void
    {
        if ($action->get('conditionsType') === 'expression' && $action->isAttributeChanged('conditionsExpression')) {
            $expression = $action->get('conditionsExpression');

            $code = $this->getExpressionLanguage()->compile($action->get('conditionsExpression'), self::CONDITIONS_EXPRESSION_NAMES);
            $namespace = self::CONDITIONS_EXPRESSION_NAMESPACE;
            $className = self::getCompiledExpressionClassName($action);

            $literal = var_export($expression, true);

            $prelude = [];
            foreach (self::CONDITIONS_EXPRESSION_NAMES as $name) {
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
    final class {$className} implements \\Atro\\Core\\ExpressionLanguage\\Compiled\CompiledActionCondition
    {
        public static function expression(): string
        {
            return {$literal};
        }

        public function eval(\\Atro\\Core\\ExpressionLanguage\\Compiled\\ActionConditionContext \$context): bool
        {
    {$prelude}

            return (bool) ({$code});
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
    }

    protected function deleteConditionsExpression(ActionEntity $action): void
    {
        $fileName = 'data/custom-code/' . str_replace('\\', '/', self::getCompiledExpressionFullClassName($action)) . '.php';
        if (file_exists($fileName)) {
            unlink($fileName);
        }
    }

    /**
     * @param ActionEntity $entity
     *
     * @return void
     */
    protected function afterEntityPopulated(Entity $entity): void
    {
        if (!$entity->isNew() && $entity->get('conditionsType') === 'expression') {
            $className = self::getCompiledExpressionFullClassName($entity);
            if (class_exists($className) && is_a($className, CompiledExpression::class, true)) {
                $entity->set('conditionsExpression', $className::expression());
            }
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('expressionLanguage');
    }

    protected function getExpressionLanguage(): ExpressionLanguage
    {
        return $this->getInjection('expressionLanguage');
    }
}
