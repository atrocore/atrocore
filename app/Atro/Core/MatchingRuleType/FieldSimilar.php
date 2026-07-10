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

class FieldSimilar extends AbstractMatchingRule
{
    public static function getSupportedFieldTypes(): array
    {
        return [
            "markdown",
            "text",
            "url",
            "varchar",
            "wysiwyg",
            "array",
            "multiEnum"
        ];
    }

    public function prepareMatchingSqlPart(QueryBuilder $qb, Entity $stageEntity): string
    {
        $alias = $qb->getQueryPart('from')[0]['alias'];

        if (!empty($this->rule->get('attributeId'))) {
            return $this->buildAttributeSimilarSubquery($qb, $alias, $stageEntity);
        }

        $field = $this->rule->get('field');
        $columnName = Util::toUnderScore($field);
        $escapedColumnName = $this->getConnection()->quoteIdentifier($columnName);
        $value = $stageEntity->get($field);
        $parameter = IdGenerator::unsortableId();

        if ($value === null) {
            return '1=0';
        }

        if (empty($value)) {
            return "({$alias}.{$escapedColumnName} IS NULL OR {$alias}.{$escapedColumnName} = '' OR {$alias}.{$escapedColumnName} = '[]')";
        }

        if (is_array($value)) {
            $qb->setParameter($parameter, '%"' . reset($value) . '"%');
            return "{$alias}.{$escapedColumnName} LIKE :$parameter";
        }

        $qb->setParameter($parameter, str_replace(' ', '', strtolower(trim($value))));

        return "REPLACE(LOWER(TRIM({$alias}.{$escapedColumnName})), ' ', '') = :$parameter";
    }

    public function match(Entity $stageEntity, array $masterEntityData): float
    {
        if (!empty($this->rule->get('attributeId'))) {
            $attributeId = $this->rule->get('attributeId');
            $entityName  = $stageEntity->getEntityName();
            $stageValue  = $this->loadAttributeRawValue($entityName, $stageEntity->id, $attributeId);
            $masterValue = $this->loadAttributeRawValue($entityName, $masterEntityData['id'], $attributeId);

            if ($stageValue === false || $masterValue === false) {
                return 0.0;
            }

            $attribute = $this->getEntityManager()->getEntity('Attribute', $attributeId);
            if ($attribute && in_array($attribute->get('type'), ['array', 'multiEnum', 'linkMultiple'])) {
                $stageValue  = is_string($stageValue) ? (json_decode($stageValue, true) ?? []) : ($stageValue ?? []);
                $masterValue = is_string($masterValue) ? (json_decode($masterValue, true) ?? []) : ($masterValue ?? []);
                sort($stageValue);
                sort($masterValue);

                return $stageValue === $masterValue ? $this->getWeight() : 0.0;
            }

            if ($stageValue === null && $masterValue === null) {
                return 0.0;
            }

            $stageValue  = str_replace(' ', '', strtolower(trim((string)$stageValue)));
            $masterValue = str_replace(' ', '', strtolower(trim((string)$masterValue)));

            return $stageValue === $masterValue ? $this->getWeight() : 0.0;
        }

        $field = $this->rule->get('field');
        $fieldType = $this->getMetadata()->get("entityDefs.{$stageEntity->getEntityName()}.fields.{$field}.type");

        if (in_array($fieldType, ['array', 'linkMultiple', 'multiEnum'])) {
            $stageValue = $fieldType === 'linkMultiple'
                ? ($stageEntity->get($field . 'Ids') ?? [])
                : ($stageEntity->get($field) ?? []);
            $masterValue = $masterEntityData[$field . 'Ids'] ?? $masterEntityData[$field] ?? [];

            if (is_string($masterValue)) {
                $masterValue = json_decode($masterValue, true) ?? [];
            }

            if (!is_array($stageValue) || !is_array($masterValue)) {
                return 0.0;
            }

            sort($stageValue);
            sort($masterValue);

            return $stageValue === $masterValue ? $this->getWeight() : 0.0;
        }

        if ($stageEntity->get($field) === null && ($masterEntityData[$field] ?? null) === null) {
            return 0.0;
        }

        $stageValue = str_replace(' ', '', strtolower(trim((string)$stageEntity->get($field))));
        $masterValue = str_replace(' ', '', strtolower(trim((string)$masterEntityData[$field])));

        return $stageValue === $masterValue ? $this->getWeight() : 0.0;
    }

    private function buildAttributeSimilarSubquery(QueryBuilder $qb, string $alias, Entity $stageEntity): string
    {
        $attributeId = $this->rule->get('attributeId');
        $entityName  = $stageEntity->getEntityName();
        $tableName   = Util::toUnderScore(lcfirst($entityName));

        $attribute = $this->getEntityManager()->getEntity('Attribute', $attributeId);
        if (!$attribute) {
            return '1=0';
        }

        $col   = $this->getAttributeValueColumn($attribute->get('type'));
        $value = $this->loadAttributeRawValue($entityName, $stageEntity->id, $attributeId);

        if ($value === false) {
            return '1=0';
        }

        $subAlias  = 'av_' . IdGenerator::unsortableId();
        $attrParam = IdGenerator::unsortableId();

        $qb->setParameter($attrParam, $attributeId);

        $baseCondition = "{$subAlias}.{$tableName}_id = {$alias}.id"
            . " AND {$subAlias}.attribute_id = :{$attrParam}"
            . " AND {$subAlias}.deleted = :false";

        if ($value === null) {
            return '1=0';
        }

        if (is_array($value)) {
            $valParam = IdGenerator::unsortableId();
            $qb->setParameter($valParam, '%"' . reset($value) . '"%');

            return "EXISTS (SELECT 1 FROM {$tableName}_attribute_value {$subAlias}"
                . " WHERE {$baseCondition}"
                . " AND {$subAlias}.{$col} LIKE :{$valParam})";
        }

        $valParam = IdGenerator::unsortableId();
        $qb->setParameter($valParam, str_replace(' ', '', strtolower(trim((string)$value))));

        return "EXISTS (SELECT 1 FROM {$tableName}_attribute_value {$subAlias}"
            . " WHERE {$baseCondition}"
            . " AND REPLACE(LOWER(TRIM({$subAlias}.{$col})), ' ', '') = :{$valParam})";
    }
}
