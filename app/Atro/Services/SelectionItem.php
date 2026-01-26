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

namespace Atro\Services;

use Atro\Core\AttributeFieldConverter;
use Atro\Core\Exceptions\NotUnique;
use Atro\Core\Templates\Services\Base;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class SelectionItem extends Base
{
    protected $mandatorySelectAttributeList = ['name', 'entityId', 'entityType'];

    protected  array $services = [];

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        $entityIds = [];
        foreach ($collection as $key => $entity) {
            $entityIds[$entity->get('entityType')][$key] = $entity;
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
                    if (!in_array($record->get('entityId'), $retrievedIds)) {
                        unset($collection[$key]);
                    }
                    foreach ($entities as $entity) {
                        if ($this->getMetadata()->get(['scopes', $entityType, 'hasAttribute'])) {
                            $this->getInjection(AttributeFieldConverter::class)->putAttributesToEntity($entity);
                            $this->getService($entityType)->prepareEntityForOutput($entity);
                        }

                        if ($record->get('entityId') === $entity->get('id')) {
                            $record->set('name', $entity->get('name') ?? $entity->get('id'));
                            $record->set('entity', $entity->toArray());
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
                    if (!in_array($record->get('entityId'), $retrievedIds)) {
                        unset($collection[$key]);
                    }
                    foreach ($entities as $entity) {
                        if ($record->get('entityId') === $entity->get('id')) {
                            $record->set('name', $entity->get('name') ?? $entity->get('id'));
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

    public function createOnCurrentItem(string $entityType, string $entityId): bool
    {
        $currentSelection = $this->getUser()->get('currentSelection');

        $masterEntity = $this->getMetadata()->get(['scopes', $entityType, 'primaryEntityId']);

        if(empty($currentSelection)) {
            $currentSelection = $this->getEntityManager()->getEntity('Selection');
            $currentSelection->set('type', 'single');
            $currentSelection->set('entity', $entityType);
            if(!empty($masterEntity)) {
                $currentSelection->set('entity', $masterEntity);
            }
            $this->getEntityManager()->saveEntity($currentSelection);

            $this->getUser()->set('currentSelectionId', $currentSelection->get('id'));

            $this->getEntityManager()->saveEntity($this->getUser());
        }

        $relevantEntities = [$entityType];

        if(!empty($masterEntity)) {
            $relevantEntities[] = $masterEntity;
        }

        if($currentSelection->get('type') === 'single' && !in_array($currentSelection->get('entity'), $relevantEntities)) {
            $currentSelection->set('type', 'multiple');
            $currentSelection->set('entity', null);
            $this->getEntityManager()->saveEntity($currentSelection);
        }

        $record = $this->getEntityManager()->getEntity('SelectionItem');
        $record->set('entityId', $entityId);
        $record->set('entityType', $entityType);
        $record->set('selectionId', $currentSelection->get('id'));
        try{
            $this->getEntityManager()->saveEntity($record);
        }catch (NotUnique $e) {
        }

        return true;
    }

    protected function getService(string $name): Record
    {
        if(!empty($this->services[$name])) {
            return $this->services[$name];
        }
        return $this->services[$name] = $this->getServiceFactory()->create($name);
    }
}
