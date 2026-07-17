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
        $entityName   = $job->getPayload()['entityName'] ?? null;
        $entitiesIds  = $job->getPayload()['entitiesIds'] ?? null;

        if (empty($entityName) || empty($entitiesIds) || empty($matchingData['code'])) {
            return;
        }

        if (empty($this->getMetadata()->get("app.matchings.{$matchingData['id']}.isActive"))) {
            return;
        }

        $collection = $this->getEntityManager()->getRepository($entityName)
            ->where([
                'id'                                              => $entitiesIds,
                Matching::prepareFieldName($matchingData['code']) => null
            ])
            ->find();

        if (empty($collection[0])) {
            return;
        }

        $matching = $this->createMatchingEntity($matchingData);
        foreach ($collection as $entity) {
            $this->getMatchingManager()->findMatches($matching, $entity);
        }

        $this->tryTriggerClusterCreation($matchingData, $job);
    }

    protected function tryTriggerClusterCreation(array $matchingData, Job $job): void
    {
        $rawMaster = $matchingData['masterEntity'] ?? null;
        if (empty($rawMaster)) {
            return;
        }
        $masterEntity = $this->getMetadata()->get("scopes.$rawMaster.primaryEntityId") ?? $rawMaster;

        // Collect all active matching IDs that resolve to the same master entity
        $matchingIds = [];
        foreach ($this->getEntityManager()->getRepository('Matching')->find() as $m) {
            if (empty($m->get('isActive'))) {
                continue;
            }
            $resolved = $this->getMetadata()->get("scopes.{$m->get('masterEntity')}.primaryEntityId") ?? $m->get('masterEntity');
            if ($resolved === $masterEntity) {
                $matchingIds[] = $m->id;
            }
        }

        // Check if any FindMatches sibling jobs are still pending/running/awaiting
        $jobRepo = $this->getEntityManager()->getRepository('Job');
        foreach ($matchingIds as $matchingId) {
            $count = $jobRepo->where([
                'type'     => ['FindMatchesForRecords', 'FindMatchesForMatching'],
                'status'   => ['Pending', 'Running', 'Awaiting'],
                'payload*' => '%"id":"' . $matchingId . '"%',
                'id!='     => $job->id,
            ])->count();
            if ($count > 0) {
                return;
            }
        }

        // Don't spawn if a cluster job is already pending/running
        $exists = $jobRepo->where([
            'type'     => 'CreateClustersForMasterEntity',
            'status'   => ['Pending', 'Running'],
            'payload*' => '%"masterEntity":"' . $masterEntity . '"%',
        ])->findOne();
        if (!empty($exists)) {
            return;
        }

        $jobEntity = $this->getEntityManager()->getEntity('Job');
        $jobEntity->set([
            'name'     => "Create Clusters for $masterEntity",
            'type'     => 'CreateClustersForMasterEntity',
            'status'   => 'Pending',
            'priority' => 30,
            'payload'  => ['masterEntity' => $masterEntity],
        ]);
        $this->getEntityManager()->saveEntity($jobEntity);
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
