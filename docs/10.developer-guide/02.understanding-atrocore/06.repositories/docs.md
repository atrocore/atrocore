---
title: Repositories
---

AtroCore implements the **Repository design pattern** to provide a centralized, abstracted layer for data access
operations. This pattern separates business logic from data storage concerns, making your code more maintainable,
testable, and flexible.

## Key Benefits

- **Separation of Concerns**: Business logic stays separate from data access code
- **Testability**: Easy to mock repositories for unit testing
- **Flexibility**: Switch between different data sources without changing business logic
- **Consistency**: Standardized data access patterns across your application
- **Reusability**: Share common queries and operations across different parts of your application

## Automatic Repository Creation

AtroCore automatically creates repository instances - no manual setup required! Eeach entity gets a default repository
that extends `\Atro\Core\Templates\Repositories{Type}` where
`{Type}` is the entity type , it can be either `Base`, `Hierarchy`, `Relation` or `ReferenceData`.

> To learn more about these types check [here.](../05.entities)

## Accessing a Repository

To get the repository of an entity we need to use the Entity Manager from the container.

```php
/** @var \Espo\Core\ORM\EntityManager $entityManager */
$entityManager = $container->get('entityManager');

/** @var \Atro\Core\Templates\Repositories\Base $repository */
$repository = $entityManager->getRepository('Manufacturer');

// Alternative: Get repository through entity type
$userRepository = $entityManager->getRepository('User');
$productRepository = $entityManager->getRepository('Product');
```

### Repository vs EntityManager Methods

AtroCore provides both repository methods and EntityManager shortcuts:

```php
<?php

// Repository method (explicit)
$manufacturer = $entityManager->getRepository('Manufacturer')->get($id);

// EntityManager shortcut (convenient)
$manufacturer = $entityManager->getEntity('Manufacturer', $id);

// Both approaches are valid - use what fits your coding style
```

## CRUD Operations

### Create Operations

#### Creating New Entities

```php
<?php

/** @var \Espo\Core\ORM\EntityManager $entityManager */
$entityManager = $container->get('entityManager');

// Method 1: Repository approach
/** @var \Atro\Core\Templates\Entities\Base $manufacturer */
$manufacturer = $entityManager->getRepository('Manufacturer')->get();
$manufacturer->set('name', 'ACME Manufacturing');
$manufacturer->set('description', 'Leading manufacturer of quality products');
$manufacturer->set('isActive', true);

$entityManager->getRepository('Manufacturer')->save($manufacturer);

// Method 2: EntityManager shortcut
$manufacturer = $entityManager->getEntity('Manufacturer');
$manufacturer->set('name', 'Global Industries');
$manufacturer->set('isActive', true);

$entityManager->saveEntity($manufacturer);
```

#### Bulk Creation

```php
<?php

$manufacturerData = [
    ['name' => 'Company A', 'isActive' => true],
    ['name' => 'Company B', 'isActive' => true],
    ['name' => 'Company C', 'isActive' => false]
];

$repository = $entityManager->getRepository('Manufacturer');

foreach ($manufacturerData as $data) {
    $manufacturer = $repository->get();
    $manufacturer->set($data);
    $repository->save($manufacturer);
}
```

### Read Operations

#### Single Entity Retrieval

```php
<?php

$entityManager = $container->get('entityManager');

// Read existing entity by ID
$manufacturer = $entityManager->getRepository('Manufacturer')->get($id);

// EntityManager shortcut
$manufacturer = $entityManager->getEntity('Manufacturer', $id);

// Handle non-existent entities
if ($manufacturer) {
    echo "Found: " . $manufacturer->get('name');
} else {
    echo "Manufacturer not found";
}
```

#### Advanced Single Entity Queries

```php
<?php

$repository = $entityManager->getRepository('Manufacturer');

// Find first entity matching criteria
$activeManufacturer = $repository
    ->where(['isActive' => true])
    ->findOne();

// Find with specific field selection
$manufacturer = $repository
    ->select(['id', 'name', 'isActive'])
    ->where(['name' => 'ACME Manufacturing'])
    ->findOne();
```

### Update Operations

