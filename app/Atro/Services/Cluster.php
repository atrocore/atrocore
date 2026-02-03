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

class Cluster extends Base
{
    protected $mandatorySelectAttributeList = ['masterEntity', 'goldenRecordId'];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (empty($entity->_fromCollection)) {
            if (!empty($entity->get('goldenRecordId'))) {
                $goldenRecord = $this->getEntityManager()->getEntity($entity->get('masterEntity'), $entity->get('goldenRecordId'));
                if (!empty($goldenRecord)) {
                    $entity->set('goldenRecordName', $goldenRecord->get('name'));
                }
            }

            $stagingItemCount = 0;
            $masterItemCount = 0;

            $clusterItems = $this->findLinkedEntities($entity->get('id'), 'clusterItems', ['select' => ['entityName'], 'collectionOnly' => true])['collection'];
            foreach ($clusterItems as $clusterItem) {
                if ($clusterItem->get('entityName') === $entity->get('masterEntity')) {
                    $masterItemCount++;
                } else {
                    $stagingItemCount++;
                }
            }

            $entity->set('stagingItemCount', $stagingItemCount);
            $entity->set('masterItemCount', $masterItemCount);
        }
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        $entityIds = [];
        $ids = [];
        foreach ($collection as $key => $entity) {
            $entityIds[$entity->get('masterEntity')][$key] = $entity;
            $ids[] = $entity->get('id');
        }

        foreach ($entityIds as $entityType => $records) {
            $ids = array_map(fn($entity) => $entity->get('goldenRecordId'), $records);

            $select = ['id'];
            $nameField = $this->getMetadata()->get(['scopes', $entityType, 'nameField']) ?? 'name';
            if ($this->getMetadata()->get(['entityDefs', $entityType, 'fields', $nameField])) {
                $select[] = $nameField;
            }

            $entities = $this->getEntityManager()->getRepository($entityType)->select($select)->where(['id' => $ids])->find();

            foreach ($records as $key => $record) {
                foreach ($entities as $entity) {
                    if ($record->get('goldenRecordId') === $entity->get('id')) {
                        $entity->set('goldenRecord', $entity);
                        $record->set('goldenRecordName', $entity->get($nameField) ?? $entity->get('id'));
                    }
                }
            }
        }

        $sp = $this->getSelectManager('ClusterItem')->getSelectParams(['where' => [
            'attribute' => 'clusterId',
            'type'      => 'in',
        ]]);
        $itemCollection = $this->getEntityManager()->getRepository('ClusterItem')->find($sp);
        $items = [];
        $itemEntityIds

        foreach ($itemCollection as $item) {
            $items[$item->get('clusterId')][] = $item;
        }

        foreach ($collection as $entity) {
            $entity->set('clusterItems', $items[$entity->get('id')] ?? []);
            $entity->set('state', $entity->getState());
        }
    }
}
