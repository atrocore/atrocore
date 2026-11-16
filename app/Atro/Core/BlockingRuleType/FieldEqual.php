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
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\Entity;

class FieldEqual extends AbstractBlockingRule
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
            "wysiwyg",
            "email"
        ];
    }

    public function prepareSqlPart(QueryBuilder $qb, Entity $stageEntity): string
    {
        $alias = $qb->getQueryPart('from')[0]['alias'];

        if (!empty($this->settings->get('attributeId'))) {
            return $this->buildAttributeEqualSubquery($qb, $alias, $stageEntity);
        }

        $fieldName = $this->settings->get('field');

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

    private function buildAttributeEqualSubquery(QueryBuilder $qb, string $alias, Entity $stageEntity): string
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
            return '1=0';
        }

        $valParam = IdGenerator::unsortableId();
        $qb->setParameter($valParam, $value, Mapper::getParameterType($value));

        return "EXISTS (SELECT 1 FROM {$tableName}_attribute_value {$subAlias}"
            . " WHERE {$baseCondition}"
            . " AND {$subAlias}.{$col} = :{$valParam})";
    }
}
