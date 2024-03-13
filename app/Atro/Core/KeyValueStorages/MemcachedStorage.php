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

namespace Atro\Core\KeyValueStorages;

use Atro\Core\Container;
use Espo\ORM\Entity;

class MemcachedStorage implements StorageInterface
{
    private \Memcached $memcached;

    private string $keysName = '_memcached_keys';

    private Container $container;

    public function __construct(Container $container, string $host, int $port)
    {
        $this->container = $container;

        $this->memcached = new \Memcached();
        $this->memcached->addServer($host, $port);
    }

    public function set(string $key, $value, int $expiration = 0): void
    {
        if ($value instanceof Entity) {
            $value = ['entityType' => $value->getEntityType(), 'entityData' => $value->toArray()];
        }

        $this->memcached->set($key, $value, $expiration);

        $keys = $this->memcached->get($this->keysName);
        $keys[$key] = true;
        $this->memcached->set($this->keysName, $keys);
    }

    public function get(string $key)
    {
        $value = $this->memcached->get($key);

        if (is_array($value) && isset($value['entityType']) && isset($value['entityData'])) {
            $value = $this->buildEntityFromArray($value['entityType'], $value['entityData']);
        }

        return $value;
    }

    public function has(string $key): bool
    {
        return !($this->memcached->get($key) === false && $this->memcached->getResultCode() === \Memcached::RES_NOTFOUND);
    }

    public function delete(string $key): void
    {
        $this->memcached->delete($key);
    }

    public function getKeys(): array
    {
        $keys = $this->memcached->get($this->keysName);

        if (empty($keys)) {
            return [];
        }

        $res = [];

        foreach ($keys as $key => $true) {
            if ($this->has($key)) {
                $res[$key] = $true;
            }
        }

        $this->memcached->set($this->keysName, $res);

        return array_keys($res);
    }

    protected function buildEntityFromArray(string $entityType, array $dataArray): Entity
    {
        $entity = $this->container->get('entityManager')->getEntity($entityType);
        $entity->rowData = $dataArray;
        $entity->set($dataArray);
        $entity->setAsFetched();

        return $entity;
    }
}