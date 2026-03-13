---
title: Select Managers
taxonomy:
    category: docs
---

For complex queries in **AtroCore**, use the **`SelectManager`**. It enables you to build high-level queries and pass them to the entity repository.

## Using SelectManager

To get started, create a `SelectManager` instance using the `selectManagerFactory` and an `entityManager` instance to get the repository.


```php
/** @var \Espo\Core\SelectManagers\Base $selectManager */
$selectManager = $container->get('selectManagerFactory')->create($entityName);
$repository = $container->get('entityManager')->getRepository($entityName);

// Build the query
$selectParams = $selectManager->getSelectParams(['where' => $where], true, true);

$count = $repository->count($selectParams);

$entities = $repository->find($selectManager);
```

-----

## General Query Structure

A `where` query is an array of conditions. Each condition is a sub-array with the following keys:

* **`attribute`**: The field or attribute ID to query.
* **`type`**: The operator to use (e.g., `equals`, `like`).
* **`value`**: The value to compare against. It is  omitted for operators what does not require a value (e.g., `isNull`, `currentMonth`)
* **`isAttribute`**: Optional, set to `true` if querying an attribute. By default, it's assumed you're querying a field.

Adding multiple elements to the `where` array automatically applies an **`AND`** condition.

```php
$where = [
    [
        "attribute" => $fieldNameOrAttributeId,
        "type" => $operationName,
        "value" => $value,
        "isAttribute" => $isAttribute
    ]
];
```

-----

## Common Operators

### Comparison Operators

Supported operators include `equals`, `notEquals`, `lessThan`, `greaterThan`, `lessThanOrEquals`, and `greaterThanOrEquals`.

```php
$where = [
    [
        "attribute" => 'amount',
        "type" => 'lessThan',
        "value" => 15
    ]
];
```

### Null Checks

Use `isNull` and `isNotNull` to check for null values.

```php
$where = [
    [
        "attribute" => 'name',
        "type" => 'isNull'
    ]
];
```

### `IN` and `NOT IN` Operators

Use `in` and `notIn` with an array of values.

```php
$where = [
    [
        "attribute" => 'name',
        "type" => 'in',
        "value" => ['name1', 'name2']
    ]
];
```

### String Operators

Operators for string fields include `like`, `notLike`, `startsWith`, `endsWith`, `contains`, `notContains`, `isEmpty`, and `isNotEmpty`.

```php
$where = [
    [
        "attribute" => 'name',
        "type" => 'like',
        "value" => '%atro%'
    ]
];
```

### Date Operators

Date fields support operators with or without a value.

* **Without a value**: `today`, `past`, `future`, `lastSevenDays`, `currentMonth`, etc.
* **With a value**: `lastXDays`, `nextXDays`, `olderThanXDays`, `afterXDays`.
* **With two values**: `between`.

<!-- end list -->

```php
// Query for pasts (prior to the current date) records
$where = [
    [
        "attribute" => 'createdAt',
        "type" => 'past'
    ]
];

// Query for records created in the last 5 days
$where = [
    [
        "attribute" => 'createdAt',
        "type" => 'lastXDays',
        "value" => 5
    ]
];

// Query for records created between 2025-01-01 and 2025-06-01
$where = [
    [
        "attribute" => 'createdAt',
        "type" => 'between',
        "value" => ['2025-01-01', '2025-06-01']
    ]
];
```

### Array Operators

For fields like `extensibleMultiEnum` or `array`, use `arrayAnyOf` or `arrayNoneOf`.

```php
$where = [
    [
        "attribute" => 'myValues',
        "type" => 'arrayAnyOf',
        "value" => ['value1', 'value2']
    ]
];
```

### Unit Field Operators

Fields with a `measureId` (e.g., `price` with a currency measure, or `weight` with a mass measure) store both a numeric value and a unit reference. When querying these fields, you can provide the value together with its unit. The system will automatically convert and compare across all units of the same measure, so filtering for "100 cm" will also match records stored as "1 m" or "1000 mm".

