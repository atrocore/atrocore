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

use Espo\ORM\Entity;

/**
 * Everything a compiled Matching Rule score expression is allowed to see.
 *
 * Adding a property here is what makes a new variable available to expressions;
 * the generated eval() unpacks the properties it actually needs into local variables.
 */
final readonly class MatchingRuleScoreContext
{
    /**
     * @param array<string, mixed> $masterEntity the candidate's raw field values, as returned by the
     *                                            matching shortlist query - not a hydrated Entity
     */
    public function __construct(
        public Entity $stageEntity,
        public array $masterEntity
    ) {
    }
}
