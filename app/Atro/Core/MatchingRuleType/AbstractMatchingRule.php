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

use Atro\Core\AttributeFieldConverter;
use Atro\Core\Container;
use Atro\Core\Utils\Config;
use Atro\Core\Utils\Metadata;
use Atro\Core\Utils\Util;
use Atro\Entities\MatchingRule as MatchingRuleEntity;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

abstract class AbstractMatchingRule
{
    protected MatchingRuleEntity $rule;

    public function __construct(private readonly Container $container)
    {
    }

    abstract public static function getSupportedFieldTypes(): array;

    abstract public function prepareMatchingSqlPart(QueryBuilder $qb, Entity $stageEntity): string;

    abstract public function match(Entity $stageEntity, array $masterEntityData): float;

    public function setRule(MatchingRuleEntity $rule): void
    {
        $this->rule = $rule;
    }

    public function getWeight(): float
    {
        return (float)($this->rule->get('weight') ?? 0);
    }

    protected function getConnection(): Connection
    {
        return $this->container->get('connection');
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    protected function getAttributeValueColumn(string $type): string
    {
        return $this->container->get(AttributeFieldConverter::class)->getFieldType($type)->getValueColumn();
    }

    protected function loadAttributeRawValue(string $entityName, string $entityId, string $attributeId): mixed
    {
        $attribute = $this->getEntityManager()->getEntity('Attribute', $attributeId);
        if (!$attribute) {
            return null;
        }

        $tableName = Util::toUnderScore(lcfirst($entityName));
        $col       = $this->getAttributeValueColumn($attribute->get('type'));

        return $this->getConnection()->createQueryBuilder()
            ->select("av.{$col}")
            ->from("{$tableName}_attribute_value", 'av')
            ->where("av.{$tableName}_id = :entityId")
            ->andWhere('av.attribute_id = :attrId')
            ->andWhere('av.deleted = :false')
            ->setParameter('entityId', $entityId)
            ->setParameter('attrId', $attributeId)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchOne();
    }
}
