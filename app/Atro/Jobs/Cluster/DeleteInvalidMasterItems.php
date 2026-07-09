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

class DeleteInvalidMasterItems extends AbstractClusterJob implements JobInterface
{
    public function run(Job $job): void
    {
        $masterEntity = $job->getPayload()['masterEntity'] ?? null;
        if (empty($masterEntity)) {
            return;
        }

        $masterDataEntity = $this->getEntityManager()->getEntity('MasterDataEntity', $masterEntity);
        if (empty($masterDataEntity) || empty($masterDataEntity->get('deleteInvalidMastersAutomatically'))) {
            return;
        }

        /** @var ClusterItem $clusterItemRepo */
        $clusterItemRepo = $this->getEntityManager()->getRepository('ClusterItem');

        foreach ($clusterItemRepo->getInvalidClusterMasterItemIds($masterEntity) as $itemId) {
            $item = $this->getEntityManager()->getEntity('ClusterItem', $itemId);
            if (empty($item)) {
                continue;
            }

            $record = $this->getEntityManager()->getEntity($item->get('entityName'), $item->get('entityId'));

            try {
                $this->getEntityManager()->removeEntity($item);
            } catch (\Exception $e) {
                $GLOBALS['log']->error("Failed to delete invalid master cluster item $itemId: " . $e->getMessage());
                continue;
            }

            if (!empty($record)) {
                try {
                    $this->getEntityManager()->removeEntity($record);
                } catch (\Exception $e) {
                    $GLOBALS['log']->error("Failed to delete invalid master {$record->getEntityName()} record {$record->get('id')}: " . $e->getMessage());
                }
            }
        }
    }
}
