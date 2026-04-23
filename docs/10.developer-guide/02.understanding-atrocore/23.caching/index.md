
---
title: Caching
---

AtroCore provides a flexible caching layer to store frequently accessed data, which improves performance by reducing the need for expensive database queries.
The system supports two primary types of caching: **persistent caching** (using files) and **in-memory caching**.

-----

## Persistent Caching

Persistent caching stores data to the file system. It's managed using the **`DataManager`** service. To use it, get the service from the container and use its dedicated methods.

You can disable this cache by setting the `useCache` value to `false` in your configuration.

**Example Usage:**

```php
/** @var \Atro\Core\DataManager $dataManager */
$dataManager = $container->get('dataManager');

// Store data in the cache. $value can be a primitive, array, or stdClass of primitives.
$dataManager->setCacheData($cacheKey, $value);

// Retrieve data. Set $isArray to true to return an array instead of stdClass.
$value = $dataManager->getCacheData($cacheKey, $isArray = false);

// Check if a cache key exists
$isCacheExist = $dataManager->isCacheExist($cacheKey);

// Remove data for a specific key
$dataManager->removeCacheData($cacheKey);

// Clear all cache data. Set $silent to true to prevent UI refresh notifications.
$dataManager->clear($silent = false);
```

-----

## In-Memory Caching

In-memory caching is used to store data for the duration of a single request. It is managed by the **`MemoryStorage`** service.

**Example Usage:**

```php
/** @var \Atro\Core\KeyValueStorages\MemoryStorage $memory */
$memory = $container->get('memoryStorage');

// Set any type of value.
$memory->set($key, $value);

// Retrieve a value by key.
$value = $memory->get($key);

// Check if a key exists.
$hasValue = $memory->has($key);

// Remove data for a specific key.
$memory->remove($key);
```

### Memcached In-Memory Storage

By default, the in-memory cache uses a simple array. For a more robust solution, AtroCore supports **Memcached** as an in-memory storage driver.
To enable it, simply add the `memcached.host` and `memcached.port` configurations to your `config` file. Lean more about configuration [here](../35.config/index.md).

Once configured, the `memoryStorage` service will automatically use the Memcached driver.

```php
/** @var \Atro\Core\KeyValueStorages\MemcachedStorage $memory */
$memory = $container->get('memoryStorage');
```

-----

## Using In-Memory Cache in Repositories

AtroCore heavily uses in-memory caching within its repositories to reduce redundant database queries.
This is a core part of its architecture, and it's important to understand how it works.

When you retrieve an entity via a repository, it is automatically stored in the in-memory cache for the duration of that request.
Subsequent calls for the same entity will retrieve it from the cache instead of the database.

**Example:**

```php
/** @var \Espo\Core\EntityManager $entityManager */
$entityManager = $container->get('entityManager');
$id = 'some-id';

// The first call will query the database.
$product = $entityManager->getRepository('Product')->get($id);

// This second call will fetch the entity from the in-memory cache.
$product = $entityManager->getRepository('Product')->get($id);

// Using findOne() with the same key also benefits from the cache.
$product = $entityManager->getRepository('Product')->where(['id' => $id])->findOne();

// To bypass the cache and force a new database query, use the 'noCache' option.
$freshProduct = $entityManager->getRepository('Product')->where(['id' => $id])->findOne(['noCache' => true]);
```
