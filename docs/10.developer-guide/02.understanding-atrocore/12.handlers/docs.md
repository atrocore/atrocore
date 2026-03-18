---
title: PSR-15 Handlers
taxonomy:
    category: docs
---

## Overview

AtroCore's HTTP layer is built on open standards: **PSR-7** (HTTP messages), **PSR-15** (middleware pipeline), and **FastRoute** (routing). Every HTTP request passes through a chain of middleware:

```
ErrorHandlerMiddleware       ← catches all unexpected exceptions
RouteMiddleware              ← matches the request path via FastRoute
AuthMiddleware               ← validates the Authorization-Token
ApiValidationMiddleware      ← validates request input and response output
[module middlewares]         ← optional, registered via Module.php
DispatchMiddleware           ← dispatches to the matched handler
NotFoundMiddleware           ← returns 404 if nothing matched
```

---

## Creating a Handler

A handler is a PHP class that implements `Psr\Http\Server\MiddlewareInterface` and is annotated with the `#[Route]` attribute.

### Directory Structure

```
src/<module>/app/
└── Handlers/
    └── <EntityName>/
        └── <EntityName><Action>Handler.php
```

The `Handlers/` directory is scanned automatically by `HandlerRegistry`. All classes found there are registered as routes — no additional configuration is needed.

**Example:**
```
src/mymodule/app/Handlers/Product/ProductReadHandler.php
src/mymodule/app/Handlers/Product/ProductCreateHandler.php
src/mymodule/app/Handlers/Product/ProductUpdateHandler.php
src/mymodule/app/Handlers/Product/ProductDeleteHandler.php
```
Namespace: `MyModule\Handlers\Product\ProductReadHandler`

---

## The `#[Route]` Attribute

Every handler **must** declare a `#[Route]` attribute. This attribute serves as the **single source of truth** for both routing and OpenAPI documentation.

```php
#[Route(
    path: '/MyEntity/{id}/stats',
    summary: 'Get MyEntity statistics',
    description: 'Returns statistics for the specified MyEntity record.',
    tag: 'MyEntity',
    parameters: [
        ['name' => 'id', 'in' => 'path', 'required' => true,
         'description' => 'Record ID', 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'Statistics data', 'content' => ['application/json' => ['schema' => [
            'type'       => 'object',
            'properties' => [
                'total'  => ['type' => 'integer'],
                'active' => ['type' => 'integer'],
            ],
        ]]]],
        400 => ['description' => 'id is required'],
        404 => ['description' => 'Record not found'],
    ],
)]
```

The most common response is `application/json`. Always define the `schema` inside the `200` response — this is what `ApiValidationMiddleware` uses to validate the actual response body.

### Required Fields

| Field | Type | Description |
|---|---|---|
| `path` | `string` | Route path. Use `{param}` for path parameters. |
| `summary` | `string` | Short one-line description shown in API docs. |
| `description` | `string` | Full description of what the endpoint does. |
| `tag` | `string` | Groups the endpoint in API docs (usually the entity name). |
| `methods` | `string\|array` | HTTP methods, e.g. `['GET']`, `['POST']`, `['GET', 'POST']`. |
| `responses` | `array` | Map of HTTP status code → response description. |

### Optional Fields

| Field | Type | Default | Description |
|---|---|---|---|
| `auth` | `bool` | `true` | Whether the endpoint requires authentication. Set to `false` only for explicitly public endpoints. |
| `parameters` | `array` | `[]` | OpenAPI-format query/path/header parameters. |

> **Important:** A handler without all required fields **will not be registered as a route**. The endpoint simply will not exist. This is by design — it enforces that every API endpoint is fully documented before it can be used.

---

## Automatic Validation

`ApiValidationMiddleware` automatically validates every request and response for handler-based routes using the OpenAPI schema derived from the `#[Route]` attribute.

- **Request validation** — query parameters, path parameters, and request body are validated against the types and constraints defined in `parameters` and `requestBody`.
- **Response validation** — the response body is validated against the schema defined in `responses`.

If validation fails, the middleware returns a `400 Bad Request` with a description of the violation.

