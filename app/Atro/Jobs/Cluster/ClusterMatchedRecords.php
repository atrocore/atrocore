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
use Atro\Repositories\ClusterItem;
use Atro\Repositories\MatchedRecord;

class ClusterMatchedRecords extends AbstractClusterJob implements JobInterface
{
    private const QUERY_LIMIT = 20000;
    private const MAX_PER_JOB = 100000;

    public function run(Job $job): void
    {
        $masterEntity = $job->getPayload()['masterEntity'] ?? null;
        if (empty($masterEntity)) {
            return;
        }

        $jobNum = (int)($job->getPayload()['jobNum'] ?? 1);

        /** @var MatchedRecord $matchedRecordRepo */
        $matchedRecordRepo = $this->getEntityManager()->getRepository('MatchedRecord');

        /** @var ClusterItem $clusterItemRepo */
        $clusterItemRepo = $this->getEntityManager()->getRepository('ClusterItem');

        $processed = 0;
        $hasMore   = false;

        while ($processed < self::MAX_PER_JOB) {
            $items = $matchedRecordRepo->getForMasterEntity($masterEntity, self::QUERY_LIMIT);
            if (empty($items)) {
                $hasMore = false;
                break;
            }

            $clustersIds = [];
            foreach ($items as $item) {
                if (!empty($item['original_cluster_id'])) {
                    $clustersIds[$item['original_entity']][$item['original_entity_id']] = $item['original_cluster_id'];
                }
                if (!empty($item['matched_cluster_id'])) {
                    $clustersIds[$item['matched_entity']][$item['matched_entity_id']] = $item['matched_cluster_id'];
                }

                $originalClusterId = $clustersIds[$item['original_entity']][$item['original_entity_id']] ?? null;
                $matchedClusterId = $clustersIds[$item['matched_entity']][$item['matched_entity_id']] ?? null;

                $clusterId = $matchedClusterId ?? $originalClusterId ?? $this->createCluster($masterEntity)->id;

                $matchedRecordRepo->markHasCluster($item['id']);

                if (!empty($originalClusterId) && !empty($matchedClusterId)) {
                    if ($originalClusterId !== $matchedClusterId) {
                        $clusterItemRepo->moveAllToCluster($originalClusterId, $matchedClusterId);
                    } else {
                        $clusterItemRepo->updateMatchedScoresInClusters([$originalClusterId]);
                    }
                    continue;
                }

                if (empty($originalClusterId)) {
                    $clustersIds[$item['original_entity']][$item['original_entity_id']] = $clusterId;
                    $this->createClusterItem($clusterId, $item['original_entity'], $item['original_entity_id'], $item['id'], $item['score']);
                } else {
                    $clusterItemRepo->updateMatchedScore($item['original_entity'], $item['original_entity_id'], $item['score']);
                }

                if (empty($matchedClusterId)) {
                    $clustersIds[$item['matched_entity']][$item['matched_entity_id']] = $clusterId;
                    $this->createClusterItem($clusterId, $item['matched_entity'], $item['matched_entity_id'], $item['id'], $item['score']);
                } else {
                    $clusterItemRepo->updateMatchedScore($item['matched_entity'], $item['matched_entity_id'], $item['score']);
                }
            }

            $processed += count($items);

            if (count($items) < self::QUERY_LIMIT) {
                $hasMore = false;
                break;
            }

            $hasMore = true;
        }

        if ($hasMore) {
            $nextNum = $jobNum + 1;
            $this->spawnJob('ClusterMatchedRecords', ['masterEntity' => $masterEntity, 'jobNum' => $nextNum], $job, $nextNum);
            return;
        }

        $this->spawnRejectInvalidClusterItems($masterEntity, $job);
    }
}
