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
use Espo\ORM\IEntity;

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
                throw new NotFound($this->getInjection('language')->translate("notFound", "exceptions", "ClusterItem"));
            }

            $confirmedClusterItems = [$entity];

            if (empty($goldenRecord)) {
                foreach ($cluster->get('clusterItems') as $clusterItem) {
                    if ($clusterItem->get('entityName') === $cluster->get('masterEntity')) {
                        if (!empty($itemRecord = $this->getEntityManager()->getEntity($clusterItem->get('entityName'), $clusterItem->get('entityId')))) {
                            $goldenRecord = $itemRecord;

                            $cluster->set('goldenRecordId', $goldenRecord->get('id'));
                            $this->getEntityManager()->saveEntity($cluster);
                            break;
                        }
                    }
                }
            } else {
                foreach ($cluster->get('clusterItems') as $clusterItem) {
                    if ($clusterItem->get('id') !== $entity->get('id') && $clusterItem->get('entityName') !== $cluster->get('masterEntity')) {
                        if (!empty($itemRecord = $this->getEntityManager()->getEntity($clusterItem->get('entityName'), $clusterItem->get('entityId')))) {
                            if ($itemRecord->get('goldenRecordId') === $goldenRecord->get('id')) {
                                $confirmedClusterItems[] = $itemRecord;
                            }
                        }
                    }
                }
            }


            $res = $this->createOrUpdateGoldenRecord($record, $cluster, $confirmedClusterItems, $goldenRecord);


            if (empty($goldenRecord)) {
                $goldenRecord = $res;

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

        if ($this->isClusterItemConfirmed($entity)) {
            throw new BadRequest($this->getInjection('language')->translate("cannotReject", "exceptions", "ClusterItem"));
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

    public function unreject(string $clusterItemId, string $rejectedClusterItemId): bool
    {
        $clusterItem = $this->getEntity($clusterItemId);
        $rejectedClusterItem = $this->getEntityManager()->getEntity('RejectedClusterItem', $rejectedClusterItemId);

        if (empty($clusterItem) || empty($rejectedClusterItem)) {
            throw new NotFound();
        }

        $cluster = $rejectedClusterItem->get('cluster');
        if (empty($cluster)) {
            throw new NotFound("Cluster not found");
        }

        if ($this->isClusterItemConfirmed($clusterItem)) {
            throw new BadRequest($this->getInjection('language')->translate("cannotUnreject", "exceptions", "ClusterItem"));
        }

        $this->getRepository()->moveToCluster($clusterItem->get('id'), $cluster->get('id'));

        $this->getEntityManager()->removeEntity($rejectedClusterItem);

        return true;
    }


    public function isClusterItemConfirmed(IEntity $clusterItem): bool
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
                if (!empty($record) && $record->get('goldenRecordId') === $cluster->get('goldenRecordId')) {
                    return true;
                }
            }
        }

        return false;
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
                return;
            }

            if (!empty($record = $this->getEntityManager()->getEntity($entity->get('entityName'), $entity->get('recordId')))) {
                $entity->setMetaPermission('unreject', $this->getAcl()->check($record, 'edit'));
            }
            return;
        }

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
        $this->getRecordService('SelectionItem')->prepareEntityRecord($entity);;
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        $this->getRecordService('SelectionItem')->prepareCollectionRecords($collection, $selectParams);
    }
}
