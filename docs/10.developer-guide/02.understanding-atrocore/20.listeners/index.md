---
title: Listeners
---

AtroCore uses an event-driven architecture that allows developers to modify the behavior of core features and modules without directly altering the original codebase. This approach provides a clean, maintainable way to extend functionality through event listeners.

AtroCore's EventManager is built on top of the [Symfony Event Dispatcher](https://symfony.com/doc/current/components/event_dispatcher.html), but you don't need to interact with the Event Dispatcher directly. Instead, AtroCore provides convenient abstractions for listening to events from the core system and other modules.

## Key Benefits

- **Non-intrusive modifications**: Modify core behavior without changing original files
- **Modular architecture**: Keep customizations organized in separate modules
- **Event-driven workflow**: React to system events at the right moments
- **Extensible design**: Easy to add new functionality and integrate third-party modules

## How It Works

To listen for events and modify system behavior, simply create the appropriate listener files in your module's `Listeners` folder. AtroCore automatically discovers and registers these listeners based on their filename and location.

**Basic Structure:**
```
YourModule/
├── app/
    ├── Listeners/
    │   ├── Metadata.php          # Modify metadata
    │   ├── Language.php          # Modify translations
    │   ├── Layout.php            # Modify Product entity layouts
    │   ├── ProductLayout.php     # Modify Product entity layouts
    │   ├── ProductEntity.php     # Listen to Product entity lifecycle events
    │   ├── Entity.php            # Listen to ALL entities lifecycle events
    │   ├── ProductController.php # Listen to Product controller actions
    │   ├── Controller.php        # Listen to ALL controllers actions
    │   ├── ProductService.php    # Listen to Product service operations
    │   └── Service.php           # Listen to ALL services operations
```

---

## Metadata Listeners

**File:** `Listeners/Metadata.php`

Use metadata listeners when you need to programmatically modify system metadata. This is particularly useful for:
- Adding metadata conditionally based on system state
- Implementing complex merging logic
- Dynamic metadata generation

### Example Implementation

```php
<?php
namespace {ModuleNamespace}\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractListener;

class Metadata extends AbstractListener
{
    public function modify(Event $event): void
    {
        $data = $event->getArgument('data');

        // Add custom metadata programmatically
        $data['examples']['images']['image1'] = 'modify-programmatically';

        // Update the event with modified data
        $event->setArgument('data', $data);
    }
}
```

### Accessing Modified Metadata

```php
/** @var \Atro\Core\Utils\Metadata $metadata */
$metadata = $container->get('metadata');

$image1 = $metadata->get(['examples', 'images', 'image1']);
// Result: 'modify-programmatically'
```

