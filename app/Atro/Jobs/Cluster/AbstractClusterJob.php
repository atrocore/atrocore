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

use Atro\Entities\Cluster;
use Atro\Entities\Job;
use Atro\Jobs\AbstractJob;
use Espo\ORM\Entity;

abstract class AbstractClusterJob extends AbstractJob
{
    private const JOB_NAMES = [
        'ClusterMatchedRecords'        => 'Group matched records into clusters',
        'RejectInvalidClusterItems'    => 'Reject invalid cluster items',
        'ConfirmClustersAutomatically' => 'Auto-confirm clusters',
        'ConfirmSingleClusterItems'    => 'Confirm single-item clusters',
        'CreateClustersForOrphans'     => 'Create clusters for unmatched records',
        'DeleteInvalidMasterItems'     => 'Delete invalid master records',
    ];

    protected function spawnJob(string $type, array $payload, Job $parent, int $num = 1): void
    {
        $masterEntity = $payload['masterEntity'] ?? '';
        $baseName     = self::JOB_NAMES[$type] ?? $type;
        $job          = $this->getEntityManager()->getEntity('Job');
        $job->set([
            'name'        => $baseName . ($masterEntity ? " [$masterEntity]" : '') . ' #' . $num,
            'type'        => $type,
            'status'      => 'Pending',
            'priority'    => $parent->get('priority'),
            'ownerUserId' => $parent->get('ownerUserId'),
            'executeTime' => (new \DateTime())->format('Y-m-d H:i:s'),
            'payload'     => $payload,
        ]);
        $this->getEntityManager()->saveEntity($job);
    }

    protected function hasPendingSiblings(string $type, string $masterEntity, ?Job $currentJob = null): bool
    {
        $where = [
            'type'     => $type,
            'status'   => ['Pending', 'Running'],
            'payload*' => '%"masterEntity":"' . $masterEntity . '"%',
        ];

        if (!empty($currentJob)) {
            $where['id!='] = $currentJob->id;
        }

        return $this->getEntityManager()->getRepository('Job')
                ->where($where)
                ->count() > 0;
    }

    protected function nextPhaseAlreadySpawned(string $type, string $masterEntity): bool
    {
        return $this->getEntityManager()->getRepository('Job')
                ->where([
                    'type'     => $type,
                    'status'   => ['Pending', 'Running', 'Awaiting'],
                    'payload*' => '%"masterEntity":"' . $masterEntity . '"%',
                ])
                ->count() > 0;
    }

    protected function getStagingEntityName(string $masterEntity): ?string
    {
        foreach ($this->getMetadata()->get('scopes') ?? [] as $scope => $scopeDefs) {
            if (!empty($scopeDefs['primaryEntityId'])
                && $scopeDefs['primaryEntityId'] === $masterEntity
                && ($scopeDefs['role'] ?? null) === 'staging') {
                return $scope;
            }
        }
        return null;
    }

