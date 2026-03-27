---
title: PSR-15 Handlers
taxonomy:
    category: docs
---

## Overview

AtroCore's HTTP layer is built on open standards: **PSR-7** (HTTP messages), **PSR-15** (middleware pipeline), and **FastRoute** (routing). Every HTTP request passes through a chain of middleware:

---

## PSR-15 Compliance

AtroCore's HTTP layer is built on **PSR-7** (HTTP messages) and **PSR-15** (middleware/request handlers). **Strict compliance with both standards is mandatory.** Every handler and middleware in the system — core or module — must follow the rules below without exception.

### The Two Interfaces

**`MiddlewareInterface`** — processes a request and may delegate to the next handler in the pipeline:
```php
interface MiddlewareInterface {
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface;
}
```

**`RequestHandlerInterface`** — produces a response unconditionally (no delegation):
```php
interface RequestHandlerInterface {
    public function handle(ServerRequestInterface $request): ResponseInterface;
}
```

All AtroCore handlers implement `MiddlewareInterface` (method `process`).

### Rules

1. **Always return `ResponseInterface`.** The `process()` method must always return a response. Throwing an exception is allowed (caught by `ErrorHandlerMiddleware`). Returning `null` or omitting `return` is a violation.

2. **Short-circuiting is allowed and expected.** A handler may return a response directly without calling `$handler->handle($request)`. All AtroCore endpoint handlers do this — they produce the response themselves and never delegate further.

3. **Do not mutate `$request` after delegation.** If `$handler->handle($request)` is called, the `$request` object must not be modified afterwards. Modify it (via `withAttribute()` etc.) only before the call.

4. **Do not store per-request state in class properties.** The handler object may be reused across requests by the DI container. Writing request data into `$this->someValue` during `process()` is a violation.

5. **One handler class = one route.** PSR-15 middleware has a single responsibility. Placing multiple `#[Route]` attributes on one class splits responsibility and is **not permitted**. Create a separate class for each route.

6. **No side-effectful logic in constructors.** Constructors are for dependency injection only. All processing must happen in `process()`.

> These rules apply equally to custom module handlers and core handlers. A handler that violates PSR-15 is considered a defect, not a style issue.

```
ErrorHandlerMiddleware          ← catches all unexpected exceptions
RouteMiddleware                 ← matches the request path via FastRoute
AuthMiddleware                  ← validates the Authorization-Token
ActionHistoryMiddleware         ← logs the action to ActionHistoryRecord
ApiValidationMiddleware         ← validates request input and response output
[module middlewares]            ← optional, registered via Module.php
EntityTypeDispatchMiddleware    ← dispatches to an EntityTypeHandler when applicable
DispatchMiddleware              ← dispatches to the matched direct handler
NotFoundMiddleware              ← returns 404 if nothing matched
```

---

## Dispatch Priority

Every incoming request is dispatched using a three-tier priority system:

### 1. Direct handlers (`Handlers/`)

A handler in a module's `Handlers/` directory is registered directly in FastRoute at startup. It is matched **only by path and HTTP method**, regardless of entity type. Direct handlers always win.

Modules take advantage of this to intercept or extend specific routes. A module's handlers are added after those of previously loaded modules and the core — so a later module can shadow an earlier one by claiming the same route pattern.

### 2. EntityType handlers (`Atro\Core\EntityTypeHandlers\`)

When no direct handler matched, `EntityTypeDispatchMiddleware` checks whether an **EntityType handler** applies. A match requires **two conditions to be true simultaneously**:

- the request path and method match the handler's `#[Route]` pattern, and
- the entity's template type (e.g. `Base`, `Hierarchy`) is listed in the handler's `#[EntityType]` attribute.

This is how AtroCore provides generic CRUD endpoints for all standard entity types without any per-entity code.

### 3. Not found

If neither tier matched, `NotFoundMiddleware` returns a `404 Not Found` response.

---

## Creating a Direct Handler

A direct handler is a PHP class that implements `Psr\Http\Server\MiddlewareInterface` and is annotated with the `#[Route]` attribute.

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
```
Namespace: `MyModule\Handlers\Product\ProductReadHandler`

---

## The `#[Route]` Attribute

Every handler **must** declare a `#[Route]` attribute. This attribute serves as the **single source of truth** for both routing and OpenAPI documentation.

