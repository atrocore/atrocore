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
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\Entity;

class FieldEqual extends AbstractMatchingRule
{
    public static function getSupportedFieldTypes(): array
    {
        return [
            "array",
            "bool",
            "date",
            "datetime",
            "enum",
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
        $alias = $qb->getQueryPart('from')[0]['alias'];

        if (!empty($this->rule->get('attributeId'))) {
            return $this->buildAttributeEqualSubquery($qb, $alias, $stageEntity);
        }

        $fieldName = $this->rule->get('field');

        $fieldType = $this->getMetadata()->get("entityDefs.{$stageEntity->getEntityName()}.fields.{$fieldName}.type");

        if (in_array($fieldType, ['link', 'file'])) {
            $fieldName .= 'Id';
        }

        $columnName = Util::toUnderScore($fieldName);
        $escapedColumnName = $this->getConnection()->quoteIdentifier($columnName);

        $value = $stageEntity->get($fieldName);

        if ($value === null) {
            return '1=0';
        }

        if (in_array($fieldType, ['array', 'multiEnum'])) {
            $value = json_encode($value);
        }

        $parameter = IdGenerator::unsortableId();

        $sqlPart = "{$alias}.{$escapedColumnName} = :$parameter";
        $qb->setParameter($parameter, $value, Mapper::getParameterType($value));

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

            if ($stageValue === null && $masterValue === null) {
                return 0.0;
            }

            return $stageValue === $masterValue ? $this->getWeight() : 0.0;
        }

        $fieldName = $this->rule->get('field');

        $fieldType = $this->getMetadata()->get("entityDefs.{$stageEntity->getEntityName()}.fields.{$fieldName}.type");

        if (in_array($fieldType, ['link', 'file'])) {
            $fieldName .= 'Id';
        }

        $value = $stageEntity->get($fieldName);
        if (in_array($fieldType, ['array', 'multiEnum'])) {
            if ($value !== null) {
                $value = json_encode($value);
            }
        }

        if ($value === null && ($masterEntityData[$fieldName] ?? null) === null) {
            return 0.0;
        }

        if ($value === $masterEntityData[$fieldName]) {
            return $this->getWeight();
        }

        return 0.0;
    }

    private function buildAttributeEqualSubquery(QueryBuilder $qb, string $alias, Entity $stageEntity): string
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

        $valParam = IdGenerator::unsortableId();
        $qb->setParameter($valParam, $value, Mapper::getParameterType($value));

        return "EXISTS (SELECT 1 FROM {$tableName}_attribute_value {$subAlias}"
            . " WHERE {$baseCondition}"
            . " AND {$subAlias}.{$col} = :{$valParam})";
    }
}
