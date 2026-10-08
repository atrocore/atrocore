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

namespace Atro\Services;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Exception;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Exceptions\NotModified;
use Atro\Core\Templates\Services\Base;
use Atro\Core\UserContext;
use Atro\DTOs\Cluster\MassActionResultDTO;
use Atro\DTOs\Cluster\MoveResultDTO;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\IEntity;

class ClusterItem extends Base
{
    protected $mandatorySelectAttributeList = ['entityName', 'entityId', 'consolidatedAutomatically'];

    public function consolidate(Entity $entity, bool $automatically = false): bool
    {
        if (empty($cluster = $entity->get('cluster'))) {
            throw new Exception("Cluster is not set for item " . $entity->get('id'));
        }

        if ($entity->get('entityName') !== $cluster->get('masterEntity')) {
            return $this->consolidateAll([$entity], $automatically);
        }

        $cluster->set('goldenRecordId', $entity->get('entityId'));
        $this->getEntityManager()->saveEntity($cluster);

        $entity->set('consolidatedAutomatically', $automatically);
        $this->getEntityManager()->saveEntity($entity);

        $this->createClusterNote($cluster->get('id'), 'consolidated', $entity->get('entityName'), $entity->get('entityId'));
        $this->runAsSystemUser(function () use ($cluster, $entity) {
            $this->createClusterNote($cluster->get('id'), 'goldenRecord', $entity->get('entityName'), $entity->get('entityId'));
        });

        return true;
    }

    public function consolidateAll(array $clusterItems, bool $automatically = false): bool
    {
        if (empty($clusterItems)) {
            return false;
        }

        if (empty($cluster = $clusterItems[0]->get('cluster'))) {
            throw new Exception("Cluster is not set for item " . $clusterItems[0]->get('id'));
        }

        $contributorItems = [];
        foreach ($clusterItems as $clusterItem) {
            if ($clusterItem->get('clusterId') !== $cluster->get('id')) {
                throw new BadRequest($this->getInjection('language')->translate('itemsMustBelongToTheSameCluster', 'exceptions', 'ClusterItem'));
            }

            if ($clusterItem->get('entityName') === $cluster->get('masterEntity')) {
                $this->consolidate($clusterItem, $automatically);
                $cluster = $clusterItem->get('cluster');
                continue;
            }

            $contributorItems[$clusterItem->get('entityId')] = $clusterItem;
        }

        if (empty($contributorItems)) {
            return true;
        }

        $goldenRecordChanged = false;
        $goldenRecord = $cluster->get('goldenRecord');

        if (empty($goldenRecord)) {
            foreach ($cluster->get('clusterItems') as $clusterItem) {
                if ($clusterItem->get('entityName') === $cluster->get('masterEntity')) {
                    if (!empty($itemRecord = $this->getEntityManager()->getEntity($clusterItem->get('entityName'), $clusterItem->get('entityId')))) {
                        $goldenRecord = $itemRecord;

                        $cluster->set('goldenRecordId', $goldenRecord->get('id'));
                        $this->getEntityManager()->saveEntity($cluster);
                        $goldenRecordChanged = true;
                        break;
                    }
                }
            }
        }

        $selectedRecords = [];
        foreach ($contributorItems as $entityId => $clusterItem) {
            $record = $this->getEntityManager()->getEntity($clusterItem->get('entityName'), $entityId);

            if (empty($record)) {
                throw new NotFound($this->getInjection('language')->translate("notFound", "exceptions", "ClusterItem"));
            }

            if (!empty($goldenRecord) && $record->get('masterRecordId') === $goldenRecord->get('id')) {
                continue;
            }

            $selectedRecords[$entityId] = $record;
        }

        if (empty($selectedRecords)) {
            return false;
        }

        $consolidationService = $this->getRecordService('Consolidation');

        $consolidation = $consolidationService->getConsolidation($cluster->get('masterEntity'));
        $candidates = $consolidationService->filterSkippedCandidates(
            $consolidation,
            new EntityCollection(array_values($selectedRecords), reset($selectedRecords)->getEntityName()),
            $goldenRecord
        );

        if (count($candidates) === 0) {
            if ($automatically) {
                return false;
            }

            throw new BadRequest($this->getInjection('language')->translate('allCandidatesSkipped', 'exceptions', 'Consolidation'));
        }

        if (empty($goldenRecord)) {
            $goldenRecord = $consolidationService->createMasterRecord($candidates);

            if (empty($goldenRecord)) {
                return false;
            }

            $masterItem = $this->getEntityManager()->getEntity('ClusterItem');
            $masterItem->set('clusterId', $cluster->get('id'));
            $masterItem->set('entityName', $cluster->get('masterEntity'));
            $masterItem->set('entityId', $goldenRecord->get('id'));
            $this->getEntityManager()->saveEntity($masterItem);

            $cluster->set('goldenRecordId', $goldenRecord->get('id'));
            $this->getEntityManager()->saveEntity($cluster);
            $goldenRecordChanged = true;
        } else {
            $consolidationService->updateMasterRecord($goldenRecord, $candidates);
        }

        foreach ($candidates as $record) {
            $record->set('masterRecordId', $goldenRecord->get('id'));
            $this->getEntityManager()->saveEntity($record, ['skipUpdateMasterRecord' => true]);

            $clusterItem = $contributorItems[$record->get('id')];
            $clusterItem->set('consolidatedAutomatically', $automatically);
            $this->getEntityManager()->saveEntity($clusterItem);

            $this->createClusterNote($cluster->get('id'), 'consolidated', $clusterItem->get('entityName'), $clusterItem->get('entityId'));
        }

        if ($goldenRecordChanged) {
            $this->runAsSystemUser(function () use ($cluster, $goldenRecord) {
                $this->createClusterNote($cluster->get('id'), 'goldenRecord', $goldenRecord->getEntityName(), $goldenRecord->get('id'));
            });
        }

        return true;
    }

