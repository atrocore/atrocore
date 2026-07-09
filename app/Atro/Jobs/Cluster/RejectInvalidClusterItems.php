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

class RejectInvalidClusterItems extends AbstractClusterJob implements JobInterface
{
    public function run(Job $job): void
    {
        $masterEntity = $job->getPayload()['masterEntity'] ?? null;
        $items        = $job->getPayload()['items'] ?? [];

        if (empty($masterEntity)) {
            return;
        }

        /** @var MatchedRecord $matchedRecordRepo */
        $matchedRecordRepo = $this->getEntityManager()->getRepository('MatchedRecord');

        /** @var ClusterItem $clusterItemRepo */
        $clusterItemRepo = $this->getEntityManager()->getRepository('ClusterItem');

        $clusterItemService = $this->getServiceFactory()->create('ClusterItem');

        foreach ($items as $itemData) {
            $clusterItem = $clusterItemRepo->get($itemData['id']);
            if (empty($clusterItem)) {
                continue;
            }

            if (!empty($itemData['other_matched_record_id'])) {
                $matchedRecordRepo->markHasCluster($itemData['other_matched_record_id']);
                $clusterItem->set('matchedRecordId', $itemData['other_matched_record_id']);
                $clusterItemRepo->save($clusterItem);
                $clusterItemRepo->updateMatchedScoresInClusters([$clusterItem->get('clusterId')]);
                continue;
            }

            try {
                $clusterItemService->rejectItem($clusterItem, false);
            } catch (\Exception $e) {
                $GLOBALS['log']->error('Impossible to automatically reject cluster item ' . $clusterItem->get('id') . ': ' . $e->getMessage());
            }
        }

        if ($this->hasPendingSiblings('RejectInvalidClusterItems', $masterEntity)) {
            return;
        }

        if ($this->nextPhaseAlreadySpawned('ConfirmClustersAutomatically', $masterEntity)) {
            return;
        }

        $this->spawnConfirmClustersAutomatically($masterEntity, $job);
    }
}
