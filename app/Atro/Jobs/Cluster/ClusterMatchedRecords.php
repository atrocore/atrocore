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
                if (!empty($item['source_cluster_id'])) {
                    $clustersIds[$item['source_entity']][$item['source_entity_id']] = $item['source_cluster_id'];
                }
                if (!empty($item['master_cluster_id'])) {
                    $clustersIds[$item['master_entity']][$item['master_entity_id']] = $item['master_cluster_id'];
                }

                $sourceClusterId = $clustersIds[$item['source_entity']][$item['source_entity_id']] ?? null;
                $masterClusterId = $clustersIds[$item['master_entity']][$item['master_entity_id']] ?? null;

                $clusterId = $masterClusterId ?? $sourceClusterId ?? $this->createCluster($masterEntity)->id;

                $matchedRecordRepo->markHasCluster($item['id']);

                if (!empty($sourceClusterId) && !empty($masterClusterId)) {
                    if ($sourceClusterId !== $masterClusterId) {
                        $clusterItemRepo->moveAllToCluster($sourceClusterId, $masterClusterId);
                    } else {
                        $clusterItemRepo->updateMatchedScoresInClusters([$sourceClusterId]);
                    }
                    continue;
                }

                if (empty($sourceClusterId)) {
                    $clustersIds[$item['source_entity']][$item['source_entity_id']] = $clusterId;
                    $this->createClusterItem($clusterId, $item['source_entity'], $item['source_entity_id'], $item['id'], $item['score']);
                } else {
                    $clusterItemRepo->updateMatchedScore($item['source_entity'], $item['source_entity_id'], $item['score']);
                }

                if (empty($masterClusterId)) {
                    $clustersIds[$item['master_entity']][$item['master_entity_id']] = $clusterId;
                    $this->createClusterItem($clusterId, $item['master_entity'], $item['master_entity_id'], $item['id'], $item['score']);
                } else {
                    $clusterItemRepo->updateMatchedScore($item['master_entity'], $item['master_entity_id'], $item['score']);
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
