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
use Atro\Core\Templates\Services\Base;
use Espo\Core\Utils\DataUtil;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class ClusterItem extends Base
{
    protected $mandatorySelectAttributeList = ['entityName', 'entityId'];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (empty($entity->_preparedInCollection)) {
            $entity->set('recordId', $entity->get('entityId'));
            $record = $this->getEntityManager()->getEntity($entity->get('entityName'), $entity->get('recordId'));
            if (!empty($record)) {
                $entity->set('recordName', $record->get('name'));

                $entity->setMetaPermission('delete', $this->getUser()->isAdmin() ?? $this->getAcl()->check($record, 'delete'));
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

                            $record->setMetaPermission('delete', $this->getUser()->isAdmin() ?? $this->getAcl()->check($entity, 'delete'));
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

                            $record->setMetaPermission('delete', $this->getUser()->isAdmin() ?? $this->getAcl()->check($entity, 'delete'));
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
        if(!empty($this->services[$name])) {
            return $this->services[$name];
        }
        return $this->services[$name] = $this->getServiceFactory()->create($name);
    }
}