    public function reject(array $params): MassActionResultDTO
    {
        $params['action']             = 'reject';
        $params['maxCountWithoutJob'] = $this->getConfig()->get('massUpdateMaxCountWithoutJob', 200);
        $params['maxChunkSize']       = $this->getConfig()->get('massUpdateMaxChunkSize', 3000);
        $params['minChunkSize']       = $this->getConfig()->get('massUpdateMinChunkSize', 400);
        $params['singleActionMethod'] = 'rejectItem';

        list($count, $errors, $sync) = $this->executeMassAction($params, function ($id) {
            try {
                $this->rejectItem($id);
            } catch (NotModified $e) {

            }
        });

        return new MassActionResultDTO($count, $sync, $errors);
    }

    public function rejectItem(\Atro\Entities\ClusterItem|string $entity, bool $persistRejection = true): bool
    {
        if (is_string($entity)) {
            /** @var \Atro\Entities\ClusterItem $entity */
            $entity = $this->getEntity($entity);
        }

        if (empty($cluster = $entity->get('cluster'))) {
            throw new Exception("Cluster is not set for item {$entity->get('id')}");
        }

        if ($this->isClusterItemConsolidated($entity)) {
            $this->runAsSystemUser(function () use ($entity, $cluster) {
                foreach ($entity->getStagingRecords() as $stagingRecord) {
                    $stagingRecord->set('masterRecordId', null);
                    $this->getEntityManager()->saveEntity($stagingRecord, ['skipUpdateMasterRecord' => true]);
                }

                if ($entity->get('entityName') === $cluster->get('masterEntity')) {
                    $cluster->set('goldenRecordId', null);
                    $this->getEntityManager()->saveEntity($cluster);
                }
            });

            if ($entity->get('entityName') !== $cluster->get('masterEntity') && !empty($goldenRecord = $cluster->get('goldenRecord'))) {
                $this->getRecordService('Consolidation')->refreshMasterRecord($goldenRecord);
            }
        }

        $entity->set('consolidatedAutomatically', false);
        $entity->set('matchedRecordId', null);

        if ($persistRejection) {
            $rci = $this->getEntityManager()->getEntity('RejectedClusterItem');
            $rci->set('clusterItemId', $entity->get('id'));
            $rci->set('clusterId', $entity->get('clusterId'));

            $this->getEntityManager()->saveEntity($rci);
        } else {
            // cancel if only one cluster item remains
            if (empty($this->getRepository()->where(['clusterId' => $cluster->get('id'), 'id!=' => $entity->get('id')])->findOne())) {
                if ($entity->isAttributeChanged('consolidatedAutomatically') || $entity->isAttributeChanged('matchedRecordId')) {
                    $this->getEntityManager()->saveEntity($entity);
                }

                return true;
            }
        }

        $rejectedClusterIds = array_column($entity->get('rejectedClusters')->toArray(), 'id');

        /* @var $matchedRecordRepo \Atro\Repositories\MatchedRecord */
        $matchedRecordRepo = $this->getEntityManager()->getRepository('MatchedRecord');

        $item = $matchedRecordRepo
            ->getForEntityRecord($entity->get('entityName'), $entity->get('entityId'), $entity->get('id'), $rejectedClusterIds);

        if (!empty($item)) {
            if ($item['source_entity'] === $entity->get('entityName') && $item['source_entity_id'] === $entity->get('entityId')) {
                $newClusterId = $item['master_cluster_id'];
            } else {
                $newClusterId = $item['source_cluster_id'];
            }

            $entity->set('matchedRecordId', $item['id']);
        }

        if ($entity->isAttributeChanged('consolidatedAutomatically') || $entity->isAttributeChanged('matchedRecordId')) {
            $this->getEntityManager()->saveEntity($entity);
        }

        $this->createClusterNote($cluster->get('id'), 'rejected', $entity->get('entityName'), $entity->get('entityId'));

        $this->runAsSystemUser(function () use ($cluster, $entity, &$newClusterId) {
            if (empty($newClusterId)) {
                $newCluster = $this->getEntityManager()->getRepository('Cluster')->get();
                $newCluster->set('masterEntity', $cluster->get('masterEntity'));
                $this->getEntityManager()->saveEntity($newCluster);
                $newClusterId = $newCluster->get('id');
            }
            $this->createClusterNote($cluster->get('id'), 'unlinked', $entity->get('entityName'), $entity->get('entityId'));
            $this->getRepository()->moveToCluster($entity->get('id'), $newClusterId);
            $this->createClusterNote($newClusterId, 'linked', $entity->get('entityName'), $entity->get('entityId'));
        });

        $this->getRepository()->updateMatchedScoresInClusters([$cluster->get('id'), $newClusterId]);
        return true;
    }

