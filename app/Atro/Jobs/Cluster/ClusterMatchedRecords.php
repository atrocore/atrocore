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
                if (!empty($item['match_subject_cluster_id'])) {
                    $clustersIds[$item['match_subject_entity']][$item['match_subject_entity_id']] = $item['match_subject_cluster_id'];
                }
                if (!empty($item['match_candidate_cluster_id'])) {
                    $clustersIds[$item['match_candidate_entity']][$item['match_candidate_entity_id']] = $item['match_candidate_cluster_id'];
                }

                $matchSubjectClusterId = $clustersIds[$item['match_subject_entity']][$item['match_subject_entity_id']] ?? null;
                $matchCandidateClusterId = $clustersIds[$item['match_candidate_entity']][$item['match_candidate_entity_id']] ?? null;

                $clusterId = $matchCandidateClusterId ?? $matchSubjectClusterId ?? $this->createCluster($masterEntity)->id;

                $matchedRecordRepo->markHasCluster($item['id']);

                if (!empty($matchSubjectClusterId) && !empty($matchCandidateClusterId)) {
                    if ($matchSubjectClusterId !== $matchCandidateClusterId) {
                        $clusterItemRepo->moveAllToCluster($matchSubjectClusterId, $matchCandidateClusterId);
                    } else {
                        $clusterItemRepo->updateMatchedScoresInClusters([$matchSubjectClusterId]);
                    }
                    continue;
                }

                if (empty($matchSubjectClusterId)) {
                    $clustersIds[$item['match_subject_entity']][$item['match_subject_entity_id']] = $clusterId;
                    $this->createClusterItem($clusterId, $item['match_subject_entity'], $item['match_subject_entity_id'], $item['id'], $item['score']);
                } else {
                    $clusterItemRepo->updateMatchedScore($item['match_subject_entity'], $item['match_subject_entity_id'], $item['score']);
                }

                if (empty($matchCandidateClusterId)) {
                    $clustersIds[$item['match_candidate_entity']][$item['match_candidate_entity_id']] = $clusterId;
                    $this->createClusterItem($clusterId, $item['match_candidate_entity'], $item['match_candidate_entity_id'], $item['id'], $item['score']);
                } else {
                    $clusterItemRepo->updateMatchedScore($item['match_candidate_entity'], $item['match_candidate_entity_id'], $item['score']);
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
