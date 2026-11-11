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

namespace Atro\Core\BlockingRuleType;

use Atro\Core\Utils\IdGenerator;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\Entity;

class FieldSimilar extends AbstractBlockingRule
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

    public function prepareSqlPart(QueryBuilder $qb, Entity $stageEntity): string
    {
        $alias = $qb->getQueryPart('from')[0]['alias'];

        if (!empty($this->settings->get('attributeId'))) {
            return $this->buildAttributeSimilarSubquery($qb, $alias, $stageEntity);
        }

        $field = $this->settings->get('field');
        $columnName = Util::toUnderScore($field);
        $escapedColumnName = $this->getConnection()->quoteIdentifier($columnName);
        $value = $stageEntity->get($field);
        $parameter = IdGenerator::unsortableId();

        if (is_array($value)) {
            $parts = [];
            foreach (array_unique($value) as $item) {
                $itemParameter = IdGenerator::unsortableId();
                $qb->setParameter($itemParameter, '%"' . $item . '"%');
                $parts[] = "{$alias}.{$escapedColumnName} LIKE :$itemParameter";
            }

            return empty($parts) ? '1=0' : '(' . implode(' OR ', $parts) . ')';
        }

        $value = str_replace(' ', '', strtolower(trim((string)$value)));
        if ($value === '') {
            return '1=0';
        }

        $qb->setParameter($parameter, $value);

        return "REPLACE(LOWER(TRIM({$alias}.{$escapedColumnName})), ' ', '') = :$parameter";
    }

    private function buildAttributeSimilarSubquery(QueryBuilder $qb, string $alias, Entity $stageEntity): string
    {
        $attributeId = $this->settings->get('attributeId');
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
            . " AND {$subAlias}.attribute_id = :{$attrParam}";

        if (is_array($value)) {
            $parts = [];
            foreach (array_unique($value) as $item) {
                $itemParameter = IdGenerator::unsortableId();
                $qb->setParameter($itemParameter, '%"' . $item . '"%');
                $parts[] = "{$subAlias}.{$col} LIKE :$itemParameter";
            }

            if (empty($parts)) {
                return '1=0';
            }

            return "EXISTS (SELECT 1 FROM {$tableName}_attribute_value {$subAlias}"
                . " WHERE {$baseCondition}"
                . " AND (" . implode(' OR ', $parts) . "))";
        }

        $value = str_replace(' ', '', strtolower(trim((string)$value)));
        if ($value === '') {
            return '1=0';
        }

        $valParam = IdGenerator::unsortableId();
        $qb->setParameter($valParam, $value);

        return "EXISTS (SELECT 1 FROM {$tableName}_attribute_value {$subAlias}"
            . " WHERE {$baseCondition}"
            . " AND REPLACE(LOWER(TRIM({$subAlias}.{$col})), ' ', '') = :{$valParam})";
    }
}