### Real-World Example
Check out a production implementation: [Metadata.php in AtroPim module](https://gitlab.atrocore.com/atrocore/atropim/-/tree/1.14.26/app/Listeners/Metadata.php)

---

## Language Listeners

**File:** `Listeners/Language.php`

Language listeners allow you to modify localization data at runtime, just before the translations are served via the API. This is useful for:
- Adding or removing translations conditionally
- Dynamic translation generation
- Multi-tenant localization scenarios

### Example Implementation

```php
<?php
namespace {ModuleNamespace}\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractListener;

class Language extends AbstractListener
{
    public function modify(Event $event): void
    {
        $data = $event->getArgument('data');

        // Example: Add dynamic translations
        if ($this->someCondition()) {
            $data['en_US']['Product']['fields']['name'] = 'Full Name'
        }

        // Update the event with modified translations
        $event->setArgument('data', $data);
    }
}
```

---

## Layout Listeners

**File:** `Listeners/{EntityName}Layout.php`

Layout listeners allow you to modify entity layouts from other modules. This is essential when you need to:
- Add new fields to existing entity views
- Modify the arrangement of existing fields
- Conditionally show/hide fields based on context

### Implementation Guidelines

1. **File naming**: Use `{EntityName}Layout.php` (e.g., `ProductLayout.php`)
2. **Extend**: `AbstractLayoutListener`
3. **Respect customizations**: Always check if a layout has been customized in the UI before modifying it

### Example: Adding a Field to Product Layout

```php
<?php
namespace {MyModuleNamespace}\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractLayoutListener;

class ProductLayout extends AbstractLayoutListener
{
    /**
     * Modify the detail view layout
     */
    public function detail(Event $event): void
    {
        // Don't modify layouts that have been customized in the UI
        if (!$this->isCustomLayout($event)) {
            $result = $event->getArgument('result');

            // Add the new field to the detail view
            $result[0]['rows'][] = [['name' => 'retailPrice'], false];

            $event->setArgument('result', $result);
        }
    }

    /**
     * Modify the list view layout
     */
    public function list(Event $event): void
    {
        $relatedEntity = $this->getRelatedEntity($event);
        $result = $event->getArgument('result');

        if (!$this->isCustomLayout($event)) {
            // Conditional field addition based on context
            if ($relatedEntity === 'Category') {
                // Don't add the field for Category detail pages
                return;
            }

            // Add the new field to the list view
            $result[] = ['name' => 'retailPrice'];
        }

        $event->setArgument('result', $result);
    }
}
```

---

## Entity Repository Listeners

Entity repository listeners hook into the entity lifecycle, allowing you to execute custom logic at key moments like before saving or after deletion.

### Types of Entity Listeners

1. **General Listeners** (`Listeners/Entity.php`): Apply to all entity repositories
2. **Specific Listeners** (`Listeners/{EntityName}Entity.php`): Apply to a single entity type

### General Entity Listener

**File:** `Listeners/Entity.php`

Use this when you need logic that applies to multiple entity types:

```php
<?php
namespace {ModuleName}\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractListener;

class Entity extends AbstractListener
{
    /**
     * Executed before any entity is saved
     */
    public function beforeSave(Event $event): void
    {
        $entity = $event->getArgument('entity');

        // Apply logic only to specific entity types
        if ($entity->getEntityType() === 'Product') {
            // Capitalize the first letter of product names
            $entity->set('name', ucfirst($entity->get('name')));
            $event->setArgument('entity', $entity);
        }
    }
}
```

### Specific Entity Listener

**File:** `Listeners/{EntityName}Entity.php`

Use this for entity-specific logic. More efficient and focused than general listeners:

```php
<?php
namespace {ModuleName}\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractListener;

class ProductEntity extends AbstractListener
{
    /**
     * Executed before a Product entity is saved
     */
    public function beforeSave(Event $event): void
    {
        $entity = $event->getArgument('entity');

        // Product-specific logic
        $entity->set('name', ucfirst($entity->get('name')));

        // Validate business rules
        if (empty($entity->get('sku'))) {
            throw new \Exception('SKU is required for products');
        }

        $event->setArgument('entity', $entity);
    }

    /**
     * Executed after a Product entity is saved
     */
    public function afterSave(Event $event): void
    {
        $entity = $event->getArgument('entity');

        // Post-save operations (e.g., cache invalidation, notifications)
        $this->invalidateProductCache($entity->get('id'));
    }
}
```

### Available Entity Events

Entity listeners can respond to these lifecycle events:

| Event | Description | When Triggered |
|-------|-------------|----------------|
| `beforeSave` | Before entity creation or update | Just before saving to database |
| `afterSave` | After entity creation or update | After successful database save |
| `beforeRemove` | Before entity deletion | Just before removing from database |
| `afterRemove` | After entity deletion | After successful database removal |
| `beforeRelate` | Before linking entities | Before creating entity relationships |
| `afterRelate` | After linking entities | After successful relationship creation |
| `beforeUnrelate` | Before unlinking entities | Before removing entity relationships |
| `afterUnrelate` | After unlinking entities | After successful relationship removal |
| `beforeMassRelate` | Before bulk linking | Before mass relationship operations |
| `afterMassRelate` | After bulk linking | After mass relationship operations |

### Event Arguments

Access these arguments using `$event->getArgument('{argumentName}')`:

- **`entity`**: The entity object being processed
- **`entityType`**: The entity type name (e.g., 'Product')
- **`options`**: Additional options passed to the operation
- **`relationName`**: Name of the relation (for relate/unrelate actions)
- **`relationParams`**: Additional relation parameters
- **`relationData`**: Data for intermediate relation fields
- **`foreign`**: The foreign entity ID or object (for relate/unrelate actions)

**Pro Tip:** Use `$event->getArguments()` to inspect all available arguments for debugging.

---

## Controller Listeners

Controller listeners intercept HTTP requests at the controller level, allowing you to modify request handling before or after controller actions execute.

### Types of Controller Listeners

1. **General Listeners** (`Listeners/Controller.php`): Listen to all controller actions
2. **Specific Listeners** (`Listeners/{EntityName}Controller.php`): Listen to specific controller actions

### General Controller Listener

**File:** `Listeners/Controller.php`

```php
<?php
namespace {ModuleName}\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractListener;

class Controller extends AbstractListener
{
    /**
     * Executed before any controller action
     */
    public function beforeAction(Event $event): void
    {
        $data = $event->getArgument('data');
        $params = $event->getArgument('params');
        $request = $event->getArgument('request');

        // Global request validation or logging
        $this->logRequest($request);
    }

    /**
     * Executed after any controller action
     */
    public function afterAction(Event $event): void
    {
        $data = $event->getArgument('data');
        $params = $event->getArgument('params');
        $request = $event->getArgument('request');
        $result = $event->getArgument('result');

        // Global response processing
        $this->processResponse($result);
    }
}
```

### Specific Controller Listener

**File:** `Listeners/{EntityName}Controller.php`

```php
<?php
namespace {ModuleName}\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractListener;

class ProductController extends AbstractListener
{
    /**
     * Executed before the Product delete action
     */
    public function beforeActionDelete(Event $event): void
    {
        $data = $event->getArgument('data');
        $params = $event->getArgument('params');

        // Custom validation before deletion
        if ($this->hasActiveOrders($params['id'])) {
            throw new \Exception('Cannot delete product with active orders');
        }
    }

    /**
     * Executed after the Product create action
     */
    public function afterActionCreate(Event $event): void
    {
        $result = $event->getArgument('result');

        // Post-creation tasks
        $this->sendNotification($result);
    }
}
```

### Available Controller Events

| Event Pattern | Description | Examples |
|---------------|-------------|----------|
| `beforeAction` | Before any action | Global request preprocessing |
| `afterAction` | After any action | Global response postprocessing |
| `beforeAction{Action}` | Before specific action | `beforeActionCreate`, `beforeActionDelete` |
| `afterAction{Action}` | After specific action | `afterActionUpdate`, `afterActionRead` |

### Common Controller Actions

- **`read`**: Get single entity
- **`create`**: Create new entity
- **`update`**: Update existing entity
- **`patch`**: Partial entity update
- **`list`**: Get entity collection
- **`delete`**: Delete entity
- **`createLink`**: Create entity relationship
- **`removeLink`**: Remove entity relationship
- **`follow`**: Follow entity
- **`unfollow`**: Unfollow entity
- **`massUpdate`**: Bulk update entities
- **`massRestore`**: Bulk restore entities

### Event Arguments

- **`data`**: POST data as stdClass object
- **`request`**: Request object for accessing query parameters (`$request->get('param')`)
- **`params`**: URI parameters
- **`result`**: Action result (available in `afterAction` events only)

---

## Service Listeners

Service listeners modify data at the API service layer, allowing you to transform entity data before it's sent to the client or after it's received from the client.

### Types of Service Listeners

1. **General Listeners** (`Listeners/Service.php`): Apply to all entity services
2. **Specific Listeners** (`Listeners/{EntityName}Service.php`): Apply to specific entity services

### General Service Listener

**File:** `Listeners/Service.php`

```php
<?php
namespace {ModuleName}\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractListener;

class Service extends AbstractListener
{
    /**
     * Prepare entity data before sending to client
     */
    public function prepareEntityForOutput(Event $event): void
    {
        $entity = $event->getArgument('entity');

        // Apply transformations based on entity type
        switch ($entity->getEntityType()) {
            case 'Product':
                // Format product names
                $entity->set('name', strtoupper($entity->get('name')));
                break;
            case 'User':
                // Remove sensitive data
                $entity->clear('password');
                break;
        }
    }
}
```

### Specific Service Listener

**File:** `Listeners/{EntityName}Service.php`

```php
<?php
namespace {ModuleName}\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractListener;

class ProductService extends AbstractListener
{
    /**
     * Prepare product data before sending to client
     */
    public function prepareEntityForOutput(Event $event): void
    {
        $entity = $event->getArgument('entity');

        // Product-specific transformations
        $entity->set('name', strtoupper($entity->get('name')));

        // Add computed fields, field most be added to  entityDefs with property notStorable set to true
        $entity->set('discountedPrice', $this->calculateDiscountedPrice($entity));
    }

    /**
     * Validate product before creation
     */
    public function beforeCreateEntity(Event $event): void
    {
        $entity = $event->getArgument('entity');

        // Business logic validation
        if ($entity->get('price') < 0) {
            throw new \Exception('Product price cannot be negative');
        }
    }
}
```

### Available Service Events

#### Entity Lifecycle Events

**Creation & Retrieval:**
- `beforeCreateEntity` / `afterCreateEntity`
- `beforeReadEntity` / `afterReadEntity`
- `beforeGetEntity` / `afterGetEntity`

**Modification:**
- `beforeUpdateEntity` / `afterUpdateEntity`

**Deletion & Restoration:**
- `beforeDeleteEntity` / `afterDeleteEntity`
- `beforeRestoreEntity` / `afterRestoreEntity`

**Internal:**
- `loadEntityAfterUpdate`

#### Collection & List Events

**Finding & Listing:**
- `beforeFindEntities` / `afterFindEntities`
- `beforeGetListKanban` / `afterGetListKanban`

**Output & Display:**
- `prepareCollectionForOutput`
- `loadPreviewForCollection`

#### Relationship & Link Events

**Finding Related Entities:**
- `beforeFindLinkedEntities` / `afterFindLinkedEntities`

**Linking & Unlinking:**
- `beforeLinkEntity` / `afterLinkEntity`
- `beforeLinkEntityMass` / `afterLinkEntityMass`
- `beforeUnlinkEntity` / `afterUnlinkEntity`
- `beforeUnlinkAll` / `afterUnlinkAll`
- `beforeDuplicateLink`

#### Mass Action Events

**Following & Unfollowing:**
- `beforeFollow` / `afterFollow`
- `beforeUnfollow` / `afterUnfollow`
- `beforeMassFollow` / `afterMassFollow`
- `beforeMassUnfollow` / `afterMassUnfollow`

**Updating & Restoring:**
- `beforeMassUpdate` / `afterMassUpdate`
- `beforeMassRestore`

### Event Arguments

Arguments vary by event type. Use `$event->getArguments()` to inspect available arguments for any specific event.

---

## Custom Event Dispatching

You can dispatch custom events for your own modules and listeners.

### Basic Event Dispatching

```php
<?php
$eventManager = $container->get('eventManager');

// Dispatch event with class-based target
$result = $eventManager->dispatch('MyCustomClass', 'myAction', new Event(['customArgument' => 'myvalue']));
$customArgument = $result->getArgument('customArgument');

// Dispatch event without class target
$result = $eventManager->dispatch(new Event(['customArgument' => 'myvalue']), 'myAction');
$customArgument = $result->getArgument('customArgument');
```

### Creating Custom Listeners

**File:** `Listeners/MyCustomClass.php`

```php
<?php
namespace {ModuleNamespace}\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractListener;

class MyCustomClass extends AbstractListener
{
    public function myAction(Event $event): void
    {
        $customArgument = $event->getArgument('customArgument');

        // Process the argument
        $processedValue = strtoupper($customArgument);

        // Update the event
        $event->setArgument('customArgument', $processedValue);
    }
}
```

### Manual Listener Registration

For more control over listener registration:

```php
<?php
$eventManager = $container->get('eventManager');

// Register a callback listener
$eventManager->addListener('myAction', function(Event $event) {
    $data = $event->getArgument('data');
    // Process data
    $event->setArgument('data', $processedData);
});
```

**Parameters:**
- **`$action`**: String name of the action to listen for
- **`$listener`**: Callback function that receives the `$event` parameter

---

## Best Practices

### Listener Organization
- Keep listeners focused on single responsibilities
- Use specific listeners over general ones when possible
- Group related functionality in the same listener class

###  Performance Considerations
- Avoid heavy operations in frequently called events (e.g., `prepareEntityForOutput`)
- Use caching for expensive computations
- Consider the impact of your listeners on system performance

###  Error Handling
- Always validate event arguments before using them
- Use appropriate exception types for different error scenarios
- Log important events for debugging and monitoring


## Troubleshooting

### Common Issues

**Listener Not Executing:**
- Check file naming conventions
- Verify namespace matches your module
- Ensure the listener extends the correct abstract class

**Event Arguments Missing:**
- Use `$event->getArguments()` to see all available arguments
- Check if you're listening to the correct event
- Verify the event is actually being dispatched

**Performance Problems:**
- Profile your listener code
- Consider moving heavy operations to queue jobs
- Use more specific listeners instead of general ones

**Conflicts with Other Modules:**
- Check event execution order
- Consider using event priorities if available
- Coordinate with other module developers
