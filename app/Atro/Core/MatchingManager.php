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
use Atro\Core\Utils\Metadata;
use Atro\Core\Utils\Util;
use Atro\Entities\Matching as MatchingEntity;
use Atro\Entities\MatchingRule;
use Atro\Repositories\MatchedRecord;
use Atro\Repositories\Matching;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class MatchingManager
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function createMatchingType(MatchingRule $rule): AbstractMatchingRule
    {
        $className = "\\Atro\\Core\\MatchingRuleType\\" . ucfirst($rule->get('type'));
        if (!class_exists($className)) {
            throw new \Exception("Class $className not found");
        }

        $ruleType = $this->container->get($className);
        $ruleType->setRule($rule);

        return $ruleType;
    }

    public function createFindMatchesJob(MatchingEntity $matching): void
    {
        $exists = $this->getEntityManager()->getRepository('Job')
            ->where([
                'name' => 'Find matches immediately',
                'type' => 'FindMatches',
                'status' => [
                    'Pending',
                    'Running'
                ]
            ])
            ->findOne();

        if (!empty($exists)) {
            return;
        }

        $jobEntity = $this->getEntityManager()->getEntity('Job');
        $jobEntity->set([
            'name'     => "Find matches immediately",
            'type'     => 'FindMatches',
            'status'   => 'Pending',
            'priority' => 200,
            'payload'  => [
                'matchingId' => $matching->id
            ]
        ]);

        $this->getEntityManager()->saveEntity($jobEntity);
    }

    public function findMatchingsAfterEntitySave(Entity $entity): void
    {
        if ($this->getMetadata()->get("scopes.{$entity->getEntityName()}.matchingDisabled")) {
            return;
        }

        if (!in_array($this->getMetadata()->get("scopes.{$entity->getEntityName()}.type"), ['Base', 'Hierarchy'])) {
            return;
        }

        foreach ($this->getEntityManager()->getRepository('Matching')->find() as $matching) {
            if ($matching->get('isActive') && ($matching->get('stagingEntity') === $entity->getEntityName() || $matching->get('masterEntity') === $entity->getEntityName())) {
                $this->getMatchingRepository()->unmarkAllMatchingSearched($matching);
            }
        }
    }

    public function findMatches(Entity $matching, Entity $entity): void
    {
        if (empty($matching->get('isActive'))) {
            return;
        }

        if (empty($matching->get('matchingRules'))) {
            return;
        }

        // Clear old matches for entity
        $this->getMatchingRepository()->deleteMatchedRecordsForEntity($matching, $entity);

        // Find possible matches
        $possibleMatches = $this->getMatchingRepository()->findPossibleMatchesForEntity($matching, $entity);

        // Find actual matches
        foreach ($possibleMatches as $row) {
            $matchingScore = 0;
            foreach ($matching->get('matchingRules') as $rule) {
                $matchingScore += $rule->match($entity, Util::arrayKeysToCamelCase($row));
            }

            if ($matchingScore >= $matching->get('minimumScore')) {
                $this
                    ->getMatchedRecordRepository()
                    ->createMatchedRecord($matching, $entity->id, $row['id'], $matchingScore);
            }

            if ($matching->get('type') === 'duplicate') {
                $this->getMatchingRepository()->markMatchingSearched($matching, $entity->getEntityName(), $row['id']);
            }
        }

        $this->getMatchingRepository()->markMatchingSearched($matching, $entity->getEntityName(), $entity->id);
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    protected function getMatchingRepository(): Matching
    {
        return $this->getEntityManager()->getRepository('Matching');
    }

    protected function getMatchedRecordRepository(): MatchedRecord
    {
        return $this->getEntityManager()->getRepository('MatchedRecord');
    }
}