    public function deconsolidate(array $params): MassActionResultDTO
    {
        if (!empty($params['where'])) {
            $selectParams = $this->getSelectParams(['where' => $params['where'], 'maxSize' => 2000]);
            $collection   = $this->getRepository()->find($selectParams);
        } else {
            if (empty($ids = $params['ids'])) {
                throw new BadRequest("No ids provided.");
            }
            $collection = $this->getRepository()->findByIds($ids);
        }

        $entities = iterator_to_array($collection);

        if (empty($entities)) {
            return new MassActionResultDTO(0);
        }

        // All items must belong to the same cluster
        $clusterIds = array_unique(array_map(fn($e) => $e->get('clusterId'), $entities));
        if (count($clusterIds) > 1) {
            throw new BadRequest($this->getInjection('language')->translate('itemsMustBelongToTheSameCluster', 'exceptions', 'ClusterItem'));
        }

        $cluster = $entities[0]->get('cluster');
        foreach ($entities as $entity) {
            if ($entity->get('entityName') === $cluster->get('masterEntity')) {
                throw new BadRequest($this->getInjection('language')->translate('cannotDeconsolidateMasterEntityItem', 'exceptions', 'ClusterItem'));
            }
        }

        $count = 0;
        foreach ($entities as $entity) {
            if (!$this->isClusterItemConsolidated($entity)) {
                continue;
            }

            $this->runAsSystemUser(function () use ($entity) {
                $this->unlinkFromGoldenRecord($entity);
            });

            $this->createClusterNote($cluster->get('id'), 'deconsolidated', $entity->get('entityName'), $entity->get('entityId'));
            $count++;
        }

        $goldenRecord = $cluster->get('goldenRecord');
        if ($count > 0 && !empty($goldenRecord)) {
            $this->getRecordService('Consolidation')->refreshMasterRecord($goldenRecord);
        }

        return new MassActionResultDTO($count);
    }

