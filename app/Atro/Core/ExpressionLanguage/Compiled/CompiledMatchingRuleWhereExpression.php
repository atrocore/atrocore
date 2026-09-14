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

namespace Atro\Core\ExpressionLanguage\Compiled;

/**
 * The returned array is a native ORM where-clause (the same DSL `Repository::where()` and the
 * `findEntities`/`findRecords` Twig functions accept - e.g. {nameFrFr: stageEntity.get('name')}),
 * evaluated against the master entity being searched for candidates.
 */
interface CompiledMatchingRuleWhereExpression extends CompiledExpression
{
    /**
     * @return array<string, mixed>
     */
    public function eval(MatchingRuleWhereContext $context): array;
}
