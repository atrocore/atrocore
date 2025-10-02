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

use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\Entity;

interface MatchingRuleTypeInterface
{
    public function prepareMatchingSqlPart(QueryBuilder $qb, Entity $rule, Entity $stageEntity): string;

    public function match(Entity $rule, Entity $stageEntity, Entity $masterEntity): int;
}
