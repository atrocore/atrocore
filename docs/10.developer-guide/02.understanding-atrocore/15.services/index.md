---
title: Services
---

**Entity Services** are where the core business logic of your application resides.
When a handler receives a request, it delegates the complex operations, data manipulation, and business rule enforcement to a service method.
This design pattern ensures that your business logic is centralized, reusable, and easier to test and maintain.

## What Is a Service

A service is always about an entity. `{Module}\Services\{EntityName}` contains the business logic of the API of that entity, e.g. `Pim\Services\Product` for `Product`.

The only exception is the `Global` service. It contains the methods of general nature that are not bound to any entity — the ones used by the handlers in `Handlers/Global`. The name matches `Global` used in metadata and translations. `global` is a reserved word of PHP, so the class is `Atro\Services\GlobalService`, registered as `Global` in `app.services`: `$serviceFactory->create('Global')`.

Any other class does not belong in the `Services` namespace:

| What | Where |
|---|---|
| Interfaces and abstract classes | `{Module}\Core\...` |
| Mechanisms — classes that do something with any entity and are used from many places (handlers, jobs, action types, listeners, other services) | `{Module}\Core\{Mechanism}\...` |
| Dashlets — the data of the dashboard widgets, available via `GET /api/Dashlet/{name}` | `{Module}\Dashlets\{Name}`, implementing `Atro\Core\Dashlets\DashletInterface` and set as `className` in the metadata of the dashlet (`metadata/dashlets/{Name}.json`) |

A mechanism receives its dependencies via the constructor and is obtained from the container (`$container->get(Foo::class)`), not from `ServiceFactory`. See [Service Container](../01.service-container/index.md).

## Default Services

When you create a new entity, AtroCore resolves a default service — an instance of `\Atro\Core\Templates\Services\{type}`, where `{type}` is the entity type: `Base`, `Hierarchy`, `Archive`, `ReferenceData`, `Relation` or `Subtype`.

`Atro\Core\Templates\Services\` contains exactly one class per entity type. Each template contains only the methods that make sense for its entity type and does not get other methods through inheritance. For example, `ReferenceData` has no stream, so its service has no `follow()` or `unfollow()` methods. Methods shared by several templates live in the abstract classes in `Atro\Core\Services\`.

Never override an inherited method only to throw `BadRequest` or `Forbidden`. If an operation is not valid for the entity type, the method must not exist in its service.

## Custom Entity Services

To add custom business logic, create a service class for the entity:

* **Service Path:** `app/Services/{EntityName}.php`
* **Service Namespace:** `\ModuleName\Services\{EntityName}`
* **Parent class:** the template of the entity type, e.g. `\Atro\Core\Templates\Services\Base`

### Example: `Manufacturer` Statistics Service

The following example demonstrates a custom service for the `Manufacturer` entity. The `getStatistics()` method queries the database and retrieves statistics on active manufacturers and those without products.

```php
<?php

namespace ExampleModule\Services;

use Atro\Core\Templates\Services\Base;

class Manufacturer extends Base
{
    public function getStatistics(): array
    {
        $repository = $this->getRepository();

        // Count active manufacturers
        $activeManufacturers = $repository->where(['isActive' => true])->count();

        // Count manufacturers without any linked products
        $where = [
            [
                'type'      => 'isEmpty',
                'attribute' => 'products'
            ]
        ];

        $selectParams = $this->getSelectManager()->getSelectParams(['where' => $where], true, true);

        return [
            'actives'         => $activeManufacturers,
            'withoutProducts' => $repository->count($selectParams)
        ];
    }
}
```

> To learn more about making complex queries, check the section [Select Manager](../10.select-manager/index.md).

## Replace Services

To replace an existing service, create (or change) the `app/Resources/metadata/app/services.json` file in your custom module:

```json
{
  "Product": "\\CustomModule\\Services\\Product"
}
```
