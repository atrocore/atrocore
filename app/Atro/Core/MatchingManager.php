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

use Atro\Core\MatchingRuleType\AbstractMatchingRule;
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

    public function createMatchingType(Entity $rule): AbstractMatchingRule
    {
        $className = "\\Atro\\Core\\MatchingRuleType\\" . ucfirst($rule->get('type'));
        if (!class_exists($className)) {
            throw new \Exception("Class $className not found");
        }

        $ruleType = $this->container->get($className);
        $ruleType->setRule($rule);

        return $ruleType;
    }

    /**
     * Returns array of matched entity IDs
     *
     * @param Entity $matching
     * @param Entity $entity
     * @return array
     */
    public function findMatches(Entity $matching, Entity $entity): array
    {
        if (empty($matching->get('matchingRules'))) {
            return [];
        }

        $masterEntityName = $matching->get('type') === 'duplicate' ? $matching->get('entity') : $matching->get('masterEntity');

        $qb = $this->getConnection()->createQueryBuilder();
        $qb
            ->select('id')
            ->from(Util::toUnderScore($masterEntityName))
            ->where('deleted=:false')
            ->setParameter('false', false, ParameterType::BOOLEAN);

        if ($matching->get('type') === 'duplicate' || $matching->get('masterEntity') === $matching->get('targetEntity')) {
            $qb
                ->andWhere('id != :id')
                ->setParameter('id', $entity->get('id'));
        }

        $rulesParts = [];
        foreach ($matching->get('matchingRules') as $rule) {
            $rulesParts[] = $this->createMatchingType($rule)->prepareMatchingSqlPart($qb, $entity);
        }

        $qb->andWhere(implode(' OR ', $rulesParts));

        $possibleMatches = $qb->executeQuery();

        $matchedIds = [];
        foreach ($possibleMatches as $row) {
            $masterEntity = $this->getEntityManager()->getRepository($masterEntityName)->get();
            $masterEntity->id = $row['id'];
            $masterEntity->set(Util::arrayKeysToCamelCase($row));

            $matchingScore = 0;
            foreach ($matching->get('matchingRules') as $rule) {
                $value = $this->createMatchingType($rule)->match($entity, $masterEntity);
                $matchingScore += $value;
            }

            if ($matchingScore >= $matching->get('minimumMatchingScore')) {
                $matchedIds[] = $row['id'];
            }
        }

        return $matchedIds;
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
