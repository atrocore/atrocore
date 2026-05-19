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

class FieldContains extends AbstractMatchingRule
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

        if (!empty($this->rule->get('attributeId'))) {
            return $this->buildAttributeContainsSubquery($qb, $alias, $stageEntity);
        }

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
            if ($attribute && in_array($attribute->get('type'), ['array', 'multiEnum', 'extensibleMultiEnum'])) {
                $stageValue  = is_string($stageValue) ? (json_decode($stageValue, true) ?? []) : ($stageValue ?? []);
                $masterValue = is_string($masterValue) ? (json_decode($masterValue, true) ?? []) : ($masterValue ?? []);

                if (!is_array($stageValue) || !is_array($masterValue)) {
                    return 0.0;
                }

                return empty(array_diff($stageValue, $masterValue)) ? $this->getWeight() : 0.0;
            }

            if (empty($stageValue) && empty($masterValue)) {
                return 0.0;
            }

            return str_contains((string)$masterValue, (string)$stageValue) ? $this->getWeight() : 0.0;
        }

        $field = $this->rule->get('field');

        $fieldType = $this->getMetadata()->get("entityDefs.{$stageEntity->getEntityName()}.fields.{$field}.type");

        $stageValue = $stageEntity->get($field);
        $masterValue = $masterEntityData[$field];

        if (empty($stageValue) && empty($masterValue)) {
            return 0.0;
        }

        if (in_array($fieldType, ['array', 'extensibleMultiEnum', 'multiEnum'])) {
            if (is_string($masterValue)) {
                $masterValue = json_decode($masterValue, true) ?? [];
            }

            if (!is_array($stageValue) || !is_array($masterValue)) {
                return 0.0;
            }

            if (empty(array_diff($stageValue, $masterValue))) {
                return $this->getWeight();
            }
        } else {
            if (str_contains($masterValue, $stageValue)) {
                return $this->getWeight();
            }
        }

        return 0.0;
    }

    private function buildAttributeContainsSubquery(QueryBuilder $qb, string $alias, Entity $stageEntity): string
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
            return "EXISTS (SELECT 1 FROM {$tableName}_attribute_value {$subAlias}"
                . " WHERE {$baseCondition}"
                . " AND ({$subAlias}.{$col} IS NULL OR {$subAlias}.{$col} = '' OR {$subAlias}.{$col} = '[]'))";
        }

        if (is_array($value)) {
            $valParam = IdGenerator::unsortableId();
            $qb->setParameter($valParam, '%"' . reset($value) . '"%');

            return "EXISTS (SELECT 1 FROM {$tableName}_attribute_value {$subAlias}"
                . " WHERE {$baseCondition}"
                . " AND {$subAlias}.{$col} LIKE :{$valParam})";
        }

        $valParam = IdGenerator::unsortableId();
        $qb->setParameter($valParam, '%' . $value . '%');

        return "EXISTS (SELECT 1 FROM {$tableName}_attribute_value {$subAlias}"
            . " WHERE {$baseCondition}"
            . " AND {$subAlias}.{$col} IS NOT NULL"
            . " AND {$subAlias}.{$col} LIKE :{$valParam})";
    }
}
