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

declare(strict_types=1);

namespace Atro\Jobs;

use Atro\Core\MatchingManager;
use Atro\Entities\Job;
use Atro\Repositories\Matching;

class FindMatchesForRecords extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $matchingData = $job->getPayload()['matching'] ?? [];
        $entityName = $job->getPayload()['entityName'] ?? null;
        $entitiesIds = $job->getPayload()['entitiesIds'] ?? null;

        if (empty($entityName) || empty($entitiesIds) || empty($matchingData['id'])) {
            return;
        }

        if (empty($this->getConfig()->get("matchings.{$matchingData['id']}"))) {
            return;
        }

        $collection = $this->getEntityManager()->getRepository($entityName)
            ->where([
                'id'                                            => $entitiesIds,
                Matching::prepareFieldName($matchingData['id']) => null
            ])
            ->find();

        if (empty($collection[0])) {
            return;
        }

        $matching = $this->createMatchingEntity($matchingData);
        foreach ($collection as $entity) {
            $this->getMatchingManager()->findMatches($matching, $entity);
        }
    }

    protected function createMatchingEntity(array $matchingData): \Atro\Entities\Matching
    {
        $matchingRules = $this->getEntityManager()->createCollection('MatchingRule', []);
        foreach ($matchingData['rules'] ?? [] as $ruleData) {
            $matchingRule = $this->getEntityManager()->getEntity('MatchingRule');
            $matchingRule->set($ruleData);
            $matchingRules->append($matchingRule);
        }

        $matching = $this->getEntityManager()->getEntity('Matching');
        $matching->set($matchingData);
        $matching->set('matchingRules', $matchingRules);

        return $matching;
    }

    protected function getMatchingManager(): MatchingManager
    {
        return $this->getContainer()->get('matchingManager');
    }
}
