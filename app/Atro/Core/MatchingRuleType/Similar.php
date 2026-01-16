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

class Similar extends AbstractMatchingRule
{
    public static function getSupportedFieldTypes(): array
    {
        return [
            "markdown",
            "password",
            "text",
            "url",
            "varchar",
            "wysiwyg"
        ];
    }

    public function prepareMatchingSqlPart(QueryBuilder $qb, Entity $stageEntity): string
    {
        $alias = $qb->getQueryPart('from')[0]['alias'];

        $columnName = Util::toUnderScore($this->rule->get('field'));
        $escapedColumnName = $this->getConnection()->quoteIdentifier($columnName);

        $sqlPart = "REPLACE(LOWER(TRIM({$alias}.{$escapedColumnName})), ' ', '') = :{$this->rule->get('id')}";
        $qb->setParameter($this->rule->get('id'), str_replace(' ', '', strtolower(trim($stageEntity->get($this->rule->get('field'))))));

        return $sqlPart;
    }

    public function match(Entity $stageEntity, array $masterEntityData): int
    {
        $stageValue = str_replace(' ', '', strtolower(trim($stageEntity->get($this->rule->get('field')))));
        $masterValue = str_replace(' ', '', strtolower(trim($masterEntityData[$this->rule->get('field')])));

        if ($stageValue === $masterValue) {
            return $this->rule->get('weight') ?? 0;
        }

        return 0;
    }
}
