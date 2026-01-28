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

use Atro\Core\AttributeFieldConverter;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Exception;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Services\Base;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class ClusterItem extends Base
{
    protected $mandatorySelectAttributeList = ['entityName', 'entityId'];

    public function confirm(string $id): bool
    {
        $entity = $this->getEntity($id);

        if (empty($entity)) {
            throw new NotFound();
        }

        if (empty($cluster = $entity->get('cluster'))) {
            throw new Exception("Cluster is not set for item {$id}");
        }

        if ($entity->get('entityName') === $cluster->get('masterEntity')) {
            $cluster->set('goldenRecordId', $entity->get('entityId'));
            $this->getEntityManager()->saveEntity($cluster);
        } else {
            $goldenRecord = $cluster->get('goldenRecord');
            $record = $this->getEntityManager()->getEntity($entity->get('entityName'), $entity->get('entityId'));

            if (empty($record)) {
                throw new NotFound("Cluster item record not found");
            }

            if (!empty($record->get('goldenRecord'))) {
                throw new BadRequest("This record is already confirmed");
            }

            if (empty($goldenRecord)) {
                foreach ($cluster->get('clusterItems') as $clusterItem) {
                    if ($clusterItem->get('entityName') === $cluster->get('masterEntity')) {
                        // master entity record does not exist in the cluster
                        throw new BadRequest("Golden record is not set on the cluster");
                    }
                }

                // try to create a golden record
                $goldenRecord = $this->getService($cluster->get('masterEntity'))->createFromStagingRecord($record);

                $clusterItem = $this->getEntityManager()->getEntity('ClusterItem');
                $clusterItem->set('clusterId', $cluster->get('id'));
                $clusterItem->set('entityName', $cluster->get('masterEntity'));
                $clusterItem->set('entityId', $goldenRecord->get('id'));
                $this->getEntityManager()->saveEntity($clusterItem);

                $cluster->set('goldenRecordId', $goldenRecord->get('id'));
                $this->getEntityManager()->saveEntity($cluster);
            }

            $record->set('goldenRecordId', $goldenRecord->get('id'));
            $this->getEntityManager()->saveEntity($record);
        }

        return true;
    }

    public function reject(string $id): bool
    {
        $entity = $this->getEntity($id);

        if (empty($entity)) {
            throw new NotFound();
        }

        if (empty($cluster = $entity->get('cluster'))) {
            throw new Exception("Cluster is not set for item {$id}");
        }

        if (!empty($cluster->get('goldenRecord'))) {
            if ($cluster->get('masterEntity') === $entity->get('entityName')) {
                if ($cluster->get('goldenRecordId') === $entity->get('entityId')) {
                    throw new BadRequest("This record cannot be rejected");
                }
            } else {
                $record = $this->getEntityManager()->getEntity($entity->get('entityName'), $entity->get('entityId'));
                if (!empty($record) && $record->get('goldenRecordId') === $cluster->get('goldenRecordId')) {
                    throw new BadRequest("This record cannot be rejected");
                }
            }
        }

        $rci = $this->getEntityManager()->getEntity('RejectedClusterItem');
        $rci->set('clusterItemId', $entity->get('id'));
        $rci->set('clusterId', $entity->get('clusterId'));

        $this->getEntityManager()->saveEntity($rci);

        $rejectedClusterIds = array_column($entity->get('rejectedClusters')->toArray(), 'id');

        /* @var $matchedRecordRepo \Atro\Repositories\MatchedRecord */
        $matchedRecordRepo = $this->getEntityManager()->getRepository('MatchedRecord');

        $items = $matchedRecordRepo
            ->getForEntityRecord($entity->get('entityName'), $entity->get('entityId'), $entity->get('id'), $rejectedClusterIds);

        $newClusterId = null;
        foreach ($items as $item) {
            if ($item['source_entity'] === $entity->get('entityName') && $item['source_entity_id'] === $entity->get('entityId')) {
                $newClusterId = $item['master_cluster_id'];
            } else if ($item['master_entity'] === $entity->get('entityName') && $item['master_entity_id'] === $entity->get('entityId')) {
                $newClusterId = $item['source_cluster_id'];
            }

            if (!empty($newClusterId)) {
                break;
            }
        }

        if (empty($newClusterId)) {
            $newCluster = $this->getEntityManager()->getRepository('Cluster')->get();
            $newCluster->set('masterEntity', $cluster->get('masterEntity'));;

            $this->getEntityManager()->saveEntity($newCluster);
            $newClusterId = $newCluster->get('id');
        }

        $this->getRepository()->moveToCluster($entity->get('id'), $newClusterId);
        return true;
    }

    public function putAclMetaForLink(Entity $entityFrom, string $link, Entity $entity): void
    {
        if ($entityFrom->getEntityName() !== 'Cluster' || $link !== 'clusterItems') {
            parent::putAclMetaForLink($entityFrom, $link, $entity);
            return;
        }

        $this->putAclMeta($entity);

        if ($this->getUser()->isAdmin()) {
            $entity->setMetaPermission('confirm', true);
            $entity->setMetaPermission('reject', true);
            $entity->setMetaPermission('unlink', true);
            $entity->setMetaPermission('delete', true);
            return;
        }

        $entity->setMetaPermission('confirm', false);
        $entity->setMetaPermission('reject', $this->getAcl()->check($entity, 'edit'));
        $entity->setMetaPermission('unlink', $this->getAcl()->check($entity, 'delete'));
        $entity->setMetaPermission('delete', false);

        if (!empty($record = $this->getEntityManager()->getEntity($entity->get('entityName'), $entity->get('recordId')))) {
            $entity->setMetaPermission('confirm', $this->getAcl()->check($record, 'edit'));
            $entity->setMetaPermission('delete', $this->getAcl()->check($record, 'delete'));
        }
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (empty($record->_preparedInCollection)) {
            $entity->set('recordId', $entity->get('entityId'));
            $record = $this->getEntityManager()->getEntity($entity->get('entityName'), $entity->get('recordId'));
            if (!empty($record)) {
                $entity->set('recordName', $record->get('name'));
            }
        }
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        $entityIds = [];
        foreach ($collection as $key => $entity) {
            $entityIds[$entity->get('entityName')][$key] = $entity;
        }

        $loadEntity = !empty($selectParams['select']) && in_array('entity', $selectParams['select']);

        foreach ($entityIds as $entityType => $records) {
            $ids = array_map(fn($entity) => $entity->get('entityId'), $records);
            if ($loadEntity) {
                $entities = $this->getEntityManager()->getRepository($entityType)->where(['id' => $ids])->find();
                $retrievedIds = [];
                foreach ($entities as $entity) {
                    $retrievedIds[] = $entity->get('id');
                }

                foreach ($records as $key => $record) {
                    if (!in_array($record->get('entityId'), $retrievedIds) || !$this->getAcl()->check($record, 'read')) {
                        unset($collection[$key]);
                    }
                    foreach ($entities as $entity) {
                        if ($this->getMetadata()->get(['scopes', $entityType, 'hasAttribute'])) {
                            $this->getInjection(AttributeFieldConverter::class)->putAttributesToEntity($entity);
                            $this->getService($entityType)->prepareEntityForOutput($entity);
                            $this->getService($entityType)->loadAdditionalFields($entity);
                        }

                        if ($record->get('entityId') === $entity->get('id')) {
                            $record->set('recordId', $entity->get('id'));
                            $record->set('recordName', $entity->get('name') ?? $entity->get('id'));
                            $record->set('entity', $entity->toArray());
                            $record->_preparedInCollection = true;
                        }
                    }
                }
            } else {
                $select = ['id'];

                if ($this->getMetadata()->get(['entityDefs', $entityType, 'fields', 'name'])) {
                    $select[] = 'name';
                }

                $entities = $this->getEntityManager()->getRepository($entityType)->select($select)->where(['id' => $ids])->find();

                $retrievedIds = [];

                foreach ($entities as $entity) {
                    $retrievedIds[] = $entity->get('id');
                }

                foreach ($records as $key => $record) {
                    if (!in_array($record->get('entityId'), $retrievedIds) || !$this->getAcl()->check($record, 'read')) {
                        unset($collection[$key]);
                    }
                    foreach ($entities as $entity) {
                        if ($record->get('entityId') === $entity->get('id')) {
                            $record->set('recordId', $entity->get('id'));
                            $record->set('recordName', $entity->get('name') ?? $entity->get('id'));
                            $record->_preparedInCollection = true;
                        }
                    }
                }
            }
        }

        // we reset the index from 0 in case of unset
        $entities = [];
        foreach ($collection as $key => $record) {
            $entities[] = $record;
            $collection->offsetUnset($key);
        }

        foreach ($entities as $key => $entity) {
            $collection->offsetSet($key, $entity);
        }
    }

    protected function getService(string $name): Record
    {
        if (!empty($this->services[$name])) {
            return $this->services[$name];
        }
        return $this->services[$name] = $this->getServiceFactory()->create($name);
    }
}
