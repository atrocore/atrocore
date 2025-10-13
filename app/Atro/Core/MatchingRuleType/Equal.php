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
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\Entity;

class Equal extends AbstractMatchingRule
{
    public static function getSupportedFieldTypes(): array
    {
        return [
            "array",
            "bool",
            "date",
            "datetime",
            "enum",
            "extensibleEnum",
            "extensibleMultiEnum",
            "file",
            "float",
            "int",
            "link",
            "measure",
            "multiEnum",
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
        $columnName = Util::toUnderScore($this->rule->get('targetField'));
        $escapedColumnName = $this->getConnection()->quoteIdentifier($columnName);

        $value = $stageEntity->get($this->rule->get('sourceField'));

        $sqlPart = "$escapedColumnName = :{$this->rule->get('id')}";
        $qb->setParameter($this->rule->get('id'), $value, Mapper::getParameterType($value));

        $qb->addSelect($escapedColumnName);

        return $sqlPart;
    }

    public function match(Entity $stageEntity, array $masterEntityData): int
    {
        $stageValue = $stageEntity->get($this->rule->get('sourceField'));
        $masterValue = $masterEntityData[$this->rule->get('targetField')];

        if ($stageValue === $masterValue) {
            return $this->rule->get('weight') ?? 0;
        }

        return 0;
    }
}