```php
#[Route(
    path: '/MyEntity/{id}/stats',
    methods: ['GET'],
    summary: 'Get MyEntity statistics',
    description: 'Returns statistics for the specified MyEntity record.',
    tag: 'MyEntity',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Record ID',
            'schema'      => [
                'type' => 'string'
            ]
        ],
    ],
    responses: [
        200 => [
            'description' => 'Statistics data',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'total'  => ['type' => 'integer'],
                            'active' => ['type' => 'integer'],
                        ],
                    ]
                ]
            ]
        ],
        400 => ['description' => 'id is required'],
        404 => ['description' => 'Record not found'],
    ],
)]
```

The most common response is `application/json`. Always define the `schema` inside the `200` response — this is what `ApiValidationMiddleware` uses to validate the actual response body.

### Array Formatting in `#[Route]`

**Always expand nested arrays vertically — one key per line, with indentation.** Never write nested structures inline on a single line.

```php
// ✗ Wrong — hard to read
responses: [
    200 => ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
],

// ✓ Correct — each level on its own line
responses: [
    200 => [
        'description' => 'Success',
        'content'     => [
            'application/json' => [
                'schema' => [
                    'type' => 'boolean'
                ]
            ]
        ]
    ],
],
```

This rule applies to all nested arrays inside `#[Route]`: `parameters`, `requestBody`, `responses`. Simple scalar values (`400 => ['description' => '...']`) may stay on one line.

### Required Fields

| Field | Type | Description |
|---|---|---|
| `path` | `string` | Route path. Use `{param}` for path parameters. |
| `methods` | `string\|array` | HTTP methods, e.g. `['GET']`, `['POST']`, `['GET', 'POST']`. |
| `summary` | `string` | Short one-line description shown in API docs. |
| `description` | `string` | Full description of what the endpoint does. |
| `tag` | `string` | Groups the endpoint in API docs (usually the entity name). |
| `responses` | `array` | Map of HTTP status code → response description. |

### Optional Fields

| Field | Type | Default | Description |
|---|---|---|---|
| `auth` | `bool` | `true` | Whether the endpoint requires authentication. Set to `false` only for explicitly public endpoints. |
| `parameters` | `array` | `[]` | OpenAPI-format query/path/header parameters. |
| `requestBody` | `array` | `[]` | OpenAPI-format request body definition. Use `['schema' => ['x-entity-post' => true]]` or `['schema' => ['x-entity-patch' => true]]` as the schema sentinel to automatically substitute the entity's Post/Patch schema (see [Read, Post and Patch Schemas](#read-post-and-patch-schemas)). |

> **Important:** A handler without all required fields **will not be registered as a route**. The endpoint simply will not exist. This is by design — it enforces that every API endpoint is fully documented before it can be used.

---

## Route Design Rules

### Grouping (tag)

Every route must have a `tag` that matches the entity name it operates on. If a route is not bound to any specific entity, use `tag: 'Global'`.

The `tag` maps to OpenAPI tags and groups endpoints in `/apidocs/`.

### Path Structure

Path segments encode **ownership and nesting**. Every segment must represent a resource or an action on that resource. The scope of an action is expressed through the path itself:

| Scope | Path pattern | tag |
|---|---|---|
| Action on a specific record | `/{Entity}/{id}/action` | `Entity` |
| Action on all records of an entity | `/{Entity}/action` | `Entity` |
| Action with no entity binding | `/action` | `Global` |

**Examples:**

```
# Export of a specific feed
POST /ExportFeed/{id}/export         tag: ExportFeed

# Export of multiple feeds (no specific ID)
POST /ExportFeed/export              tag: ExportFeed

# Export with no feed binding at all
POST /export                         tag: Global
```

### Universal Actions (identical across all entities)

When an action has **exactly the same inputs and outputs for every entity** — same request shape, same response shape — creating per-entity routes (`/Product/massDelete`, `/Category/massDelete`, …) is redundant. Use a single global route with `entityName` as an explicit parameter instead:

```
# Instead of:
POST /Product/massDelete
POST /Category/massDelete
POST /Brand/massDelete
...

# Use one route:
POST /massDelete                     tag: Global
  body: { entityName: string, ids: string[] }
  response: boolean
```

The `entityName` parameter carries the same information that the path segment would — but without spawning a separate route for each entity.

This pattern applies when all three conditions hold:
1. The action is applicable to **any** entity without special logic per entity.
2. The **request signature** is identical regardless of entity.
3. The **response signature** is identical regardless of entity.

If any of these conditions breaks — the action has entity-specific logic, parameters, or response shape — use per-entity routes instead.

### Naming Global Routes

