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
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotModified;
use Atro\Core\Exceptions\NotUnique;
use Atro\Core\ORM\Repositories\RDB;
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

            $sp = $this->getSelectParams([
                'select' => ['state', 'stagingItemCount', 'masterItemCount'],
            ]);
            $sp['whereClause']['id'] = $entity->get('id');
            $sp['noCache'] = true;

            $record = $this->getRepository()->findOne($sp);
            if (!empty($record)) {
                $entity->set('stagingItemCount', $record->get('stagingItemCount'));
                $entity->set('masterItemCount', $record->get('masterItemCount'));
                $entity->set('state', $record->get('state'));
            }
        }
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        $entityIds = [];
        foreach ($collection as $key => $entity) {
            $entityIds[$entity->get('masterEntity')][$key] = $entity;
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
                        $record->set('goldenRecordName', $entity->get($nameField) ?? $entity->get('id'));
                    }
                }
            }
        }
    }

    public function mergeItems(string $clusterId, array $sourceIds, \stdClass $attributes): Entity
    {
        $cluster = $this->getEntity($clusterId);

        if(empty($cluster)) {
            throw new BadRequest("Cluster $clusterId not found");
        }

        if (!$this->getAcl()->check($cluster->get('masterEntity'), 'create')) {
            throw new Forbidden();
        }

        /** @var RDB $masterRepository */
        $masterRepository = $this->getEntityManager()->getRepository($cluster->get('masterEntity'));
        /** @var Record $masterService */
        $masterService = $this->getRecordService($cluster->get('masterEntity'));

        $sourceList = [];

        $clusterItems = $this->getEntityManager()->getRepository('ClusterItem')
            ->where(['entityId' => $sourceIds, 'clusterId' => $clusterId])
            ->find();

        foreach ($clusterItems as $clusterItem) {
            if (in_array($clusterItem->get('entityId'), $sourceIds)) {
                $sourceList[] = $this->getRecordService($clusterItem->get('entityName'))->getEntity($clusterItem->get('entityId'));
            }
        }


        $goldenRecord = $cluster->get('goldenRecord');

        if (empty($goldenRecord)) {
            foreach ($sourceList as $source) {
                if($source->getEntityName() === $cluster->get('masterEntity')) {
                    $goldenRecord = $source;
                }
            }

            if(empty($goldenRecord)) {
                $goldenRecord = $masterService->createEntity($attributes->input);
                $cluster->set('goldenRecordId', $goldenRecord->get('id'));
                $this->getRepository()->save($cluster);
            }
        }

        $relationshipData = json_decode(json_encode($attributes->relationshipData), true);

        $linksDefs = $this->getMetadata()->get(['entityDefs', $goldenRecord->getEntityType(), 'links']);
        foreach ($linksDefs as $link => $linkDefs) {

            if ($linkDefs['type'] !== 'hasMany' || !empty($linkDefs['relationName'])) {
                continue;
            }
            $method = 'applyMergeFor' . ucfirst($link);
            if (method_exists($this, $method)) {
                $masterService->$method($goldenRecord, $sourceList);
                continue;
            }

            foreach ($sourceList as $source) {

                if (empty($source->entityDefs['links'][$link])) {
                    continue;
                }

                $linkedList = $this->getEntityManager()->getRepository($source->getEntityName())->findRelated($source, $link);


                foreach ($linkedList as $linked) {
                    try {
                        $masterRepository->relate($goldenRecord, $link, $linked);
                    } catch (NotUnique $e) {
                    }
                }
            }
        }

        $upsertData = [];

        foreach ($relationshipData as $data) {
            if (empty($data['scope'])) {
                continue;
            }
            if (!empty($data['toUpsert'])) {
                foreach ($data['toUpsert'] as $payload) {
                    $input = new \stdClass();
                    $input->entity = $data['scope'];
                    $input->payload = (object)$payload;
                    $upsertData[] = $input;
                }
            }

            if (!empty($data['toDelete'])) {
                $this->getRecordService($data['scope'])->massRemove([
                    'ids' => $data['toDelete']
                ]);
            }
        }

        $this->getRecordService('MassActions')->upsert($upsertData);

        try {
            $attributes->input->_skipCheckForConflicts = true;
            $goldenRecord = $masterService->updateEntity($goldenRecord->get('id'), $attributes->input);
        } catch (NotModified $e) {

        }

        foreach ($sourceList as $source) {
            if($source->getEntityName() !== $cluster->get('masterEntity')) {
                $source->set('masterRecordId', $goldenRecord->get('id'));
                $this->getEntityManager()->saveEntity($source);
            }
        }

        return $goldenRecord;
    }
}
