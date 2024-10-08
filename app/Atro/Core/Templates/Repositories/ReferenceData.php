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

namespace Atro\Core\Templates\Repositories;

use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotUnique;
use Atro\Core\Utils\Util;
use Espo\Core\Interfaces\Injectable;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\Repository;
use Espo\ORM\EntityFactory;
use Espo\ORM\EntityManager;

class ReferenceData extends Repository implements Injectable
{
    protected array $dependencies = [];
    protected array $injections = [];

    public function __construct($entityType, EntityManager $entityManager, EntityFactory $entityFactory)
    {
        parent::__construct($entityType, $entityManager, $entityFactory);

        $this->init();
    }

    public function get($id = null)
    {
        if (empty($id)) {
            return $this->getNewEntity();
        }

        return $this->getEntityById($id);
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        $this->validateUnique($entity);

        $this->dispatch('beforeSave', $entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        $this->dispatch('afterSave', $entity, $options);
    }

    public function validateUnique(Entity $entity): void
    {
        $items = $this->getConfig()->get($this->entityName, []);

        $uniques = [];
        foreach ($this->getMetadata()->get(['entityDefs', $this->entityName, 'fields']) as $field => $fieldDefs) {
            if (!empty($fieldDefs['unique'])) {
                $uniques[] = $field;
            }
        }

        foreach ($items as $item) {
            foreach ($uniques as $unique) {
                if ($item['id'] !== $entity->get('id') && $item[$unique] === $entity->get($unique)) {
                    throw new NotUnique('The record cannot be created due to database constraints.');
                }
            }
        }
    }

    public function insertEntity(Entity $entity): bool
    {
        $items = $this->getConfig()->get($this->entityName, []);
        $items[$entity->get('code')] = array_diff($entity->toArray(), ['deleted' => false]);

        $this->getConfig()->set($this->entityName, $items);
        $this->getConfig()->save();

        return true;
    }

    public function updateEntity(Entity $entity): bool
    {
        $items = $this->getConfig()->get($this->entityName, []);
        foreach ($items as &$item) {
            if ($item['id'] === $entity->get('id')) {
                $item = array_diff($entity->toArray(), ['deleted' => false]);
            }
        }
        unset($item);

        $this->getConfig()->set($this->entityName, $items);
        $this->getConfig()->save();

        return true;
    }

    public function deleteEntity(Entity $entity): bool
    {
        $items = $this->getConfig()->get($this->entityName, []);

        $newItems = [];
        foreach ($items as $item) {
            if ($item['id'] !== $entity->get('id')) {
                $newItems[$item['code']] = $item;
            }
        }

        $this->getConfig()->set($this->entityName, $newItems);
        $this->getConfig()->save();

        return true;
    }

    public function save(Entity $entity, array $options = [])
    {
        $nowString = date('Y-m-d H:i:s');
        $user = $this->getEntityManager()->getUser();

        if ($entity->isNew()) {
            if (!$entity->has('id')) {
                $entity->set('id', Util::generateId());
            }

            if ($entity->hasAttribute('createdAt')) {
                $entity->set('createdAt', $nowString);
            }
            if ($entity->hasAttribute('createdById') && $user) {
                $entity->set('createdById', $user->get('id'));
            }
        }

        if ($entity->hasAttribute('modifiedAt')) {
            $entity->set('modifiedAt', $nowString);
        }

        if ($entity->hasAttribute('modifiedById') && $user) {
            $entity->set('modifiedById', $user->get('id'));
        }

        $entity->setAsBeingSaved();

        if (empty($options['skipBeforeSave']) && empty($options['skipAll'])) {
            $this->beforeSave($entity, $options);
        }

        if ($entity->isNew() && !$entity->isSaved()) {
            $result = $this->insertEntity($entity);
        } else {
            $result = $this->updateEntity($entity);
        }

        if ($result) {
            $entity->setIsSaved(true);

            if (empty($options['skipAfterSave']) && empty($options['skipAll'])) {
                $this->afterSave($entity, $options);
            }
            if ($entity->isNew()) {
                if (empty($options['keepNew'])) {
                    $entity->setIsNew(false);
                }
            } else {
                if ($entity->isFetched()) {
                    $entity->updateFetchedValues();
                }
            }
        }
        $entity->setAsNotBeingSaved();

        return $result;
    }

    protected function getNewEntity()
    {
        $entity = $this->entityFactory->create($this->entityName);
        $entity->setIsNew(true);
        $entity->populateDefaults();

        return $entity;
    }

    protected function getEntityById($id)
    {
        $items = $this->getConfig()->get($this->entityName, []);
        foreach ($items as $item) {
            if ($item['id'] === $id) {
                $entity = $this->entityFactory->create($this->entityName);
                $entity->set($item);

                return $entity;
            }
        }

        return null;
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        $this->dispatch('beforeRemove', $entity, $options);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        $this->dispatch('afterRemove', $entity, $options);
    }

    public function remove(Entity $entity, array $options = [])
    {
        $this->beforeRemove($entity, $options);
        $result = $this->deleteEntity($entity);
        if ($result) {
            $this->afterRemove($entity, $options);
        }

        return $result;
    }

    public function find(array $params)
    {
        $items = $this->getConfig()->get($this->entityName, []);

        $collection = new EntityCollection(array_values($items), $this->entityName, $this->entityFactory);
        $collection->setAsFetched();

        return $collection;
    }

    public function findOne(array $params)
    {
        throw new BadRequest('The function is not provided for an entity of this type.');
    }

    public function getAll()
    {
        return $this->find([]);
    }

    public function count(array $params)
    {
        return count($this->getConfig()->get($this->entityName, []));
    }

    protected function init()
    {
        $this->addDependency('config');
        $this->addDependency('metadata');
        $this->addDependency('eventManager');
    }

    public function getDependencyList()
    {
        return $this->dependencies;
    }

    public function inject($name, $object): void
    {
        $this->injections[$name] = $object;
    }

    protected function addDependency(string $name): void
    {
        $this->dependencies[] = $name;
    }

    protected function getInjection($name)
    {
        return $this->injections[$name];
    }

    protected function dispatch(string $action, Entity $entity, $options, $arg1 = null, $arg2 = null, $arg3 = null)
    {
        $event = new Event(
            [
                'entityType'     => $this->entityName,
                'entity'         => $entity,
                'options'        => $options,
                'relationName'   => $arg1,
                'relationParams' => $arg2,
                'relationData'   => $arg2,
                'foreign'        => $arg3,
            ]
        );

        // dispatch an event
        $this->getInjection('eventManager')->dispatch('Entity', $action, $event);
    }

    protected function getConfig(): Config
    {
        return $this->getInjection('config');
    }

    protected function getMetadata(): Metadata
    {
        return $this->getInjection('metadata');
    }
}