To query a unit field, set `unitField` to `true` and pass the value as an array of `[amount, unitId]`.

Supported operators: `equals`, `notEquals`, `greaterThan`, `greaterThanOrEquals`, `lessThan`, `lessThanOrEquals`, `between`, `isNull`, `isNotNull`.

```php
// Find products where price equals 50 in the given unit
$where = [
    [
        "attribute" => 'price',
        "type" => 'equals',
        "value" => [50, 'unit_id_for_usd'],
        "unitField" => true
    ]
];
```

For `between`, the value is an array of two `[amount, unitId]` pairs — one for the lower bound and one for the upper bound. Each bound can use a different unit:

```php
$where = [
    [
        "attribute" => 'price',
        "type" => 'between',
        "value" => [
            [10, 'unit_id_for_eur'],
            [50, 'unit_id_for_usd']
        ],
        "unitField" => true
    ]
];
```

`isNull` and `isNotNull` check that **both** the numeric value and the unit are null (or both are not null). No `value` key is needed:

```php
$where = [
    [
        "attribute" => 'price',
        "type" => 'isNull',
        "unitField" => true
    ]
];
```

Unit field queries also work with attributes. Set `isAttribute` to `true` alongside `unitField`:

```php
$where = [
    [
        "attribute" => $attributeId,
        "type" => 'greaterThan',
        "value" => [100, 'unit_id_for_kg'],
        "isAttribute" => true,
        "unitField" => true
    ]
];
```

-----

### Subqueries for Relationships

You can use subqueries to filter entities based on related data.

### Many-to-Many or One-to-Many

Use `linkedWith` and `notLinkedWith` for link fields (`linkMultiple`). You can provide an IDs array or a subquery.

**Example**: query manufacturers that produces some brands

```php
// Using IDs array
$where = [
    [
        "attribute" => 'brands',
        "type" => 'linkedWith',
        "value" => ['a01k5655ntxeajvyh6fvcrrksys', 'a01k7895ntxeajvyh6fvcfrrkxea']
    ]
];
// Using subquery: Find manufacturers that produce brands name Iphone
$where = [
    [
        "attribute" => 'brands',
        "type" => 'linkedWith',
        "subQuery" => [
            [
                "attribute" => "name",
                "type" => "equals",
                "value" => "Iphone"
            ]
        ]
    ]
];
```

### Many-to-One

Use the ID field of the Many-to-One relationship with a subquery.

**Example**: Find products with the Manufacturer named 'AtroCore'.

```php
// With IDs array
$where = [
    [
        "attribute" => 'manufacturerId',
        "type" => 'in',
        "value" => ['a01k5655ntxeajvyh6fvcrrksys', 'a01k7895ntxeajvyh6fvcfrrkxea']
    ]
];

// With Subquery : Find products with the brand named 'AtroCore'.
$where = [
    [
        "attribute" => 'manufacturerId',
        "type" => 'in',
        "subQuery" => [
            [
                "attribute" => "name",
                "type" => "equals",
                "value" => "AtroCore"
            ]
        ]
    ]
];
```

-----

### `AND`,  `OR` and `NOT` Operators

Combine conditions using `and` , `or` and `not` types. The `value` for these types is another array of query conditions.

```php
// AND condition
$where = [
    [
        "type" => 'and',
        "value" => $andWhere // an array of query conditions
    ]
];

// OR condition
$where = [
    [
        "type" => 'or',
        "value" => $orWhere // an array of query conditions
    ]
];
```

-----

### Advanced Control with Callbacks

For low-level control, use callbacks to access the query builder directly. This is useful for complex, custom logic.

