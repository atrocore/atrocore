---
title: Controllers
taxonomy:
    category: docs
---

In AtroCore, **controllers** are responsible for handling the request-response cycle, making acl control and delegating complex operations to service methods. This approach ensures a clean separation of concerns.

When you create a new entity, AtroCore automatically handles a default controller, which is an instance of `\Atro\Core\Templates\Controllers\{type}`, where `{type}` can be `Base`, `Hierarchy`, `ReferenceData`, or `Relation`. This default controller provides a set of ready-to-use API endpoints for standard entity actions like `read`, `create`, `edit`, and `delete`.

### Custom Controllers

To add custom API routes, you must create your own controller class. This involves creating a new PHP file following the standard directory structure and namespace conventions.

* **Controller Path:** `Controllers/{EntityName}.php`
* **Controller Namespace:** `\ModuleName\Controllers\{EntityName}`

#### Example: `Manufacturer` Statistics Controller

This example demonstrates a custom controller for the `Manufacturer` entity. The `actionStatistic` method is the entry point for a new route (`/Manufacturer/action/statistic`). It validates the request and delegates the core logic to the service.

```php
<?php

namespace ExampleModule\Controllers;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Controllers\Base;

class Manufacturer extends Base
{
    public function actionStatistic($params, $data, $request): array
    {
        if (!$request->isGet()) {
            throw new BadRequest();
        }

        // Use $request->get('requestParam') to access URL parameters.
        // Use $data for POST parameters (not applicable for this GET request).
        return $this->getRecordService()->getStatics();
    }
}
```

-----

### Documenting Custom Routes

AtroCore requires all custom routes to be documented in the `Resources/routes.json` file. This documentation adheres to the **OpenAPI 3.0 specification** and ensures the API is valid and functional.

Here is the JSON object for documenting the new `statistic` route:

```json
{
  "route": "/Manufacturer/action/statistic",
  "method": "get",
  "params": {
    "controller": "Manufacturer",
    "action": "statistic"
  },
  "description": "Get the number of active manufacturers and manufacturers without products",
  "security": [
    {
      "basicAuth": []
    },
    {
      "Authorization-Token": []
    }
  ],
  "response": {
    "type": "object",
    "properties": {
      "actives": {
        "type": "integer",
        "example": "0"
      },
      "withoutProducts": {
        "type": "integer",
        "example": "0"
      }
    }
  }
}
```
