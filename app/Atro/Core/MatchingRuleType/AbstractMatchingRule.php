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

use Atro\Core\Container;
use Atro\Core\Utils\Config;
use Atro\Core\Utils\Metadata;
use Atro\Entities\MatchingRule as MatchingRuleEntity;
use Doctrine\DBAL\Connection;
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

    abstract public function match(Entity $stageEntity, array $masterEntityData): int;

    public function setRule(MatchingRuleEntity $rule): void
    {
        $this->rule = $rule;
    }

    public function getWeight(): int
    {
        return $this->rule->get('weight') ?? 0;
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
}