```php
use Espo\ORM\IEntity;
use Doctrine\DBAL\Query\QueryBuilder;
use Atro\ORM\DB\RDB\Mapper;

// Query products that belongs to the user's teams

$selectParams['callbacks'][] = function (QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper) {
    $ta = $mapper->getQueryConverter()->getMainTableAlias();
    $sql = "($ta.id IN (SELECT entity_id FROM entity_team WHERE deleted=:false AND entity_type=:entityType AND team_id IN (:teamsIds)))";

    $qb->andWhere($sql)
        ->setParameter('teamsIds', $this->getUser()->getLinkMultipleIdList('teams'), Connection::PARAM_STR_ARRAY)
        ->setParameter('entityType', $this->entityType)
        ->setParameter('false', false, ParameterType::BOOLEAN);
};
```

For more information on using the `QueryBuilder` instance `$qb`, refer to the [Doctrine QueryBuilder documentation](https://www.doctrine-project.org/2020/11/17/dbal-3.0.0.html).

## Bool Filters in AtroCore

In AtroCore, **bool filters** are predefined, reusable custom filters that can be applied to entity requests. They're useful for common business queries and for front-end filtering scenarios, like displaying a list of records in a modal that excludes already selected items.

#### Common Bool Filters

Several standard bool filters are available for most entities:

* **`onlyDeleted`**: Queries only entities that have been deleted.
* **`onlyBookmarked`**: Queries only entities bookmarked by the user.
* **`onlyMy`**: Queries entities created by, assigned to, or owned by the current user, or linked to the user's teams.
* **`onlyActive`**: Queries active entities. This applies to entities with `hasActive` set to `true` in their scope definition.
* **`onlyArchived`**: Queries archived entities. This applies to entities with `hasArchive` set to `true` in their scope definition.

You can check which custom filters are available for an entity by looking at the `boolFilterList` key in its `Resources/metadata/clientDefs/{EntityName}.json` file.

> `onlyDeleted` and `onlyBookmarked` are almost always available regardless of what's listed.

### Applying Bool Filters

To apply these filters, include a `where` condition of `type` **`bool`** in your query. You can apply multiple filters by listing them in the `value` array. An **`AND`** condition is applied between all listed filters.

```php
$where = [
    [
        "type" => "bool",
        "value" => ['onlyDeleted', 'onlyMy']
    ]
];
```

Some bool filters require additional **`data`** to function. For example, the `onlyEntity` filter on the `Attribute` entity needs to know which entity to filter by:

```php
$where = [
    [
        "type" => 'bool',
        "value" => ['onlyEntity'],
        "data" => [
            "onlyEntity" => "Product"
        ]
    ]
];
```

-----

### Creating Your Own Bool Filters

You can create custom bool filters by extending the entity's **`SelectManager`**.

1.  **Create the SelectManager File**: In your module, create a file at `\ModuleName\SelectManagers\{EntityName}.php`. This class must extend `\Espo\Core\SelectManagers\Base`.

2.  **Define the Filter Method**: Create a new protected method named `boolFilter<FilterName>`. This method will contain the logic for your filter. For our `Manufacturer` entity, let's create a filter named `onlyNotActive`:

```php
<?php
namespace ExampleModule\SelectManagers;

use Espo\Core\SelectManagers\Base;

class Manufacturer extends Base
{
    protected function boolFilterOnlyNotActive(&$result)
    {
        $result['whereClause'][] = ['isActive' => false];
    }
}
```

3.  **Register the Filter**: Add the new filter to the `boolFilterList` in the entity's `Resources/metadata/clientDefs/{EntityName}.json` file.

```json
{
    "controller": "controllers/record",
    "iconClass": "tag",
    "boolFilterList": ["isNotActive"]
}
```

### Applying Saved Searches(Filters)

Saved Searches are custom filters registered in the entity `SavedSearch`, you can learn more about it in the section [Saved Filters](../../../01.atrocore/11.search-and-filtering).
To apply these filters, include a `where` condition of `type` **`savedSearch`** in your query. You can apply multiple savedSearch by listing their IDs in the `value` array. An **`AND`** condition is applied between all listed filters.

```php
$where = [
    [
        "type" => "savedSearch",
        "value" => ['a01k7p5y5z8e51t7t3bpa23jh0c', 'a01k7p5k0kqe1jst7t2f5s8wn4g']
    ]
];
```
