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

class Like extends AbstractMatchingRule
{
    public static function getSupportedFieldTypes(): array
    {
        return [
            // "array",
            // "bool",
            // "date",
            // "datetime",
            // "enum",
            // "extensibleEnum",
            // "extensibleMultiEnum",
            // "file",
            // "float",
            // "int",
            // "link",
            // "measure",
            // "multiEnum",
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
        $sqlPart = "REPLACE(LOWER(TRIM(" . $this->getConnection()->quoteIdentifier(Util::toUnderScore($this->rule->get('targetField'))) . ")), ' ', '') = :" . $this->rule->get('id');
        $qb->setParameter($this->rule->get('id'), str_replace(' ', '', strtolower(trim($stageEntity->get($this->rule->get('sourceField'))))));

        $qb->addSelect($this->getConnection()->quoteIdentifier(Util::toUnderScore($this->rule->get('targetField'))));

        return $sqlPart;
    }

    public function match(Entity $stageEntity, array $masterEntityData): int
    {
        $stageValue = str_replace(' ', '', strtolower(trim($stageEntity->get($this->rule->get('sourceField')))));
        $masterValue = str_replace(' ', '', strtolower(trim($masterEntityData[$this->rule->get('targetField')])));

        if ($stageValue === $masterValue) {
            return $this->rule->get('weight') ?? 0;
        }

        return 0;
    }
}
