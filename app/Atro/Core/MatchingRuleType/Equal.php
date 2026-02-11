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

        $fieldName = $this->rule->get('field');

        $fieldType = $this->getMetadata()->get("entityDefs.{$stageEntity->getEntityName()}.fields.{$fieldName}.type");

        if (in_array($fieldType, ['link', 'file'])) {
            $fieldName .= 'Id';
        }

        $columnName = Util::toUnderScore($fieldName);
        $escapedColumnName = $this->getConnection()->quoteIdentifier($columnName);

        $value = $stageEntity->get($fieldName);

        if (in_array($fieldType, ['array', 'extensibleMultiEnum', 'multiEnum'])) {
            if ($value !== null) {
                $value = json_encode($value);
            }
        }

        $parameter = IdGenerator::unsortableId();

        $sqlPart = "{$alias}.{$escapedColumnName} = :$parameter";
        $qb->setParameter($parameter, $value, Mapper::getParameterType($value));

        return $sqlPart;
    }

    public function match(Entity $stageEntity, array $masterEntityData): int
    {
        $fieldName = $this->rule->get('field');

        $fieldType = $this->getMetadata()->get("entityDefs.{$stageEntity->getEntityName()}.fields.{$fieldName}.type");

        if (in_array($fieldType, ['link', 'file'])) {
            $fieldName .= 'Id';
        }

        if ($stageEntity->get($fieldName) === $masterEntityData[$fieldName]) {
            return $this->rule->get('weight') ?? 0;
        }

        return 0;
    }
}
