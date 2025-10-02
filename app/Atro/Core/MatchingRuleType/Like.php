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
    public function prepareMatchingSqlPart(QueryBuilder $qb, Entity $rule, Entity $stageEntity): string
    {
        $sqlPart = "REPLACE(LOWER(TRIM(" . $this->getConnection()->quoteIdentifier($rule->get('sourceField')) . ")), ' ', '') = :" . $rule->get('id');
        $qb->setParameter($rule->get('id'), str_replace(' ', '', strtolower(trim($stageEntity->get($rule->get('targetField'))))));

        $qb->addSelect($this->getConnection()->quoteIdentifier($rule->get('sourceField')));

        return $sqlPart;
    }

    public function match(Entity $rule, Entity $stageEntity, Entity $masterEntity): int
    {
        echo '<pre>';
        print_r('123');
        die();
        
        return 0;
    }
}
