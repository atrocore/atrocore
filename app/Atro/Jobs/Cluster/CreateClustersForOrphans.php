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

class CreateClustersForOrphans extends AbstractClusterJob implements JobInterface
{
    public function run(Job $job): void
    {
        $masterEntity  = $job->getPayload()['masterEntity'] ?? null;
        $recordIds     = $job->getPayload()['recordIds'] ?? [];

        if (empty($masterEntity)) {
            return;
        }

        $contributorEntity = $this->getContributorEntityName($masterEntity);
        if (empty($contributorEntity)) {
            return;
        }

        $clusterItemService = $this->getServiceFactory()->create('ClusterItem');

        foreach ($recordIds as $recordId) {
            $cluster     = $this->createCluster($masterEntity);
            $clusterItem = $this->createClusterItem($cluster->get('id'), $contributorEntity, $recordId);

            $clusterItem->set('cluster', $cluster);
            $cluster->set('clusterItems', [$clusterItem]);

            try {
                $clusterItemService->confirm($clusterItem, true);
            } catch (\Exception $e) {
                $GLOBALS['log']->error('Impossible to automatically confirm cluster ' . $cluster->get('id') . ': ' . $e->getMessage());
            }
        }

        if ($this->hasPendingSiblings('CreateClustersForOrphans', $masterEntity, $job)) {
            return;
        }

        if (!$this->nextPhaseAlreadySpawned('DeleteInvalidMasterItems', $masterEntity)) {
            $this->spawnDeleteInvalidMasterItems($masterEntity, $job);
        }
    }
}