    protected function createCluster(string $masterEntity): Cluster
    {
        $cluster = $this->getEntityManager()->getRepository('Cluster')->get();
        $cluster->set('masterEntity', $masterEntity);
        $this->getEntityManager()->saveEntity($cluster);
        return $cluster;
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

    protected function spawnRejectInvalidClusterItems(string $masterEntity, Job $parent): void
    {
        /** @var \Atro\Repositories\ClusterItem $clusterItemRepo */
        $clusterItemRepo = $this->getEntityManager()->getRepository('ClusterItem');

        $offset   = 0;
        $limit    = 1000;
        $batchNum = 0;

        while (!empty($page = $clusterItemRepo->getInvalidClusterItemDataPage($offset, $limit))) {
            $batchNum++;
            $this->spawnJob('RejectInvalidClusterItems', [
                'masterEntity' => $masterEntity,
                'items'        => $page,
            ], $parent, $batchNum);
            $offset += $limit;
            if (count($page) < $limit) {
                break;
            }
        }

        if ($batchNum === 0) {
            $this->spawnConfirmClustersAutomatically($masterEntity, $parent);
        }
    }

    protected function spawnConfirmClustersAutomatically(string $masterEntity, Job $parent): void
    {
        $stagingEntity = $this->getStagingEntityName($masterEntity);
        if (empty($stagingEntity)) {
            $this->spawnConfirmSingleClusterItems($masterEntity, $parent);
            return;
        }

        /** @var \Atro\Repositories\ClusterItem $clusterItemRepo */
        $clusterItemRepo = $this->getEntityManager()->getRepository('ClusterItem');

        $offset   = 0;
        $limit    = 1000;
        $batchNum = 0;

        while (!empty($page = $clusterItemRepo->getClustersToConfirmAutomatically($stagingEntity, $offset, $limit))) {
            $batchNum++;
            $clusters = array_map(fn($row) => [
                'clusterId'      => $row['cluster_id'],
                'clusterItemIds' => explode(',', $row['cluster_item_ids']),
            ], $page);

            $this->spawnJob('ConfirmClustersAutomatically', [
                'masterEntity' => $masterEntity,
                'clusters'     => $clusters,
            ], $parent, $batchNum);
            $offset += $limit;
            if (count($page) < $limit) {
                break;
            }
        }

        if ($batchNum === 0) {
            $this->spawnConfirmSingleClusterItems($masterEntity, $parent);
        }
    }

    protected function spawnConfirmSingleClusterItems(string $masterEntity, Job $parent): void
    {
        $stagingEntity = $this->getStagingEntityName($masterEntity);
        if (empty($stagingEntity)) {
            $this->spawnCreateClustersForOrphans($masterEntity, $parent);
            return;
        }

        /** @var \Atro\Repositories\ClusterItem $clusterItemRepo */
        $clusterItemRepo = $this->getEntityManager()->getRepository('ClusterItem');

        $offset   = 0;
        $limit    = 1000;
        $batchNum = 0;

        while (!empty($page = $clusterItemRepo->getSingleClusterItemIdsPage($stagingEntity, $offset, $limit))) {
            $batchNum++;
            $this->spawnJob('ConfirmSingleClusterItems', [
                'masterEntity'   => $masterEntity,
                'clusterItemIds' => $page,
            ], $parent, $batchNum);
            $offset += $limit;
            if (count($page) < $limit) {
                break;
            }
        }

        if ($batchNum === 0) {
            $this->spawnCreateClustersForOrphans($masterEntity, $parent);
        }
    }

    protected function spawnCreateClustersForOrphans(string $masterEntity, Job $parent): void
    {
        $stagingEntity = $this->getStagingEntityName($masterEntity);
        if (empty($stagingEntity)) {
            $this->spawnDeleteInvalidMasterItems($masterEntity, $parent);
            return;
        }

        /** @var \Atro\Repositories\ClusterItem $clusterItemRepo */
        $clusterItemRepo = $this->getEntityManager()->getRepository('ClusterItem');

        $offset   = 0;
        $limit    = 1000;
        $batchNum = 0;

        while (!empty($page = $clusterItemRepo->getRecordsWithNoClusterItems($stagingEntity, $limit, $offset))) {
            $batchNum++;
            $this->spawnJob('CreateClustersForOrphans', [
                'masterEntity' => $masterEntity,
                'recordIds'    => $page,
            ], $parent, $batchNum);
            $offset += $limit;
            if (count($page) < $limit) {
                break;
            }
        }

        if ($batchNum === 0) {
            $this->spawnDeleteInvalidMasterItems($masterEntity, $parent);
        }
    }

    protected function spawnDeleteInvalidMasterItems(string $masterEntity, Job $parent): void
    {
        $this->spawnJob('DeleteInvalidMasterItems', ['masterEntity' => $masterEntity], $parent);
    }
}
