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
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Connection;
use Espo\ORM\Entity;

class Like implements MatchingRuleTypeInterface
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function prepareMatchingSqlPart(QueryBuilder $qb, Entity $rule, Entity $entity): string
    {
        $sqlPart = "REPLACE(LOWER(TRIM(" . $this->getConnection()->quoteIdentifier($rule->get('sourceField')) . ")), ' ', '') = :" . $rule->get('id');
        $qb->setParameter($rule->get('id'), str_replace(' ', '', strtolower(trim($entity->get($rule->get('targetField'))))));

        $qb->addSelect($this->getConnection()->quoteIdentifier($rule->get('sourceField')));

        return $sqlPart;
    }

    protected function getConnection(): Connection
    {
        return $this->container->get('connection');
    }
}
