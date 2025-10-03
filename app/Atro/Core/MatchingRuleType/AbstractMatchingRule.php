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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\Entity;

abstract class AbstractMatchingRule
{
    protected Container $container;
    protected Entity $rule;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    abstract public function prepareMatchingSqlPart(QueryBuilder $qb, Entity $stageEntity): string;

    abstract public function match(Entity $stageEntity, Entity $masterEntity): int;

    public function setRule(Entity $rule): void
    {
        $this->rule = $rule;
    }

    protected function getConnection(): Connection
    {
        return $this->container->get('connection');
    }
}