    public function split(array $params): MassActionResultDTO
    {
        if (!empty($params['where'])) {
            $selectParams = $this->getSelectParams(['where' => $params['where'], 'maxSize' => 2000]);
            $collection   = $this->getRepository()->find($selectParams);
        } else {
            if (empty($ids = $params['ids'])) {
                throw new BadRequest("No ids provided.");
            }
            $collection = $this->getRepository()->findByIds($ids);
        }

        $entities = iterator_to_array($collection);

        if (empty($entities)) {
            return new MassActionResultDTO(0);
        }

        $clusterIds = array_unique(array_map(fn($e) => $e->get('clusterId'), $entities));
        if (count($clusterIds) > 1) {
            throw new BadRequest($this->getInjection('language')->translate('itemsMustBelongToTheSameCluster', 'exceptions', 'ClusterItem'));
        }

        $cluster = $entities[0]->get('cluster');
        foreach ($entities as $entity) {
            if ($entity->get('entityName') === $cluster->get('masterEntity')) {
                throw new BadRequest($this->getInjection('language')->translate('cannotSplitMasterEntityItem', 'exceptions', 'ClusterItem'));
            }
        }

        $this->deconsolidate(['ids' => array_map(fn($e) => $e->get('id'), $entities)]);

        $newCluster = null;
        $this->runAsSystemUser(function () use (&$newCluster, $cluster) {
            $newCluster = $this->getEntityManager()->getRepository('Cluster')->get();
            $newCluster->set('masterEntity', $cluster->get('masterEntity'));
            $this->getEntityManager()->saveEntity($newCluster);
        });

        foreach ($entities as $entity) {
            $this->createClusterNote($cluster->get('id'), 'moved', $entity->get('entityName'), $entity->get('entityId'));
            $this->getRepository()->moveToCluster($entity->get('id'), $newCluster->get('id'));
            $this->runAsSystemUser(function () use ($newCluster, $entity) {
                $this->createClusterNote($newCluster->get('id'), 'linked', $entity->get('entityName'), $entity->get('entityId'));
            });
        }

        $this->getRepository()->updateMatchedScoresInClusters([$cluster->get('id'), $newCluster->get('id')]);

        return new MassActionResultDTO(count($entities));
    }

    public function unreject(string $clusterItemId, string $rejectedClusterItemId): bool
    {
        $clusterItem         = $this->getEntity($clusterItemId);
        $rejectedClusterItem = $this->getEntityManager()->getEntity('RejectedClusterItem', $rejectedClusterItemId);

        if (empty($clusterItem) || empty($rejectedClusterItem)) {
            throw new NotFound();
        }

        $cluster = $rejectedClusterItem->get('cluster');
        if (empty($cluster)) {
            throw new NotFound("Cluster not found");
        }

        if ($this->isClusterItemConsolidated($clusterItem)) {
            foreach ($clusterItem->getStagingRecords() as $stagingRecord) {
                $stagingRecord->set('masterRecordId', null);
                $this->getEntityManager()->saveEntity($stagingRecord, ['skipUpdateMasterRecord' => true]);
            }

            $previousCluster = $clusterItem->get('cluster');
            if ($clusterItem->get('entityName') === $previousCluster->get('masterEntity')) {
                $previousCluster->set('goldenRecordId', null);
                $this->getEntityManager()->saveEntity($previousCluster);
            } elseif (!empty($goldenRecord = $previousCluster->get('goldenRecord'))) {
                $this->getRecordService('Consolidation')->refreshMasterRecord($goldenRecord);
            }
        }

        if (!empty($clusterItem->get('consolidatedAutomatically'))) {
            $clusterItem->set('consolidatedAutomatically', false);
            $this->getEntityManager()->saveEntity($clusterItem);
        }

        $this->getRepository()->moveToCluster($clusterItem->get('id'), $cluster->get('id'));

        $this->createClusterNote($cluster->get('id'), 'reincluded', $clusterItem->get('entityName'), $clusterItem->get('entityId'));

        $this->getRepository()->updateMatchedScoresInClusters([$clusterItem->get('clusterId'), $cluster->get('id')]);

        $this->getEntityManager()->removeEntity($rejectedClusterItem);

        return true;
    }

