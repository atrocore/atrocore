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

use Espo\Core\Interfaces\Injectable;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityFactory;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository;

class ReferenceData extends Repository implements Injectable
{
    protected array $dependencies = [];
    protected array $injections = [];

    public function __construct($entityType, EntityManager $entityManager, EntityFactory $entityFactory)
    {
        parent::__construct($entityType, $entityManager, $entityFactory);

        $this->init();
    }

    protected function init()
    {
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

    public function get($id = null)
    {
        if (empty($id)) {
            return $this->getNewEntity();
        }

        return $this->getEntityById($id);
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
        return null;
//
//        $entity = $this->entityFactory->create($this->entityType);
//
//        if (!$entity) {
//            return null;
//        }
//
//        $params = [];
//        $this->handleSelectParams($params);
//
//        if (!$this->cacheable) {
//            return $this->getMapper()->selectById($entity, $id, $params);
//        }
//
//        $key = $this->getCacheKey($id);
//        if (!$this->getMemoryStorage()->has($key)) {
//            $this->putToCache($id, $this->getMapper()->selectById($entity, $id, $params));
//        }
//
//        return $this->getMemoryStorage()->get($key);
    }

    public function save(Entity $entity)
    {
        echo '<pre>';
        print_r('save');
        die();
    }

    public function remove(Entity $entity)
    {
        echo '<pre>';
        print_r('remove');
        die();
    }

    public function find(array $params)
    {
//        $params = $this->getSelectParams($params);
//
//        if (empty($params['skipAdditionalSelectParams'])) {
//            $this->handleSelectParams($params);
//        }
//
//        $dataArr = !empty($this->seed) ? $this->getMapper()->select($this->seed, $params) : [];

        $dataArr = [];

        $collection = new EntityCollection($dataArr, $this->entityName, $this->entityFactory);
        $collection->setAsFetched();

        return $collection;
    }

    public function findOne(array $params)
    {
        echo '<pre>';
        print_r('findOne');
        die();
    }

    public function getAll()
    {
        echo '<pre>';
        print_r('getAll');
        die();
    }

    public function count(array $params)
    {
        echo '<pre>';
        print_r('count');
        die();
    }
}
