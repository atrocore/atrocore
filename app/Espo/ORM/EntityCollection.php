<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

namespace Espo\ORM;

class EntityCollection implements \Iterator, \Countable, \ArrayAccess, \SeekableIterator
{
    private $entityFactory = null;

    private $entityName;

    private $position = 0;

    protected $isFetched = false;

    protected $container = array();

    public function __construct($data = array(), $entityName = null, EntityFactory $entityFactory = null)
    {
        $this->container = $data;
        $this->entityName = $entityName;
        $this->entityFactory = $entityFactory;
    }

    public function rewind()
    {
        $this->position = 0;

        while (!$this->valid() && $this->position <= $this->getLastValidKey()) {
            $this->position ++;
        }
    }

    public function current()
    {
        return $this->getEntityByOffset($this->position);
    }

    public function key()
    {
        return $this->position;
    }

    public function next()
    {
        do {
            $this->position ++;
            $next = false;
            if (!$this->valid() && $this->position <= $this->getLastValidKey()) {
                $next = true;
            }
        } while ($next);
    }

    private function getLastValidKey()
    {
        $keys = array_keys($this->container);
        $i = end($keys);
        while ($i > 0) {
            if (isset($this->container[$i])) {
                break;
            }
            $i--;
        }
        return $i;
    }

    public function valid()
    {
        return isset($this->container[$this->position]);
    }

    public function offsetExists($offset)
    {
        return isset($this->container[$offset]);
    }

    public function offsetGet($offset)
    {
        if (!isset($this->container[$offset])) {
            return null;
        }
        return $this->getEntityByOffset($offset);
    }

    public function offsetSet($offset, $value)
    {
        if (!($value instanceof Entity)) {
            throw new \InvalidArgumentException('Only Entity is allowed to be added to EntityCollection.');
        }

        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    public function offsetUnset($offset)
    {
        unset($this->container[$offset]);
    }

    public function count()
    {
        return count($this->container);
    }

    public function seek($offset)
    {
        $this->position = $offset;
        if (!$this->valid()) {
            throw new \OutOfBoundsException("Invalid seek offset ($offset).");
        }
    }

    public function append(Entity $entity)
    {
        $this->container[] = $entity;
    }

    private function getEntityByOffset($offset)
    {
        $value = $this->container[$offset];

        if ($value instanceof Entity) {
            return $value;
        } else if (is_array($value)) {
            $this->container[$offset] = $this->buildEntityFromArray($value);
        } else {
            return null;
        }

        return $this->container[$offset];
    }

    protected function buildEntityFromArray(array $dataArray)
    {
        $entity = $this->entityFactory->create($this->entityName);
        if ($entity) {
            $entity->rowData = $dataArray;
            $entity->set($dataArray);
            if ($this->isFetched) {
                $entity->setAsFetched();
            }
        }

        return $entity;
    }

    public function getEntityName()
    {
        return $this->entityName;
    }

    public function getInnerContainer()
    {
        return $this->container;
    }

    public function merge(EntityCollection $collection)
    {
        $newData = $this->container;
        $incomingData = $collection->getInnerContainer();

        foreach ($incomingData as $v) {
            if (!$this->contains($v)) {
                $this->container[] = $v;
            }
        }
    }

    public function contains($value)
    {
        if ($this->indexOf($value) !== false) {
            return true;
        }
        return false;
    }

    public function indexOf($value)
    {
        $index = 0;
        if (is_array($value)) {
            foreach ($this->container as $v) {
                if (is_array($v)) {
                    if ($value['id'] == $v['id']) {
                        return $index;
                    }
                } else if ($v instanceof Entity) {
                    if ($value['id'] == $v->id) {
                        return $index;
                    }
                }
                $index ++;
            }
        } else if ($value instanceof Entity) {
            foreach ($this->container as $v) {
                if (is_array($v)) {
                    if ($value->id == $v['id']) {
                        return $index;
                    }
                } else if ($v instanceof Entity) {
                    if ($value === $v) {
                        return $index;
                    }
                }
                $index ++;
            }
        }
        return false;
    }

    public function toArray($itemsAsObjects = false)
    {
        $arr = [];
        foreach ($this as $entity) {
            if ($itemsAsObjects) {
                $item = $entity->getValueMap();
            } else {
                $item = $entity->toArray();
            }
            $arr[] = $item;
        }
        return $arr;
    }

    public function getValueMapList()
    {
        return $this->toArray(true);
    }

    public function setAsFetched()
    {
        $this->isFetched = true;
    }

    public function setAsNotFetched()
    {
        $this->isFetched = false;
    }

    public function isFetched()
    {
        return $this->isFetched;
    }
}
