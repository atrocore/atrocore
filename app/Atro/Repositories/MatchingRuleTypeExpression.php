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

use Atro\Core\ExpressionLanguage\Compiled\CompiledExpression;
use Atro\Core\Templates\Repositories\Subtype;
use Espo\ORM\Entity as OrmEntity;

class MatchingRuleTypeExpression extends Subtype
{
    protected function afterEntityPopulated(OrmEntity $entity): void
    {
        if ($entity->isNew()) {
            return;
        }

        /** @var AbstractRule $repository */
        $repository = $this->getEntityManager()->getRepository($this->getSupertypeName());

        foreach (array_keys($repository::EXPRESSION_FIELDS) as $field) {
            $className = $repository::getCompiledExpressionFullClassName($entity, $field);
            if (class_exists($className) && is_a($className, CompiledExpression::class, true)) {
                $expression = $className::expression();

                $entity->set($field, $expression);
                $entity->setFetched($field, $expression);
            }
        }
    }
}