Global routes share a single flat namespace. A vague name like `/subscription` or `/follow` will conflict with other operations or become ambiguous as the API grows. **Every Global route name must be specific enough to be unambiguous on its own.**

Rules for naming Global routes:

1. **Prefix with the domain noun** — name the route after the resource it operates on, not the HTTP method or the action verb. The HTTP method already expresses create/delete/update.

   ```
   ✓  POST   /entitySubscription     (resource: subscription on an entity record)
   ✓  DELETE /entitySubscription
   ✗  POST   /follow                 (verb — conflicts with future /follow on other resources)
   ✗  POST   /subscription           (too vague — subscription to what?)
   ```

2. **Avoid generic words** — words like `record`, `data`, `item`, `action`, `info` carry no meaning in a flat namespace. Qualify them: `entitySubscription`, not `subscription`; `entityMassDelete`, not `massDelete` (if scoped to entity records).

3. **Use camelCase** — consistent with existing Global routes (`/massDelete`, `/globalSearch`, `/entitySubscription`).

4. **One resource, multiple methods** — prefer a single path with different HTTP methods over separate paths per action:

   ```
   ✓  POST   /entitySubscription   ← follow
   ✓  DELETE /entitySubscription   ← unfollow

   ✗  POST   /followEntity
   ✗  POST   /unfollowEntity
   ```

### HTTP Method Semantics

| Method | Use |
|---|---|
| `GET` | Read, no side effects |
| `POST` | Create or trigger an action |
| `PATCH` | Partial update |
| `DELETE` | Remove |

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
use MyModule\Services\ProductService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Product/{id}/stats',
    methods: ['GET'],
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
        $id = (string) $request->getAttribute('id');

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

At startup, `RouteCompiler` scans all `Handlers/` directories, reads every `#[Route]` attribute, resolves EntityType expansions, and builds the full route table. The result is stored in `data/cache/routes.json` **always — regardless of the `useCache` setting** — because reflection-based compilation is too expensive to repeat on every request.

> **This means:** whenever you add a handler, remove one, or change a `#[Route]` attribute — even in development — you must run `php console.php clear cache` for the change to take effect.

The cache is invalidated automatically by `clear cache`. There is no other mechanism that refreshes it.

---

### Controlling Handler Registration from a Module

`HandlerRegistry` calls `registerHandlerClasses(array &$classes)` on each module in load order, passing the **full accumulated list** of handler FQCNs collected from core and all previously loaded modules. The default implementation appends the module's own `Handlers/` classes.

Override this method when you need more control:

```php
// src/mymodule/app/MyModule/Module.php

public function registerHandlerClasses(array &$classes): void
{
    // 1. Add this module's own handlers (default behaviour)
    parent::registerHandlerClasses($classes);

    // 2. Remove a core or earlier-module handler
    $classes = array_filter(
        $classes,
        fn(string $c) => $c !== \Atro\Handlers\SomeCoreHandler::class
    );

    // 3. Replace a handler with your own implementation
    $classes = array_map(
        fn(string $c) => $c === \Atro\Handlers\AnotherCoreHandler::class
            ? \MyModule\Handlers\ReplacementHandler::class
            : $c,
        $classes
    );
}
```

Because modules are processed in load order, a module with a higher `getLoadOrder()` value can override decisions made by earlier modules or the core.

---

## API Documentation

All handlers with complete `#[Route]` annotations appear automatically in `/apidocs/`. No separate OpenAPI registration is needed — the documentation is generated directly from the attribute.

---

## Module Middleware

Modules can add their own PSR-15 middleware to the HTTP pipeline by overriding `getMiddlewares()` in their `Module.php`. Module middlewares are placed **after** `ApiValidationMiddleware` and **before** `EntityTypeDispatchMiddleware`, which means they receive a fully authenticated and validated request, and can inspect or modify the response after the handler has run.

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

## Entity Type Handlers

AtroCore provides a ready-made set of PSR-15 handlers that cover all standard CRUD and action endpoints for entity records. These handlers live in `Atro\Core\EntityTypeHandlers\` and are dispatched by `EntityTypeDispatchMiddleware`.

### How Dispatch Works

`EntityTypeDispatchMiddleware` runs after module middlewares. It intercepts requests where the matched route carries an `entityName` parameter and checks whether an EntityType handler applies:

1. Reads the `entityName` from the matched route parameters.
2. Looks up the entity's template type from metadata (`scopes.{entityName}.type`).
3. Queries `EntityTypeHandlerRegistry` — which holds a secondary FastRoute router built from all `EntityTypeHandler` classes — for a handler whose `#[Route]` pattern matches the request **and** whose `#[EntityType]` types list includes the entity's type.
4. If found, forwards the request to that handler (with `entityName` set as a request attribute).
5. If not found, passes the request on to `DispatchMiddleware`.