    public function isClusterItemConsolidated(IEntity $clusterItem): bool
    {
        if (empty($cluster = $clusterItem->get('cluster'))) {
            return false;
        }

        if (!empty($cluster->get('goldenRecord'))) {
            if ($cluster->get('masterEntity') === $clusterItem->get('entityName')) {
                if ($cluster->get('goldenRecordId') === $clusterItem->get('entityId')) {
                    return true;
                }
            } else {
                $record = $this->getEntityManager()->getEntity($clusterItem->get('entityName'), $clusterItem->get('entityId'));
                if (!empty($record) && $record->get('masterRecordId') === $cluster->get('goldenRecordId')) {
                    return true;
                }
            }
        }

        return false;
    }

    public function unlinkFromGoldenRecord(\Atro\Entities\ClusterItem $clusterItem): void
    {
        foreach ($clusterItem->getStagingRecords() as $stagingRecord) {
            $stagingRecord->set('masterRecordId', null);
            if ($stagingRecord->isAttributeChanged('masterRecordId')) {
                $this->getEntityManager()->saveEntity($stagingRecord, ['skipUpdateMasterRecord' => true]);
            }
        }

        if (!empty($clusterItem->get('consolidatedAutomatically'))) {
            $clusterItem->set('consolidatedAutomatically', false);
            $this->getEntityManager()->saveEntity($clusterItem);
        }
    }

    public function putMetaForLink(Entity $entityFrom, string $link, Entity $entity): void
    {
        parent::putMetaForLink($entityFrom, $link, $entity);

        if ($entityFrom->getEntityName() === 'Cluster' && $link === 'clusterItems') {
            $entity->set('cluster', $entityFrom);

            $entity->setMeta('cluster', 'consolidated', $this->isClusterItemConsolidated($entity));
            $entity->setMeta('cluster', 'golden', !empty($entityFrom->get('goldenRecordId')) && $entity->get('entityId') === $entityFrom->get('goldenRecordId'));
        }
    }

    public function putAclMeta(Entity $entity): void
    {
        parent::putAclMeta($entity);

        $isConsolidated = $this->isClusterItemConsolidated($entity);
        $isStaging   = !empty($this->getMetadata()->get(['scopes', $entity->get('entityName'), 'primaryEntityId']));
        $record      = $this->getEntityManager()->hasRepository($entity->get('entityName')) ?
            $this->getEntityManager()->getEntity($entity->get('entityName'), $entity->get('entityId')) :
            null;


        if ($this->getUser()->isAdmin()) {
            $entity->setMetaPermission('consolidate', !$isConsolidated);
            $entity->setMetaPermission('reject', true);
            $entity->setMetaPermission('deconsolidate', $isStaging);
            $entity->setMetaPermission('split', $isStaging);
            $entity->setMetaPermission('move', true);
            $entity->setMetaPermission('delete', true);
            if (empty($record)) {
                $entity->setMetaPermission('unlink', true);
                $entity->setMetaPermission('delete', false);
            }
            return;
        }


        $entity->setMetaPermission('consolidate', false);
        $entity->setMetaPermission('reject', $this->getAcl()->check($entity, 'edit'));
        $entity->setMetaPermission('deconsolidate', $isStaging && $this->getAcl()->check($entity, 'edit'));
        $entity->setMetaPermission('split', $isStaging && $this->getAcl()->check($entity, 'edit'));
        $entity->setMetaPermission('move', $this->getAcl()->check($entity, 'edit'));
        $entity->setMetaPermission('delete', false);

        if (!empty($record)) {
            $entity->setMetaPermission('consolidate', !$isConsolidated && $this->getAcl()->check($record, 'edit'));
            $entity->setMetaPermission('delete', $this->getAcl()->check($record, 'delete'));
        } else {
            $entity->setMetaPermission('unlink', $this->getAcl()->check($entity, 'delete'));
        }
    }

