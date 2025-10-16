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

use Atro\Core\Utils\Util;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\Entity;

class Set extends AbstractMatchingRule
{
    public static function getSupportedFieldTypes(): array
    {
        return [];
    }

    public function getWeight(): int
    {
        return 0;
    }

    public function prepareMatchingSqlPart(QueryBuilder $qb, Entity $stageEntity): string
    {
        $sqlPart = '';
//        $columnName = Util::toUnderScore($this->rule->get('targetField'));
//        $escapedColumnName = $this->getConnection()->quoteIdentifier($columnName);
//
//        $sqlPart = "$escapedColumnName IS NOT NULL AND $escapedColumnName LIKE :{$this->rule->get('id')}";
//        $qb->setParameter($this->rule->get('id'), "%".$stageEntity->get($this->rule->get('sourceField'))."%");
//
//        $qb->addSelect($escapedColumnName);

        return $sqlPart;
    }

    public function match(Entity $stageEntity, array $masterEntityData): int
    {
//        $stageValue = $stageEntity->get($this->rule->get('sourceField'));
//        $masterValue = $masterEntityData[$this->rule->get('targetField')];
//
//        if (!empty($stageValue) && !empty($masterValue) && strpos($masterValue, $stageValue) !== false) {
//            return $this->rule->get('weight') ?? 0;
//        }

        return 0;
    }
}