```php
<?php

$entityManager = $container->get('entityManager');

// Load, modify, and save
$manufacturer = $entityManager->getEntity('Manufacturer', $id);

if ($manufacturer) {
    $manufacturer->set('name', 'Updated Company Name');
    $manufacturer->set('description', 'Updated description');

    // Repository method
    $entityManager->getRepository('Manufacturer')->save($manufacturer);

    // Or EntityManager shortcut
    $entityManager->saveEntity($manufacturer);
}
```

### Delete Operations

#### Soft Delete (Recommended)

AtroCore uses soft deletes by default - entities are marked as deleted but remain in the database:

```php
<?php

$entityManager = $container->get('entityManager');

$manufacturer = $entityManager->getEntity('Manufacturer', $id);

if ($manufacturer) {
    // Soft delete - marks entity as deleted
    $entityManager->getRepository('Manufacturer')->remove($manufacturer);

    // Or using EntityManager shortcut
    $entityManager->removeEntity($manufacturer);
}
```

#### Restore Deleted Entities

```php
<?php

$entityManager = $container->get('entityManager');

// Restore a soft-deleted entity
$entityManager->getRepository('Manufacturer')->restore($id);

// Verify restoration
$manufacturer = $entityManager->getEntity('Manufacturer', $id);
if ($manufacturer) {
    echo "Manufacturer successfully restored";
}
```

#### Permanent Delete (Use with Caution)

```php
<?php

$entityManager = $container->get('entityManager');

// Permanently remove from database - IRREVERSIBLE!
$entityManager->getRepository('Manufacturer')->deleteFromDb($id);

// This completely removes the record and cannot be undone
```

!!!  `deleteFromDb()` permanently removes data. Use only when you're certain the data should never be recovered.

---

## Finds related entities

```php
 $manufacturer = $entityManager->getEntity('Manufacturer', $id);
 $relationNames = 'products';
 $products = $entityManager->getRepository('Manufacturer')->findRelated($entity, $relationNames);
```

## Querying

### Comparison operators

Supported comparison operators are: `>`, `<`, `>=` `<=`, `=`, `!=`.

```php
$stocks = $entityManager->getRepository('Stock')->where(['amount>=' => 150])->find();
```

! When using `=` use can omit it `['amount' => 50]` instead of `['amount=' => 50]`.

### IN and NOT IN

Here the operators  `=`, `!=` are still used, the value just need to be and array.

```php
 $manufacturers = $entityManager->getRepository('Manufacturer')->where(['name' => ['ManufacturerName1', 'ManufacturerName2']]);

```

```php
 $notInManufacturers = $entityManager->getRepository('Manufacturer')->where(['name!=' => ['ManufacturerName1', 'ManufacturerName2']]);
```

### LIKE operators

`*` is used for LIKE and `!*` for NOT LIKE

```php
 $manufacturers = $entityManager->getRepository('Manufacturer')->where(['name*' => '%atrocore%' ]);
```

### OR, AND operators

```php
$opportunityList = $entityManager
    ->getRepository('Product')
    ->where([
        [
            'OR' => [
                ['status' => 'draft'],
                ['isActive' => false],
            ],
            'AND' => [
                'quantity>' => 100,
                'quantity<=' => 999,
            ],
        ]
    ])
    ->findOne();
```

## Custom Repository Classes

### Creating a Custom Repository

When you need to add custom query methods or modify default behavior, create a custom repository class:

**File:** `Repositories/Manufacturer.php`

