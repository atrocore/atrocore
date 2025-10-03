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

    public function findMatches(Entity $matching, Entity $entity): void
    {
        if (empty($matching->get('matchingRules'))) {
            return;
        }

        // Clear old matches
        $this->getConnection()->createQueryBuilder()
            ->delete('matched_record')
            ->where('matching_id = :matchingId')
            ->setParameter('matchingId', $matching->id)
            ->executeQuery();

        // Find possible matches
        $qb = $this->getConnection()->createQueryBuilder();
        $qb
            ->select('id')
            ->from(Util::toUnderScore($matching->get('masterEntity')))
            ->where('deleted=:false')
            ->setParameter('false', false, ParameterType::BOOLEAN);

        if ($matching->get('masterEntity') === $matching->get('stagingEntity')) {
            $qb
                ->andWhere('id != :id')
                ->setParameter('id', $entity->get('id'));
        }
        $rulesParts = [];
        foreach ($matching->get('matchingRules') as $rule) {
            $rulesParts[] = $this->createMatchingType($rule)->prepareMatchingSqlPart($qb, $entity);
        }
        $qb->andWhere(implode(' OR ', $rulesParts));
        $possibleMatches = $qb->fetchAllAssociative();

        // Find actual matches
        $matches = [];
        foreach ($possibleMatches as $row) {
            $matchingScore = 0;
            foreach ($matching->get('matchingRules') as $rule) {
                $value = $this->createMatchingType($rule)->match($entity, Util::arrayKeysToCamelCase($row));
                $matchingScore += $value;
            }

            if ($matchingScore >= $matching->get('minimumMatchingScore')) {
                // $matches[] = $row;

                // Save match
                $this->getConnection()->createQueryBuilder()
                    ->insert('matched_record')
                    ->setValue('id', ':id')
                    ->setValue('matching_id', ':matchingId')
                    ->setValue('staging_entity', ':stagingEntity')
                    ->setValue('staging_entity_id', ':stagingEntityId')
                    ->setValue('master_entity', ':masterEntity')
                    ->setValue('master_entity_id', ':masterEntityId')
                    ->setValue('score', ':score')
                    ->setParameter('id', Util::generateId())
                    ->setParameter('matchingId', $matching->id)
                    ->setParameter('stagingEntity', $matching->get('stagingEntity'))
                    ->setParameter('stagingEntityId', $entity->id)
                    ->setParameter('masterEntity', $matching->get('masterEntity'))
                    ->setParameter('masterEntityId', $row['id'])
                    ->setParameter('score', $matchingScore)
                    ->executeQuery();
            }
        }

        echo '<pre>';
        print_r('123');
        die();

        // echo '<pre>';
        // print_r($matches);
        // die();
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
