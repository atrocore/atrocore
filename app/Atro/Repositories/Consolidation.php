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
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;

class Consolidation extends Base
{
    public const array SKIP_CONDITION_EXPRESSION_NAMES = ['candidate', 'masterRecord'];

    public const string SKIP_CONDITION_EXPRESSION_NAMESPACE = 'Compiled\\ConsolidationSkipCondition';

    public static function getCompiledSkipConditionClassName(Entity $consolidation): string
    {
        return self::SKIP_CONDITION_EXPRESSION_NAMESPACE . '\\C' . md5($consolidation->id);
    }

    public function getByEntityName(?string $entityName): ?Entity
    {
        if (empty($entityName)) {
            return null;
        }

        return $this->where(['entityId' => $entityName])->findOne();
    }

    public function getContributorEntityName(string $masterEntityName): ?string
    {
        foreach ($this->getMetadata()->get('scopes', []) as $scopeName => $scopeDefs) {
            if (($scopeDefs['primaryEntityId'] ?? null) === $masterEntityName && ($scopeDefs['role'] ?? null) === 'contributor') {
                return $scopeName;
            }
        }

        return null;
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isNew()) {
            $entityName = $entity->get('entityId');

            if (empty($entityName) || empty($this->getContributorEntityName((string)$entityName))) {
                throw new BadRequest(
                    sprintf(
                        $this->getLanguage()->translate('entityTypeInvalid', 'exceptions', 'Consolidation'),
                        (string)$entityName
                    )
                );
            }

            if (!empty($this->getByEntityName($entityName))) {
                throw new BadRequest(
                    sprintf(
                        $this->getLanguage()->translate('consolidationAlreadyExists', 'exceptions', 'Consolidation'),
                        $entityName
                    )
                );
            }

            // remove a soft-deleted record with the same name to avoid a unique index collision
            $this->getDbal()->createQueryBuilder()
                ->delete($this->getDbal()->quoteIdentifier('consolidation'))
                ->where('entity_id = :name')
                ->andWhere('deleted = :true')
                ->setParameter('name', $entityName)
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->executeQuery();
        }

        if ($entity->isAttributeChanged('skipCondition') && !empty($entity->get('skipCondition'))) {
            try {
                $this->getExpressionLanguage()->lint($entity->get('skipCondition'), self::SKIP_CONDITION_EXPRESSION_NAMES);
            } catch (SyntaxError $e) {
                throw new BadRequest($e->getMessage());
            }
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if (!$entity->isAttributeChanged('skipCondition')) {
            return;
        }

        if (empty($entity->get('skipCondition'))) {
            $this->deleteSkipCondition($entity);
            return;
        }

        $expression = (string)$entity->get('skipCondition');
        $code = $this->getExpressionLanguage()->compile($expression, self::SKIP_CONDITION_EXPRESSION_NAMES);
        $namespace = self::SKIP_CONDITION_EXPRESSION_NAMESPACE;
        $className = substr(self::getCompiledSkipConditionClassName($entity), strlen($namespace) + 1);

        $literal = var_export($expression, true);

        $prelude = [];
        foreach (self::SKIP_CONDITION_EXPRESSION_NAMES as $name) {
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
    final class {$className} implements \\Atro\\Core\\ExpressionLanguage\\Compiled\\CompiledConsolidationSkipCondition
    {
        public static function expression(): string
        {
            return {$literal};
        }

        public function eval(\\Atro\\Core\\ExpressionLanguage\\Compiled\\ConsolidationSkipConditionContext \$context): bool
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

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->deleteSkipCondition($entity);
    }

    protected function deleteSkipCondition(Entity $entity): void
    {
        $fileName = 'data/custom-code/' . str_replace('\\', '/', self::getCompiledSkipConditionClassName($entity)) . '.php';
        if (file_exists($fileName)) {
            unlink($fileName);
        }
    }

    protected function afterEntityPopulated(Entity $entity): void
    {
        if ($entity->isNew()) {
            return;
        }

        $className = self::getCompiledSkipConditionClassName($entity);
        if (class_exists($className) && is_a($className, CompiledExpression::class, true)) {
            $expression = $className::expression();

            $entity->set('skipCondition', $expression);
            $entity->setFetched('skipCondition', $expression);
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
