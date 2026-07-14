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
        $type = $rule->get('type');
        $className = $this->getMetadata()->get(['app', 'matchingRuleTypes', $type, 'className']);
        if (!$className || !class_exists($className)) {
            throw new \Exception("MatchingRule type '$type' is not registered");
        }

        $ruleType = $this->container->get($className);
        $ruleType->setRule($rule);

        return $ruleType;
    }

    public function collectAllMatchingFields(?EntityCollection $matchingRules, array &$fields, string $entityName): void
    {
        foreach ($matchingRules ?? [] as $rule) {
            if (!empty($rule->get('field'))) {
                $field     = $rule->get('field');
                $fieldType = $this->getMetadata()->get("entityDefs.{$entityName}.fields.{$field}.type");

                if ($fieldType === 'link') {
                    $field .= 'Id';
                } elseif ($fieldType === 'linkMultiple') {
                    $field .= 'Ids';
                }

                if (!in_array($field, $fields)) {
                    $fields[] = $field;
                }
            }
            $this->collectAllMatchingFields($rule->get('matchingRules'), $fields, $entityName);
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
                $this->collectAllMatchingFields($matching->get('matchingRules'), $fields, $matching->get('masterEntity'));
                foreach ($fields as $field) {
                    if ($entity->isAttributeChanged($field)) {
                        $this->getMatchingRepository()->unmarkAllMatchingSearched($matching);
                        break;
                    }
                }
            } elseif ($matching->get('entity') === $entity->getEntityName()) {
                $fields = [];
                $this->collectAllMatchingFields($matching->get('matchingRules'), $fields, $matching->get('entity'));
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
        if (empty($this->getMetadata()->get("app.matchings.{$matching->id}.isActive"))) {
            return;
        }

        $matchingRules = $matching->get('matchingRules') ?? [];
        if (empty($matchingRules[0])) {
            return;
        }

        // Find possible matches
        $possibleMatches = $this->getMatchingRepository()->findPossibleMatchesForEntity($matching, $entity);

        $matchedRecordsRows = [];

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

        $matchedAt = date('Y-m-d H:i:s');

        foreach ($matchedRecordsRows as $row) {
            $this
                ->getMatchedRecordRepository()
                ->createMatchedRecord($matching, $entity->id, $row['id'], $row['percentageScore'], $matchedAt);
        }

        $this->getMatchingRepository()->markMatchingSearched($matching, $entity->getEntityName(), $entity->id, $matchedAt);

        foreach ($matchedRecordsRows as $row) {
            if ($matching->get('type') === 'duplicate') {
                $this->getMatchingRepository()->markMatchingSearched($matching, $entity->getEntityName(), $row['id'], $matchedAt, true);
            }
        }

        $this
            ->getMatchedRecordRepository()
            ->removeOldMatches($matching, $matchedAt, $entity->getEntityName(), $entity->id);
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
