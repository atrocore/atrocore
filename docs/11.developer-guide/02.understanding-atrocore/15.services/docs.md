---
title: Services
taxonomy:
    category: docs
---

**Entity Services** are where the core business logic of your application resides.
When a controller receives a request, it delegates the complex operations, data manipulation, and business rule enforcement to a service method.
This design pattern ensures that your business logic is centralized, reusable, and easier to test and maintain.

When you create a new entity, AtroCore resolve a default service, an instance of `\Atro\Core\Templates\Services\{type}`, where `{type}` can be `Base`, `Hierarchy`, `ReferenceData`, or `Relation`. This default service provides out-of-the-box functionality for common actions such as `create`, `read`, `edit`, `delete`, and `MassUpdate`.

### Custom Entity Services

To add custom business logic, you must create a new service class. This involves creating a new PHP file following the standard conventions.

* **Service Path:** `Services/{ServiceName}.php`
* **Service Namespace:** `\ModuleName\Services\{ServiceName}`

#### Example: `Manufacturer` Statistics Service

The following example demonstrates a custom service for the `Manufacturer` entity. The `getStatics()` method contains the business logic to query the database and retrieve statistics on active manufacturers and those without products.

```php
<?php

namespace ExampleModule\Services;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Service\Base;

class Manufacturer extends Base
{
    public function getStatics(): array
    {
        // Use the query builder for efficiency
        $repository = $this->getRepository();

        // Query to count active manufacturers
        $activeManufacturers = $this->getRepository()->where(['isActive' => true])->count();

        // Query to count manufacturers without any linked products
        $where = [
            [
                "type" => "isEmpty",
                "attribute" => "products"
            ]
        ];

        $selectParams = $this->getSelectManager()->getSelectParams(['where' => $where], true, true);

        $manufacturerWithoutProducts = $repository->count($selectParams);

        return [
            "actives" => $activeManufacturers,
            "withoutProducts" => $manufacturerWithoutProducts
        ];
    }
}
```

> To learn more about making complex query, check the section [Select Manager](../10.select-manager/docs.md)

### Replace Services

To replace existing service, you have to create (or change) ```app/Resources/metadata/app/services.json``` file in your custom module.

Example:
```
{
  "Product": "\\CustomModule\\Services\\Product"
}
```
