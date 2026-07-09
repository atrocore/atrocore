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

class ConfirmClustersAutomatically extends AbstractClusterJob implements JobInterface
{
    public function run(Job $job): void
    {
        $masterEntity = $job->getPayload()['masterEntity'] ?? null;
        $clusters     = $job->getPayload()['clusters'] ?? [];

        if (empty($masterEntity)) {
            return;
        }

        /** @var ClusterItem $clusterItemRepo */
        $clusterItemRepo = $this->getEntityManager()->getRepository('ClusterItem');
        $clusterItemService = $this->getServiceFactory()->create('ClusterItem');

        foreach ($clusters as $clusterData) {
            $clusterItemIds = $clusterData['clusterItemIds'] ?? [];
            if (empty($clusterItemIds)) {
                continue;
            }

            $clusterItems = iterator_to_array($clusterItemRepo->findByIds($clusterItemIds));

            try {
                $clusterItemService->confirmAll($clusterItems, true);
            } catch (\Exception $e) {
                $GLOBALS['log']->error('Impossible to automatically confirm cluster ' . $clusterData['clusterId'] . ': ' . $e->getMessage());
            }
        }

        if ($this->hasPendingSiblings('ConfirmClustersAutomatically', $masterEntity)) {
            return;
        }

        if ($this->nextPhaseAlreadySpawned('ConfirmSingleClusterItems', $masterEntity)) {
            return;
        }

        $this->spawnConfirmSingleClusterItems($masterEntity, $job);
    }
}
