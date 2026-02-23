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

namespace Atro\Jobs;

use Atro\Entities\Cluster;
use Atro\Entities\Job;
use Atro\Repositories\ClusterItem;
use Atro\Repositories\MatchedRecord;
use Espo\ORM\Entity;

class CreateClustersForMasterEntity extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $masterEntity = $job->getPayload()['masterEntity'] ?? null;
        if (empty($masterEntity)) {
            return;
        }

        /** @var MatchedRecord $matchedRecordRepo */
        $matchedRecordRepo = $this->getEntityManager()->getRepository('MatchedRecord');

        /** @var ClusterItem $clusterItemRepo */
        $clusterItemRepo = $this->getEntityManager()->getRepository('ClusterItem');

        while (!empty($items = $matchedRecordRepo->getForMasterEntity($masterEntity, 20000))) {
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
        }


        $entitiesNames = [];
        foreach ($this->getMetadata()->get("scopes") ?? [] as $scope => $scopeDefs) {
            if (!empty($scopeDefs['primaryEntityId']) && $scopeDefs['primaryEntityId'] === $masterEntity && !empty($scopeDefs['matchMasterRecords'])) {
                $entitiesNames[] = $scope;
            }
        }

        $clusterItemService = $this->getServiceFactory()->create('ClusterItem');
        foreach ($entitiesNames as $entityName) {
            // Confirm automatically cluster items
            $offset = 0;
            $limit = 10000;

            while (!empty($clustersData = $clusterItemRepo->getClustersToConfirmAutomatically($entityName, $offset, $limit))) {
                $offset += $limit;

                foreach ($clustersData as $item) {
                    $clusterItemIds = explode(',', $item['cluster_item_ids']);
                    if (empty($clusterItemIds)) {
                        continue;
                    }

                    $clusterItems = iterator_to_array($clusterItemRepo->findByIds($clusterItemIds));

                    try {
                        $clusterItemService->confirmAll($clusterItems, true);
                    } catch (\Exception $e) {
                        $GLOBALS['log']->error("Impossible to automatically confirm cluster " . $item['cluster_id'] . " : " . $e->getMessage());
                    }
                }
            }

            // Confirm clusters for rest records that failed confirmation on the previous run
            $offset = 0;
            $limit = 2000;

            while (count($clusterItems = $clusterItemRepo->getSingleClusterItemsToConfirmAutomatically($entityName, $offset, $limit)) > 0) {
                $offset += $limit;

                foreach ($clusterItems as $clusterItem) {
                    try {
                        $clusterItemService->confirm($clusterItem, true);
                    } catch (\Exception $e) {
                        $GLOBALS['log']->error("Impossible to automatically confirm cluster " . $clusterItem->get('clusterId') . " : " . $e->getMessage());
                    }
                }
            }


            // create clusters for the rest of the records
            while (!empty($recordsIds = $clusterItemRepo->getRecordsWithNoClusterItems($entityName, 20000))) {
                foreach ($recordsIds as $recordId) {
                    $cluster = $this->createCluster($masterEntity);
                    $clusterItem = $this->createClusterItem($cluster->get('id'), $entityName, $recordId);

                    $clusterItem->set('cluster', $cluster);
                    $cluster->set('clusterItems', [$clusterItem]);

                    try {
                        $clusterItemService->confirm($clusterItem, true);
                    } catch (\Exception $e) {
                        $GLOBALS['log']->error("Impossible to automatically confirm cluster " . $cluster->get('id') . " : " . $e->getMessage());
                    }
                }
            }
        }
    }

    protected function createClusterItem(string $clusterId, string $entityName, string $entityId, ?string $matchedRecordId = null, ?int $score = null): Entity
    {
        $clusterItem = $this->getEntityManager()->getRepository('ClusterItem')->get();
        $clusterItem->set('clusterId', $clusterId);
        $clusterItem->set('entityName', $entityName);
        $clusterItem->set('entityId', $entityId);
        if (!empty($matchedRecordId)) {
            $clusterItem->set('matchedRecordId', $matchedRecordId);
        }
        if (!empty($score)) {
            $clusterItem->set('matchedScore', $score);
        }

        $this->getEntityManager()->saveEntity($clusterItem);
        return $clusterItem;
    }

    protected function createCluster(string $masterEntity): Cluster
    {
        $cluster = $this->getEntityManager()->getRepository('Cluster')->get();
        $cluster->set('masterEntity', $masterEntity);

        $this->getEntityManager()->saveEntity($cluster);

        return $cluster;
    }
}
