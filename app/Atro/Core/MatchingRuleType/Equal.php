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
        $alias = $qb->getQueryPart('from')[0]['alias'];

        $columnName = Util::toUnderScore($this->getFieldName($stageEntity));
        $escapedColumnName = $this->getConnection()->quoteIdentifier($columnName);

        $value = $stageEntity->get($this->rule->get('field'));

        $sqlPart = "{$alias}.{$escapedColumnName} = :{$this->rule->get('id')}";
        $qb->setParameter($this->rule->get('id'), $value, Mapper::getParameterType($value));

        return $sqlPart;
    }

    public function match(Entity $stageEntity, array $masterEntityData): int
    {
        $fieldName = $this->getFieldName($stageEntity);

        if ($stageEntity->get($fieldName) === $masterEntityData[$fieldName]) {
            return $this->rule->get('weight') ?? 0;
        }

        return 0;
    }

    protected function getFieldName(Entity $stageEntity): string
    {
        $field = $this->rule->get('field');
        $fieldType = $this->getMetadata()->get("entityDefs.{$stageEntity->getEntityName()}.fields.{$field}.type");
        if ($fieldType === 'link') {
            $field .= 'Id';
        }

        return $field;
    }
}
