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

namespace Atro\Core;

use Atro\Core\MatchingRuleType\MatchingRuleTypeInterface;
use Doctrine\DBAL\Connection;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class MatchingManager
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function createMatchingType(string $type): MatchingRuleTypeInterface
    {
        $className = "\\Atro\\Core\\MatchingRuleType\\" . ucfirst($type);
        if (!class_exists($className)) {
            throw new \Exception("Class $className not found");
        }

        return $this->container->get($className);
    }

    public function findMatches(Entity $matching, Entity $entity): array
    {
        $qb = $this->getConnection()->createQueryBuilder();

        echo '<pre>';
        print_r($entity->toArray());
        die();

        // $qb->select('*')->from();



        foreach ($matching->get('matchingRules') ?? [] as $rule) {
            $ruleType = $rule->get('type');

            echo '<pre>';
            print_r('123');
            die();

            $ruleTypeInstance = $this->container->get($ruleType);
            var_dump($ruleTypeInstance);
        }

        echo '<pre>';
        print_r($matching->get('matchingRules')->toArray());
        die();

        return [];
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getConnection(): Connection
    {
        return $this->container->get('connection');
    }
}