### The `#[EntityType]` Attribute

Each built-in handler declares which entity template types it applies to, and optionally restricts or requires certain scope metadata flags.

```php
use Atro\Core\Routing\EntityType;

#[Route(path: '/{entityName}', methods: ['GET'], ...)]
#[EntityType(types: ['Base', 'Hierarchy', 'Archive', 'Relation', 'ReferenceData'])]
class ListHandler extends AbstractHandler { ... }

#[Route(path: '/{entityName}/action/inheritField', methods: ['POST'], ...)]
#[EntityType(types: ['Hierarchy'])]
class InheritFieldHandler extends AbstractHandler { ... }
```

Always list types **explicitly** — there is no wildcard shorthand.

#### `#[EntityType]` Parameters

| Parameter | Type | Description |
|---|---|---|
| `types` | `string[]` | **Required.** Entity template types this handler applies to (e.g. `Base`, `Hierarchy`, `Relation`, `Archive`, `ReferenceData`). |
| `excludeEntities` | `string[]` | Entity names to **always skip**, regardless of type. Use this when a specific entity has custom logic or the operation is explicitly disabled for it. |
| `requires` | `string[]` | Keys that must be **truthy** in `scopes.{entityName}` metadata for the route to be registered. The route is skipped for any entity where any of these keys is absent or falsy. |
| `requiresAbsent` | `string[]` | Keys that must be **absent or falsy** in `scopes.{entityName}` metadata. The route is skipped for any entity where any of these keys is truthy. |

**Examples:**

```php
// Only for entities that have attributes (hasAttribute flag in scopes)
#[EntityType(types: ['Base', 'Hierarchy'], requires: ['hasAttribute'])]

// Only for entities where stream is enabled (streamDisabled must not be set)
#[EntityType(types: ['Base', 'Hierarchy', 'Relation'], requiresAbsent: ['streamDisabled'])]

// Skip specific entities that have custom handlers or disabled operations
#[EntityType(types: ['Base', 'Hierarchy', 'Relation'], excludeEntities: ['UserProfile', 'MatchedRecord', 'Notification'])]

// Only for entities that are master-data primaries
#[EntityType(types: ['Base'], requires: ['primaryEntityId'])]
```

> **Note:** `requires` and `requiresAbsent` read from `scopes` metadata only. Flags stored in `clientDefs` (e.g. `createDisabled`) cannot be expressed this way — they require a dedicated check in `RouteCompiler::compileEntityTypeHandlerRoutes()`.

### Available Types and Their Handler Sets

All built-in entity type handlers live in `Atro\Core\EntityTypeHandlers\`.

| Type | Handler set |
|---|---|
| `Base` | Full CRUD + actions + attribute operations |
| `Hierarchy` | Same as Base + tree navigation (`TreeHandler`, `TreeDataHandler`) + field/relation inheritance (`InheritField`, `InheritAll`, `InheritAllForChildren`, `InheritAllFromParent`) |
| `Archive` | Read-only: `list` and `read` only |
| `Relation` | Full CRUD + `InheritRelationHandler`, `RemoveAssociatesHandler` |
| `ReferenceData` | Reduced CRUD (no mass mutations, no follow/link); admin-only |

### Disabling an EntityType Route for a Specific Entity

Sometimes a module must **prevent** a standard EntityType route from being registered for a particular entity — because the operation is not applicable or is explicitly forbidden for that entity type.

The correct approach is to declare an exclusion in the module's `Module.php` via `getEntityTypeHandlerExcludes()`. `RouteCompiler` will then skip route registration for that entity/handler combination entirely — no route is registered, and no blocking handler is needed.

```php
// src/mymodule/app/MyModule/Module.php

use Atro\Core\EntityTypeHandlers\MassDeleteHandler;
use Atro\Core\EntityTypeHandlers\MergeHandler;

