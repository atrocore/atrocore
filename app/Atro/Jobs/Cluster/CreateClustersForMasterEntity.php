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

namespace Atro\Jobs\Cluster;

use Atro\Entities\Job;
use Atro\Jobs\JobInterface;
use Atro\Repositories\Matching as MatchingRepository;

class CreateClustersForMasterEntity extends AbstractClusterJob implements JobInterface
{
    private const SUB_JOB_TYPES = [
        'ClusterMatchedRecords',
        'RejectInvalidClusterItems',
        'ConfirmClustersAutomatically',
        'ConfirmSingleClusterItems',
        'CreateClustersForOrphans',
        'DeleteInvalidMasterItems',
    ];

    public function run(Job $job): void
    {
        $masterEntity = $job->getPayload()['masterEntity'] ?? null;
        if (empty($masterEntity)) {
            return;
        }

        // Guard: abort if any sub-job is already running for this master entity
        foreach (self::SUB_JOB_TYPES as $type) {
            if ($this->hasPendingSiblings($type, $masterEntity)) {
                throw new \RuntimeException("Skipped: sub-job '$type' is already running for '$masterEntity'.");
            }
        }

        /** @var MatchingRepository $matchingRepo */
        $matchingRepo = $this->getEntityManager()->getRepository('Matching');

        // Phase 0: collect matching IDs for this master entity, then wait for all FindMatches jobs to finish
        $matchingIds = [];
        foreach ($matchingRepo->find() as $matching) {
            if (empty($matching->get('isActive'))) {
                continue;
            }
            $resolvedMaster = $this->getMetadata()->get("scopes.{$matching->get('masterEntity')}.primaryEntityId") ?? $matching->get('masterEntity');
            if ($resolvedMaster === $masterEntity) {
                $matchingIds[] = $matching->id;
            }
        }

        if (!empty($matchingIds)) {
            $jobRepo = $this->getEntityManager()->getRepository('Job');
            while (true) {
                $hasPending = false;
                foreach ($matchingIds as $matchingId) {
                    $count = $jobRepo->where([
                        'type'     => ['FindMatchesForRecords', 'FindMatchesForRecord', 'FindMatchesForMatching'],
                        'status'   => ['Pending', 'Running', 'Awaiting'],
                        'payload*' => '%"id":"' . $matchingId . '"%',
                    ])->count();
                    if ($count > 0) {
                        $hasPending = true;
                        break;
                    }
                }
                if (!$hasPending) {
                    break;
                }
                sleep(5);
            }
        }

        // Phase 1: spawn first sequential matched-records job
        $this->spawnJob('ClusterMatchedRecords', ['masterEntity' => $masterEntity, 'jobNum' => 1], $job, 1);
    }
}
