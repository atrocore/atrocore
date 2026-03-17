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

class Similar extends AbstractMatchingRule
{
    private function isFuzzySearchAvailable(): bool
    {
        return class_exists('AdvancedDataManagement\Core\FuzzySearch')
            && \AdvancedDataManagement\Core\FuzzySearch::isAvailable($this->getConnection());
    }

    private function getSimilarityScoreAlias(): string
    {
        return 'sim_score_' . Util::toUnderScore($this->rule->get('field'));
    }

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
        } elseif ($this->isFuzzySearchAvailable()) {
            $threshold = (float)$this->getConfig()->get('similarityThreshold', 0.3);
            $thresholdParam = 'sim_thr_' . IdGenerator::unsortableId();
            $scoreAlias = $this->getSimilarityScoreAlias();

            $sqlPart = "similarity({$alias}.{$escapedColumnName}, :{$parameter}) >= :{$thresholdParam}";
            $qb->setParameter($parameter, (string)$value);
            $qb->setParameter($thresholdParam, $threshold);
            $qb->addSelect("similarity({$alias}.{$escapedColumnName}, :{$parameter}) AS {$scoreAlias}");
        } else {
            $sqlPart = "REPLACE(LOWER(TRIM({$alias}.{$escapedColumnName})), ' ', '') = :$parameter";
            $qb->setParameter($parameter, str_replace(' ', '', strtolower(trim($value))));
        }

        return $sqlPart;
    }

    public function match(Entity $stageEntity, array $masterEntityData): int
    {
        $field = $this->rule->get('field');

        $fieldType = $this->getMetadata()->get("entityDefs.{$stageEntity->getEntityName()}.fields.{$field}.type");

        if (in_array($fieldType, ['array', 'extensibleMultiEnum', 'multiEnum'])) {
            $stageValue = $stageEntity->get($field) ?? [];
            $masterValue = $masterEntityData[$field] ?? [];

            if (is_string($masterValue)) {
                $masterValue = json_decode($masterValue, true) ?? [];
            }

            if (!is_array($stageValue) || !is_array($masterValue)) {
                return 0;
            }

            sort($stageValue);
            sort($masterValue);

            if ($stageValue === $masterValue) {
                return $this->rule->get('weight') ?? 0;
            }

            return 0;
        }

        if ($this->isFuzzySearchAvailable()) {
            $scoreAlias = $this->getSimilarityScoreAlias();
            $scoreKey = Util::toCamelCase($scoreAlias);
            $score = $masterEntityData[$scoreKey] ?? $masterEntityData[$scoreAlias] ?? null;

            if ($score !== null) {
                // return part of the weight proportionally to the similarity score
                return (int)round((float)$score * ($this->rule->get('weight') ?? 0));
            }
        }

        $stageValue = str_replace(' ', '', strtolower(trim((string)$stageEntity->get($field))));
        $masterValue = str_replace(' ', '', strtolower(trim((string)$masterEntityData[$field])));

        if ($stageValue === $masterValue) {
            return $this->rule->get('weight') ?? 0;
        }

        return 0;
    }
}
