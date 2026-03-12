---
title: Service Container
taxonomy:
    category: docs
---

The Service Container is a fundamental component of AtroCore, implemented in the `\Atro\Core\Container` class. It acts as a central registry for all services in the application, providing a standardized way to access core functionality and register custom services.

This documentation explains how to work with the Service Container to access existing services and integrate your own custom services into the AtroCore ecosystem.

!! Core services available in service container should not be mistaken to Entity services where business logic is written. Learn more about Entity Services [here](../15.services).

## Core Concepts

The Service Container provides several key benefits:

- **Centralized Service Management**: All services (both core and custom) are registered in a single location
- **Dependency Injection**: Automatically resolves and injects service dependencies into your classes
- **Service Discovery**: Locate and instantiate services by name or class reference
- **Service Overriding**: Replace core services with custom implementations
- **Global User Context**: Access the authenticated user throughout the application

## Accessing Services

To retrieve a service from the container, use the `get()` method with either a service alias (string) or a class name:

```php
// Access by class name
$entityManager = $container->get(\Espo\Core\ORM\EntityManager::class);

// Access by alias (if registered)
$entityManager = $container->get('entityManager');
```

The container will instantiate the service if it hasn't been created yet, resolving any dependencies automatically.

## Registering Custom Services

### Using Class Aliases

You can register your own classes in the container by creating aliases:

```php
// Register your custom service with an alias
$container->setClassAlias('myCustomService', \MyNamespace\Services\MyCustomService::class);

// Later, retrieve your service
$myService = $container->get('myCustomService');
```

### Automatic Class Resolution

The container can also instantiate classes that aren't explicitly registered:

```php
use Espo\Core\ORM\EntityManager;

class MyCustomClass {
    public function __construct(protected readonly EntityManager $entityManager){
        // Constructor receives automatically injected EntityManager
    }
}

// The container will automatically resolve dependencies when instantiating
$object = $container->get(MyCustomClass::class);
```

## Extending Existing Container Services

You can replace existing container services with your own implementations to customize or extend functionality:

```php
// Define your extended service
class MyExtendedService extends CoreService {
    // Override methods or add new functionality
}

// Replace the core service with your implementation
$container->setClassAlias('coreService', MyExtendedService::class);

// Now when code requests 'coreService', your implementation will be used
$service = $container->get('coreService'); // Returns instance of MyExtendedService
```

!!! Be careful when replacing existing container services with your own versions, as this can potentially break existing functionality if your implementation is incompatible.

## Bootstrapping Custom Services

To ensure your custom services are available throughout the application, you should register them during module initialization. This is done in the `onLoad` method of your module's main class.

Here's how to properly bootstrap your services:

```php
<?php

namespace MyModule;

use Atro\Core\ModuleManager\AbstractModule;
use MyModule\Services\CustomService;

class Module extends AbstractModule
{
    /**
     * Define module load order - higher numbers load later
     * and can override services from modules with lower numbers
     */
    public static function getLoadOrder(): int
    {
        return 5000;
    }

    /**
     * Register services when the module loads
     */
    public function onLoad()
    {
        parent::onLoad();

        // Register your custom services
        $this->container->setClassAlias('myCustomService', CustomService::class);

        // You can also override existing services here
        $this->container->setClassAlias('existingService', MyImprovedService::class);
    }
}
```

After registration, your services can be accessed anywhere in the application:

```php
// In controllers, listeners, or other classes with container access
$myService = $this->container->get('myCustomService');
```

## Global User Context

The container provides access to the currently authenticated user:

```php
// Set the authenticated user (typically done by the authentication system)
$container->setUser($user);

// Access the authenticated user throughout the application
$currentUser = $container->get('user');
```

## Dependency Resolution

The Service Container automatically resolves dependencies for your classes based on type hints in constructor parameters:

```php
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Metadata;

class MyService {
    public function __construct(
        protected readonly EntityManager $entityManager,
        protected readonly Metadata $metadata,
        protected readonly string $customParameter = 'default'
    ) {
        // EntityManager and Metadata are automatically injected
    }
}

// Simple instantiation with automatic dependency injection
$myService = $container->get(MyService::class);
```

## Best Practices

1. **Use type hints**: Always use type hints in your constructor parameters for proper dependency injection
2. **Register services during initialization**: Add your services in the module's `onLoad` method
3. **Avoid direct instantiation**: Use the container to create service instances rather than `new`
4. **Be cautious when overriding core services**: Test thoroughly when replacing existing services or consider using [Listeners](../20.listeners) for extending

---

For more information about creating custom modules in AtroCore, see the [Creating a Module](../../30.own-modules) documentation.