public function getEntityTypeHandlerExcludes(): array
{
    return [
        MassDeleteHandler::class => ['MyLockedEntity'],
        MergeHandler::class      => ['MyLockedEntity', 'AnotherEntity'],
    ];
}
```

This method returns a map of **handler FQCN → list of entity names** to exclude. Multiple modules can declare their own exclusions — `RouteCompiler` merges them all.

> **Do not create blocking handlers** (handlers that just `throw new Forbidden()`) to suppress EntityType routes. This approach pollutes the handler registry, registers a route that returns 403, and makes the intent less clear. Use `getEntityTypeHandlerExcludes()` instead.

---

### Overriding an EntityType Handler from a Module

To replace a core EntityType handler for a **specific entity** (e.g. only for `Product`), create a direct handler in your module's `Handlers/` directory with a concrete path. Direct handlers have higher priority than EntityType handlers and always win:

```php
// src/mymodule/app/Handlers/Product/ProductListHandler.php

#[Route(
    path: '/Product',
    methods: ['GET'],
    summary: 'List products',
    description: 'Returns a customised product collection.',
    tag: 'Product',
    responses: [200 => [...]],
)]
class ProductListHandler implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // custom logic ...
    }
}
```

This handler will be matched **before** `ListHandler` for `GET /api/Product`, while all other entities continue to use the core `ListHandler`.

### `AbstractHandler` Base Class

EntityType handlers extend `Atro\Core\EntityTypeHandlers\AbstractHandler`, which provides convenience methods:

| Method | Description |
|---|---|
| `getEntityName(request)` | Returns the `entityName` request attribute set by `EntityTypeDispatchMiddleware`. |
| `getRecordService(entityName)` | Returns the entity's service (falls back to the generic `Record` service). |
| `getAcl()` | Returns the current user's ACL instance. |
| `getUser()` | Returns the current `User` entity. |
| `getRequestBody(request)` | Decodes the JSON request body. |
| `buildListParams(request)` | Parses common list query parameters (`where`, `offset`, `maxSize`, `sortBy`, etc.). |
| `buildListResult(result, params)` | Formats a list service result into the standard `{total, list}` response shape. |
| `buildMassParams(data)` | Parses mass-action parameters (`ids` or `where`+`byWhere`). |

---

## Read, Post and Patch Schemas

For every entity AtroCore automatically generates **three OpenAPI component schemas**:

| Schema | Name | Contents |
|---|---|---|
| Read schema | `{entityName}` | All fields returned by the API (including computed/derived fields like `categoryName`, `createdAt`, etc.) |
| Post schema | `{entityName}Post` | Fields that can be sent in create (POST) requests — includes optional `id` (for custom IDs), excludes `_meta`, `deleted`, `createdAt`, `modifiedAt`, `createdById`, all `_`-prefixed fields, and all `readOnly` fields. Preserves `required` constraints. |
| Patch schema | `{entityName}Patch` | Fields that can be sent in partial update (PATCH) requests — same as Post schema but without `id` and without any `required` constraints (since PATCH is a partial update). |

These schemas are built automatically by `OpenApiGenerator` based on the entity's field definitions.

### Using Entity Schema Sentinels in a Handler

`RouteCompiler` replaces schema sentinels at compile time with concrete `$ref` values. Three sentinels are available:

| Sentinel | Resolves to | Use for |
|---|---|---|
| `['x-entity-read' => true]` | `$ref: #/components/schemas/{Entity}` | Response body — full read schema |
| `['x-entity-post' => true]` | `$ref: #/components/schemas/{Entity}Post` | POST request body — includes `id`, preserves `required` |
| `['x-entity-patch' => true]` | `$ref: #/components/schemas/{Entity}Patch` | PATCH request body — no `id`, no `required` |

```php
// POST
#[Route(
    path: '/{entityName}',
    methods: ['POST'],
    ...
    requestBody: [
        'required' => true,
        'content'  => ['application/json' => ['schema' => ['x-entity-post' => true]]],
    ],
    responses: [
        200 => ['description' => 'Entity record', 'content' => ['application/json' => ['schema' => ['x-entity-read' => true]]]],
    ],
)]

// PATCH
#[Route(
    path: '/{entityName}/{id}',
    methods: ['PATCH'],
    ...
    requestBody: [
        'required' => true,
        'content'  => ['application/json' => ['schema' => ['x-entity-patch' => true]]],
    ],
    responses: [
        200 => ['description' => 'Entity record', 'content' => ['application/json' => ['schema' => ['x-entity-read' => true]]]],
    ],
)]
```

This is how `CreateHandler` (Post) and `UpdateHandler` (Patch) work. The substitution is done by `RouteCompiler::substituteEntitySchemaRef()` when compiling EntityType handler routes.

