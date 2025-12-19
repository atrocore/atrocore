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
use Atro\Core\Utils\Config;
use Atro\Core\Utils\Metadata;
use Atro\Core\Utils\Util;
use Atro\Entities\Matching as MatchingEntity;
use Atro\Entities\MatchingRule;
use Atro\Repositories\MatchedRecord;
use Atro\Repositories\Matching;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
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
                'name'   => 'Find matches immediately',
                'type'   => 'FindMatches',
                'status' => [
                    'Pending',
                    'Running',
                ],
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
                'matchingId' => $matching->id,
            ],
        ]);

        $this->getEntityManager()->saveEntity($jobEntity);
    }

    public function collectAllMatchingFields(?EntityCollection $matchingRules, string $fieldName, array &$fields): void
    {
        foreach ($matchingRules ?? [] as $rule) {
            if (!empty($rule->get($fieldName)) && !in_array($rule->get($fieldName), $fields)) {
                $fields[] = $rule->get($fieldName);
            }
            $this->collectAllMatchingFields($rule->get('matchingRules'), $fieldName, $fields);
        }
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
            if (empty($matching->get('isActive'))) {
                continue;
            }

            if ($matching->get('masterEntity') === $entity->getEntityName()) {
                $fields = [];
                $this->collectAllMatchingFields($matching->get('matchingRules'), 'masterField', $fields);
                foreach ($fields as $field) {
                    if ($entity->isAttributeChanged($field)) {
                        $this->getMatchingRepository()->unmarkAllMatchingSearched($matching);
                        break;
                    }
                }
            } elseif ($matching->get('sourceEntity') === $entity->getEntityName()) {
                $fields = [];
                $this->collectAllMatchingFields($matching->get('matchingRules'), 'sourceField', $fields);
                foreach ($fields as $field) {
                    if ($entity->isAttributeChanged($field)) {
                        $this->getMatchingRepository()->unmarkMatchingSearchedForEntity($matching, $entity);
                        break;
                    }
                }
            }
        }
    }

    public function findMatches(MatchingEntity $matching, Entity $entity): void
    {
        if (empty($this->getConfig()->get("matchings.{$matching->id}"))) {
            return;
        }

        $matchingRules = $matching->get('matchingRules') ?? [];
        if (empty($matchingRules[0])) {
            return;
        }

        // Clear old matches for entity
        $this->getMatchedRecordRepository()->deleteMatchedRecordsForEntity($matching, $entity);

        // Find possible matches
        $possibleMatches = $this->getMatchingRepository()->findPossibleMatchesForEntity($matching, $entity);

        // Find actual matches
        foreach ($possibleMatches as $row) {
            $maxMatchingScore = 0;
            $matchingScore = 0;
            foreach ($matchingRules as $rule) {
                $maxMatchingScore += $rule->get('weight');
                $matchingScore += $rule->match($entity, Util::arrayKeysToCamelCase($row));
            }

            $row['percentageScore'] = $maxMatchingScore > 0 ? $matchingScore / $maxMatchingScore * 100 : 0;

            if ($row['percentageScore'] >= $matching->get('minimumScore')) {
                $matchedRecordsRows[] = $row;
            }
        }

        if (!empty($matchedRecordsRows[$matching->get('matchedRecordsMax')])) {
            $matching->deactivate();
            return;
        }

        foreach ($matchedRecordsRows as $row) {
            $this
                ->getMatchedRecordRepository()
                ->createMatchedRecord($matching, $entity->id, $row['id'], $row['percentageScore']);
        }

        $this->getMatchingRepository()->markMatchingSearched($matching, $entity->getEntityName(), $entity->id);
        foreach ($possibleMatches as $row) {
            if ($matching->get('type') === 'duplicate') {
                $this->getMatchingRepository()->markMatchingSearched($matching, $entity->getEntityName(), $row['id']);
            }
        }
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
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
