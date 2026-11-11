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

class FieldContains extends AbstractBlockingRule
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
            "multiEnum"
        ];
    }

    public function prepareSqlPart(QueryBuilder $qb, Entity $stageEntity): string
    {
        $alias = $qb->getQueryPart('from')[0]['alias'];

        if (!empty($this->settings->get('attributeId'))) {
            return $this->buildAttributeContainsSubquery($qb, $alias, $stageEntity);
        }

        $field = $this->settings->get('field');
        $columnName = Util::toUnderScore($this->settings->get('field'));
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
            $qb->setParameter($parameter, "%" . $stageEntity->get($this->settings->get('field')) . "%");
        }

        return $sqlPart;
    }

    private function buildAttributeContainsSubquery(QueryBuilder $qb, string $alias, Entity $stageEntity): string
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