    public function putAclMetaForLink(Entity $entityFrom, string $link, Entity $entity): void
    {
        if ($entityFrom->getEntityName() !== 'Cluster' || !in_array($link, ['clusterItems', 'rejectedClusterItems'])) {
            parent::putAclMetaForLink($entityFrom, $link, $entity);
            return;
        }

        $this->putAclMeta($entity);

        if ($link === 'rejectedClusterItems') {
            if ($this->getUser()->isAdmin()) {
                $entity->setMetaPermission('unreject', true);
                $entity->setMetaPermission('unlink', true);
                return;
            }

            if (!empty($entity->relationEntity)) {
                $entity->setMetaPermission('unlink', $this->getAcl()->check($entity->relationEntity, 'delete'));
            }

            if (!empty($record = $this->getEntityManager()->getEntity($entity->get('entityName'), $entity->get('recordId')))) {
                $entity->setMetaPermission('unreject', $this->getAcl()->check($record, 'edit'));
            }
            return;
        }

        $entity->set('cluster', $entityFrom);
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);
        $this->getRecordService('SelectionItem')->prepareEntityRecord($entity);
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        $this->getRecordService('SelectionItem')->prepareCollectionRecords($collection, $selectParams);
    }

    public function move(array $params): MoveResultDTO
    {
        if (!$this->getAcl()->check('ClusterItem', 'edit')) {
            throw new Forbidden();
        }

        $targetClusterId = $params['targetClusterId'] ?? null;
        if (empty($targetClusterId)) {
            throw new BadRequest("targetClusterId is required.");
        }

        if (!empty($params['where'])) {
            $selectParams = $this->getSelectParams(['where' => $params['where'], 'maxSize' => 2000]);
            $collection   = $this->getRepository()->find($selectParams);
        } else {
            if (empty($ids = $params['ids'])) {
                throw new BadRequest("No ids provided.");
            }
            $collection = $this->getRepository()->findByIds($ids);
        }

        $moved   = 0;
        $skipped = 0;

        foreach ($collection as $entity) {
            $this->moveItem($entity, $targetClusterId) ? $moved++ : $skipped++;
        }

        return new MoveResultDTO($moved, $skipped);
    }

    public function moveItem(\Atro\Entities\ClusterItem $clusterItem, string $targetClusterId): bool
    {
        if (!$this->getAcl()->checkEntity($clusterItem, 'edit')) {
            throw new Forbidden();
        }

        $targetCluster = $this->getEntityManager()->getEntity('Cluster', $targetClusterId);
        if (empty($targetCluster)) {
            throw new NotFound("Target cluster not found.");
        }

        if ($clusterItem->get('clusterId') === $targetClusterId) {
            return false;
        }

        if ($clusterItem->get('entityName') !== $targetCluster->get('masterEntity')
            && $this->getMetadata()->get(['scopes', $clusterItem->get('entityName'), 'primaryEntityId']) !== $targetCluster->get('masterEntity')) {
            return false;
        }

        if ($this->getRepository()->isRejectedInCluster($clusterItem->get('id'), $targetClusterId)) {
            return false;
        }

        $sourceClusterId = $clusterItem->get('clusterId');

        if ($this->isClusterItemConsolidated($clusterItem)) {
            $this->runAsSystemUser(function () use ($clusterItem) {
                $this->unlinkFromGoldenRecord($clusterItem);
            });

            $sourceCluster = $clusterItem->get('cluster');
            if ($clusterItem->get('entityName') !== $sourceCluster->get('masterEntity') && !empty($goldenRecord = $sourceCluster->get('goldenRecord'))) {
                $this->getRecordService('Consolidation')->refreshMasterRecord($goldenRecord);
            }
        }

        $this->getRepository()->moveToCluster($clusterItem->get('id'), $targetClusterId);
        $this->getRepository()->createMoveNotes($sourceClusterId, $targetClusterId, [
            ['entity_name' => $clusterItem->get('entityName'), 'entity_id' => $clusterItem->get('entityId')],
        ]);

        $this->getRepository()->updateMatchedScoresInClusters([$sourceClusterId, $targetClusterId]);

        return true;
    }

    private function createClusterNote(string $clusterId, string $action, string $relatedType = '', string $relatedId = ''): void
    {
        $this->getEntityManager()->getRepository('ClusterItem')->createClusterActivityNote(
            $clusterId, $action, $relatedType, $relatedId
        );
    }

    private function runAsSystemUser(callable $callback): void
    {
        $user       = $this->getUser();
        $systemUser = $user->getSystemUser();
        $this->getEntityManager()->setUser($systemUser);
        $this->getEntityManager()->getContainer()->get(UserContext::class)->set($systemUser);
        try {
            $callback();
        } finally {
            $this->getEntityManager()->setUser($user);
            $this->getEntityManager()->getContainer()->get(UserContext::class)->set($user);
        }
    }
}
