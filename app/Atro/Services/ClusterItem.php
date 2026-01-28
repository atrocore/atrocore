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
use Atro\Core\Exceptions\Exception;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Services\Base;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class ClusterItem extends Base
{
    protected $mandatorySelectAttributeList = ['entityName', 'entityId'];

    public function reject(string $id): bool
    {
        $entity = $this->getEntity($id);

        if (empty($entity)) {
            throw new NotFound();
        }

        if (empty($cluster = $entity->get('cluster'))) {
            throw new Exception("Cluster is not set for item {$id}");
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
            $entity->setMetaPermission('reject', true);
            $entity->setMetaPermission('unlink', true);
            $entity->setMetaPermission('delete', true);
            return;
        }

        $entity->setMetaPermission('reject', $this->getAcl()->check($entity, 'edit'));
        $entity->setMetaPermission('unlink', $this->getAcl()->check($entity, 'delete'));
        $entity->setMetaPermission('delete', false);

        if (!empty($record = $this->getEntityManager()->getEntity($entity->get('entityName'), $entity->get('recordId')))) {
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
