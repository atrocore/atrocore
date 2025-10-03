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

class Like extends AbstractMatchingRule
{
    public function prepareMatchingSqlPart(QueryBuilder $qb, Entity $stageEntity): string
    {
        $sqlPart = "REPLACE(LOWER(TRIM(" . $this->getConnection()->quoteIdentifier($this->rule->get('sourceField')) . ")), ' ', '') = :" . $this->rule->get('id');
        $qb->setParameter($this->rule->get('id'), str_replace(' ', '', strtolower(trim($stageEntity->get($this->rule->get('targetField'))))));

        $qb->addSelect($this->getConnection()->quoteIdentifier($this->rule->get('sourceField')));

        return $sqlPart;
    }

    public function match(Entity $stageEntity, Entity $masterEntity): int
    {
        $stageValue = str_replace(' ', '', strtolower(trim($stageEntity->get($this->rule->get('sourceField')))));
        $masterValue = str_replace(' ', '', strtolower(trim($masterEntity->get($this->rule->get('targetField')))));

        if ($stageValue === $masterValue) {
            return $this->rule->get('weight') ?? 0;
        }

        return 0;
    }
}
