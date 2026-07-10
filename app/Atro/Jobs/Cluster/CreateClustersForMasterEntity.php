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

        // Phase 0: FindMatches must be complete
        /** @var MatchingRepository $matchingRepo */
        $matchingRepo = $this->getEntityManager()->getRepository('Matching');

        foreach ($matchingRepo->find() as $matching) {
            if (empty($matching->get('isActive'))) {
                continue;
            }

            $resolvedMaster = $this->getMetadata()->get("scopes.{$matching->get('masterEntity')}.primaryEntityId") ?? $matching->get('masterEntity');
            if ($resolvedMaster !== $masterEntity) {
                continue;
            }

            if ($matchingRepo->hasUnprocessedRecords($matching)) {
                throw new \RuntimeException("Skipped: matching '{$matching->id}' still has unprocessed records for '$masterEntity'. FindMatches must complete first.");
            }
        }

        // Phase 1: spawn first sequential matched-records job
        $this->spawnJob('ClusterMatchedRecords', ['masterEntity' => $masterEntity, 'jobNum' => 1], $job, 1);
    }
}