```php
<?php

namespace ExampleModule\Repositories;

use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\EntityCollection;
use Espo\ORM\Entity;

class Manufacturer extends Base
{
    /**
     * Get all active manufacturers ordered by name
     *
     * @return EntityCollection
     */
    public function getActiveManufacturers(): EntityCollection
    {
        return $this
            ->where(['isActive' => true])
            ->order('name', 'ASC')
            ->find();
    }


    /**
     * Find manufacturers by name pattern with fuzzy matching
     *
     * @param string $namePattern
     * @return EntityCollection
     */
    public function findByNamePattern(string $namePattern): EntityCollection
    {
        // Clean and prepare pattern
        $pattern = '%' . strtolower(trim($namePattern)) . '%';

        return $this
            ->where(
                [
                    'name*' => $pattern,
                    'isActive' => true
                ]
            )
            ->order('name', 'ASC')
            ->find();
    }

    /**
     * Lifecycle hook: before saving entity
     *
     * @param Entity $entity
     * @param array $options
     */
    protected function beforeSave(Entity $entity, array $options = []): void
    {
        // Standardize name formatting
        if (!empty($entity->get('name'))) {
            $name = trim($entity->get('name'));
            $entity->set('name', ucwords(strtolower($name)));
        }

        // Auto-generate slug from name
        if (!empty($entity->get('name')) && empty($entity->get('slug'))) {
            $slug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $entity->get('name')));
            $entity->set('slug', trim($slug, '-'));
        }

        parent::beforeSave($entity, $options);
    }

    /**
     * Lifecycle hook: after saving entity
     *
     * @param Entity $entity
     * @param array $options
     */
    protected function afterSave(Entity $entity, array $options = []): void
    {
        parent::afterSave($entity, $options);

        // Clear relevant caches
        $this->getDataManager()->clearCache();

    }
}
```

### Available Lifecycle Hooks

Custom repositories can override these lifecycle methods:

Here is the completed table without any bold formatting.

| Method             | Description                   | When Called                                       |
|--------------------|-------------------------------|---------------------------------------------------|
| `beforeSave()`     | Before entity is saved        | Create and update operations                      |
| `afterSave()`      | After entity is saved         | After successful save                             |
| `beforeRemove()`   | Before entity deletion        | Before soft delete                                |
| `afterRemove()`    | After entity deletion         | After soft delete                                 |
| `beforeRestore()`  | Before entity restoration     | Before restoring deleted entity                   |
| `afterRestore()`   | After entity restoration      | After successful restoration                      |
| `beforeRelate()`   | Before entities are related   | Before a link is established between two entities |
| `afterRelate()`    | After entities are related    | After a successful link is established            |
| `beforeUnrelate()` | Before entities are unrelated | Before a link between two entities is removed     |
| `afterUnrelate()`  | After entities are unrelated  | After a successful link removal                   |

### Using Custom Repository Methods

```php
<?php

/** @var \ExampleModule\Repositories\Manufacturer $repository */
$repository = $entityManager->getRepository('Manufacturer');

// Use custom methods
$activeManufacturers = $repository->getActiveManufacturers();


$searchResults = $repository->findByNamePattern('acme');

// Lifecycle hooks are automatically called
$manufacturer = $entityManager->getEntity('Manufacturer');
$manufacturer->set('name', 'new company name');  // Will be formatted by beforeSave()
$repository->save($manufacturer);  // Triggers beforeSave() and afterSave()
```

---

### Raw SQL Queries

For complex queries beyond ORM capabilities, use direct database connections:

```php
/** @var \Espo\Core\ORM\EntityManager $entityManager */
$entityManager = $containter->get('entityManager');

/** @var \Doctrine\DBAL\Connection $connection */
$connection = $entityManager->getConnection();

$activeManufacturers = $connection->createQueryBuilder()
    ->from($connection->quoteIdentifier('manufacturer'))
    ->select('id, name')
    ->where('deleted = :false')
    ->andWhere('isActive = :true')
    ->setParameter('false', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
    ->setParameter('true', true, \Doctrine\DBAL\ParameterType::BOOLEAN)
    ->fetchAllAssociative();
```

!!! This method is not recommended because it directly manipulates the database. Any future database schema changes will break the code.
!!! Furthermore, this approach bypasses AtroCore's event system. As a result, other parts of the application will not be notified of any changes done, which can lead to data inconsistencies and unexpected behavior.

## Repository Limitations and SelectManager

The standard repository pattern in AtroCore has limitations when dealing with relational queries. For advanced querying
needs, use the [**SelectManager**](../10.select-manager/docs.md):

### Repository Limitations

- Limited support for complex JOIN operations
- Difficult to query across multiple related entities
- No support for advanced aggregations
- Limited subquery capabilities

### SelectManager Advantages

- Advanced JOIN operations
- Complex WHERE conditions across relationships
- Aggregation functions (COUNT, SUM, AVG, etc.)
- Subquery support
