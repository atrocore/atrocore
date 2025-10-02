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
use Atro\Core\Utils\Util;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
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
        if (empty($matching->get('matchingRules'))) {
            return [];
        }

        // prepare entity name
        $entityName = $matching->get('type') === 'duplicate' ? $matching->get('entity') : $matching->get('masterEntity');

        $qb = $this->getConnection()->createQueryBuilder();
        $qb
            ->select('id')
            ->from(Util::toUnderScore($entityName))
            ->where('deleted=:false')
            ->setParameter('false', false, ParameterType::BOOLEAN);

        if ($matching->get('type') === 'duplicate' || $matching->get('masterEntity') === $matching->get('targetEntity')) {
            $qb
                ->andWhere('id != :id')
                ->setParameter('id', $entity->get('id'));
        }

        $rulesParts = [];
        foreach ($matching->get('matchingRules') ?? [] as $rule) {
            $rulesParts[] = $this->createMatchingType($rule->get('type'))->prepareMatchingSqlPart($qb, $rule, $entity);
        }

        $qb->andWhere(implode(' OR ', $rulesParts));

        echo '<pre>';
        print_r($qb->fetchAllAssociative());
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
