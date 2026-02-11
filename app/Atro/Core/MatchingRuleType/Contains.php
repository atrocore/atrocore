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

use Atro\Core\Utils\IdGenerator;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\Entity;

class Contains extends AbstractMatchingRule
{
    public static function getSupportedFieldTypes(): array
    {
        return [
            "markdown",
            "password",
            "text",
            "url",
            "varchar",
            "wysiwyg",
            "array",
            "extensibleMultiEnum",
            "multiEnum"
        ];
    }

    public function prepareMatchingSqlPart(QueryBuilder $qb, Entity $stageEntity): string
    {
        $alias = $qb->getQueryPart('from')[0]['alias'];
        $field = $this->rule->get('field');
        $columnName = Util::toUnderScore($this->rule->get('field'));
        $escapedColumnName = $this->getConnection()->quoteIdentifier($columnName);
        $value = $stageEntity->get($field);
        $parameter = IdGenerator::unsortableId();

        if (empty($value)) {
            $sqlPart = "({$alias}.{$escapedColumnName} IS NULL OR {$alias}.{$escapedColumnName} = '' OR {$alias}.{$escapedColumnName} = '[]')";
        } elseif (is_array($value)) {
            $sqlPart = "{$alias}.{$escapedColumnName} LIKE :$parameter";
            $qb->setParameter($parameter, '%"' . reset($value) . '"%');
        } else {
            $sqlPart = "{$alias}.{$escapedColumnName} IS NOT NULL AND {$alias}.{$escapedColumnName} LIKE :$parameter";
            $qb->setParameter($parameter, "%" . $stageEntity->get($this->rule->get('field')) . "%");
        }

        return $sqlPart;
    }

    public function match(Entity $stageEntity, array $masterEntityData): int
    {
        $field = $this->rule->get('field');

        $fieldType = $this->getMetadata()->get("entityDefs.{$stageEntity->getEntityName()}.fields.{$field}.type");

        $stageValue = $stageEntity->get($field);
        $masterValue = $masterEntityData[$field];

        if (empty($stageValue) && empty($masterValue)) {
            return 0;
        }

        if (in_array($fieldType, ['array', 'extensibleMultiEnum', 'multiEnum'])) {
            if (is_string($masterValue)) {
                $masterValue = json_decode($masterValue, true) ?? [];
            }

            if (!is_array($stageValue) || !is_array($masterValue)) {
                return 0;
            }

            if (empty(array_diff($stageValue, $masterValue))) {
                return $this->rule->get('weight') ?? 0;
            }
        } else {
            if (str_contains($masterValue, $stageValue)) {
                return $this->rule->get('weight') ?? 0;
            }
        }

        return 0;
    }
}