**This means the quality of your `#[Route]` annotation directly affects how well the system protects your endpoint.** Describe types, enums, and required flags precisely.

---

## Full Handler Example

```php
<?php

declare(strict_types=1);

namespace MyModule\Handlers\Product;

use Atro\Core\Http\Response\Errors\BadRequestResponse;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Mezzio\Router\RouteResult;
use MyModule\Services\ProductService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Product/{id}/stats',
    summary: 'Get product statistics',
    description: 'Returns sales and inventory statistics for the specified product.',
    tag: 'Product',
    parameters: [
        ['name' => 'id', 'in' => 'path', 'required' => true,
         'description' => 'Product record ID', 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'Product statistics', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
        400 => ['description' => 'id is required'],
        404 => ['description' => 'Product not found'],
    ],
)]
class ProductStatsHandler implements MiddlewareInterface
{
    public function __construct(private readonly ProductService $productService)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routeResult = $request->getAttribute(RouteResult::class);
        $id = $routeResult?->getMatchedParams()['id'] ?? null;

        if (empty($id)) {
            return new BadRequestResponse('id is required');
        }

        return new JsonResponse($this->productService->getStats($id));
    }
}
```

**Key points:**

- Inject dependencies directly via constructor — the service container resolves them automatically (see [Service Container](../01.service-container)).
- Do **not** use try/catch for unexpected errors — `ErrorHandlerMiddleware` handles them centrally.
- Use `BadRequestResponse`, `NotFoundResponse`, etc. from `Atro\Core\Http\Response\Errors\` for expected error cases.
- Use `JsonResponse` for successful JSON responses.

---

## Route Discovery and Caching

`HandlerRegistry` discovers handler classes by scanning the `Handlers/` directory of each module. This scan happens **once per request** and the result is cached via `DataManager` (key: `handler_routes`).

- **Cache enabled** (`useCache = true` in config) — the class list is stored in `data/cache/handler_routes.json` and reused on subsequent requests. Run `php console.php clear-cache` to invalidate it after adding or renaming handlers.
- **Cache disabled** (`useCache = false`) — the filesystem is scanned on every request. Use this setting only in development.

> After adding a new handler in production, always run `php console.php clear-cache` to ensure it is picked up.

---

## API Documentation

All handlers with complete `#[Route]` annotations appear automatically in `/apidocs/`. No separate OpenAPI registration is needed — the documentation is generated directly from the attribute.

---

## Module Middleware

Modules can add their own PSR-15 middleware to the HTTP pipeline by overriding `getMiddlewares()` in their `Module.php`. Module middlewares are placed **after** `ApiValidationMiddleware` and **before** `DispatchMiddleware`, which means they receive a fully authenticated and validated request, and can inspect or modify the response after the handler has run.

### Registering Middleware

Override `getMiddlewares()` in your module class:

```php
// src/mymodule/app/MyModule/Module.php

public function getMiddlewares(): array
{
    return [
        \MyModule\Middleware\MyMiddleware::class,
    ];
}
```

The service container resolves each class automatically, so constructor injection works the same way as in handlers.

### Example: Adding a Custom Response Header

```php
<?php

declare(strict_types=1);

namespace MyModule\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AddVersionHeaderMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly \Psr\Container\ContainerInterface $container)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $version = $this->container->get('moduleManager')->getModule('MyModule')?->getVersion() ?? 'unknown';

        return $handler->handle($request)->withHeader('X-MyModule-Version', $version);
    }
}
```

The call to `$handler->handle($request)` runs the rest of the pipeline — including the actual handler — and returns the fully-built response. Anything done **after** that call modifies the final response.

### Execution Order

When multiple modules register middleware, they are piped in module **load order** (as defined by each module's `getLoadOrder()`).

---

## Legacy Controllers

Some modules still contain a `Controllers/` directory with classes extending `Atro\Core\Templates\Controllers\Base` (and similar). These are supported via `LegacyControllerHandler`, which bridges the old system into the PSR-15 pipeline.

**This is technical debt scheduled for removal.** Do not create new controllers. All new endpoints must be implemented as PSR-15 handlers as described in this document. Existing controllers will be migrated to handlers in an upcoming release.
