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

class ConfirmSingleClusterItems extends AbstractClusterJob implements JobInterface
{
    public function run(Job $job): void
    {
        $masterEntity   = $job->getPayload()['masterEntity'] ?? null;
        $clusterItemIds = $job->getPayload()['clusterItemIds'] ?? [];

        if (empty($masterEntity)) {
            return;
        }

        /** @var ClusterItem $clusterItemRepo */
        $clusterItemRepo = $this->getEntityManager()->getRepository('ClusterItem');
        $clusterItemService = $this->getServiceFactory()->create('ClusterItem');

        foreach ($clusterItemIds as $id) {
            $clusterItem = $clusterItemRepo->get($id);
            if (empty($clusterItem)) {
                continue;
            }

            try {
                $clusterItemService->confirm($clusterItem, true);
            } catch (\Exception $e) {
                $GLOBALS['log']->error('Impossible to automatically confirm cluster ' . $clusterItem->get('clusterId') . ': ' . $e->getMessage());
            }
        }

        if ($this->hasPendingSiblings('ConfirmSingleClusterItems', $masterEntity, $job)) {
            return;
        }

        if ($this->nextPhaseAlreadySpawned('CreateClustersForOrphans', $masterEntity)) {
            return;
        }

        $this->spawnCreateClustersForOrphans($masterEntity, $job);
    }
}
