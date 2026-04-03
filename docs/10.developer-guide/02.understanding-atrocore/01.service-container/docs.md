---
title: Service Container
taxonomy:
    category: docs
---

The DI system in AtroCore is powered by **[Laminas ServiceManager](https://docs.laminas.dev/laminas-servicemanager/)** — a battle-tested, PSR-11 compliant container maintained by the Laminas Project.

!! Core services in the container should not be confused with Entity Services where business logic lives. Learn more about Entity Services [here](../15.services).

---

## Using the container

Use `$container` — this is the standard name across the PHP ecosystem. Type-hint against `Psr\Container\ContainerInterface`. The system provides `Atro\Core\Container` which implements this interface and adds IDE-friendly generics on `get()`, so PhpStorm infers the return type automatically when a class-string is passed.

```php
// ✅ Correct — follows PSR-11 convention
public function __construct(private readonly \Psr\Container\ContainerInterface $container) {}
```

---

## Accessing Services

```php
// By class name (preferred — PhpStorm infers the return type)
$metadata = $container->get(\Atro\Core\Utils\Metadata::class);

// By alias
$metadata = $container->get('metadata');
```

---

## Registering Services

### 1. Typed Constructor — no registration needed

The most common case. If your class has a typed constructor, the SM resolves and injects all dependencies automatically. No factory, no registration, no configuration required.

```php
namespace Pim\Services;

use Atro\Core\Utils\Config;
use Espo\ORM\EntityManager;
use Atro\Core\Utils\FileManager;

class ProductExporter
{
    public function __construct(
        private readonly EntityManager $em,
        private readonly Config $config,
        private readonly FileManager $fileManager
    ) {}
}

// Anywhere in the application:
$exporter = $container->get(ProductExporter::class);
```

The SM walks the constructor parameters, resolves each type via the alias map, and injects the shared instances.

---

### 2. Named Factory — explicit instantiation logic

When construction requires runtime decisions, implement `Laminas\ServiceManager\Factory\FactoryInterface`:

```php
namespace MyModule\Core\Factories;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class StorageFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): StorageInterface
    {
        return match($container->get('config')->get('storageDriver')) {
            's3'    => new S3Storage($container->get('config')),
            'local' => new LocalStorage($container->get('fileManager')),
        };
    }
}
```

Register it in your module's `onLoad`:

```php
public function onLoad(): void
{
    $this->sm->setFactory('storage', StorageFactory::class);
    $this->sm->setAlias(StorageInterface::class, 'storage');
}
```

Now `$container->get('storage')` and `$container->get(StorageInterface::class)` both return the same shared instance.

---

### 3. Abstract Factory — handle a family of services by pattern

Use an abstract factory when a group of services share a common creation pattern and you don't want to register each one individually.

```php
namespace MyModule\Core\Factories;

use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Psr\Container\ContainerInterface;

class DriverAbstractFactory implements AbstractFactoryInterface
{
    public function canCreate(ContainerInterface $container, string $requestedName): bool
    {
        // Handles any service name matching 'driver.*'
        return str_starts_with($requestedName, 'driver.');
    }

    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): DriverInterface
    {
        // 'driver.pdf'  → PdfDriver
        // 'driver.xlsx' → XlsxDriver
        $type  = ucfirst(substr($requestedName, 7));
        $class = "MyModule\\Drivers\\{$type}Driver";

        return new $class($container->get('config'));
    }
}
```

Register once — covers all matching service names:

```php
public function onLoad(): void
{
    $this->sm->configure([
        'abstract_factories' => [DriverAbstractFactory::class],
    ]);
}

// Usage — no individual registration needed:
$container->get('driver.pdf');
$container->get('driver.xlsx');
```

---

### 4. Aliases — map interfaces to implementations

Aliases let you decouple consumers from concrete class names:

```php
public function onLoad(): void
{
    $this->sm->setFactory('mailer', MailerFactory::class);

    // Interface → service name
    $this->sm->setAlias(MailerInterface::class, 'mailer');

    // Alternative name → service name
    $this->sm->setAlias('emailSender', 'mailer');
}
```

All three resolve to the same shared instance:

```php
$container->get('mailer');
$container->get(MailerInterface::class);
$container->get('emailSender');
```

---

### 5. Non-shared services

By default every service is a singleton (shared). To get a fresh instance on every `get()`:

```php
public function onLoad(): void
{
    $this->sm->setFactory('reportBuilder', ReportBuilderFactory::class);
    $this->sm->configure(['shared' => ['reportBuilder' => false]]);
}
```

---

## Full module registration example

```php
namespace MyModule;

use Atro\Core\ModuleManager\AbstractModule;

class Module extends AbstractModule
{
    public static function getLoadOrder(): int
    {
        return 5000;
    }

    public function onLoad(): void
    {
        // Named factory
        $this->sm->setFactory('storage', \MyModule\Core\Factories\StorageFactory::class);

        // Interface alias
        $this->sm->setAlias(\MyModule\Contracts\StorageInterface::class, 'storage');

        // Abstract factory for a whole family of services
        $this->sm->configure([
            'abstract_factories' => [\MyModule\Core\Factories\DriverAbstractFactory::class],
        ]);

        // Non-shared service
        $this->sm->setFactory('queryBuilder', \MyModule\Core\Factories\QueryBuilderFactory::class);
        $this->sm->configure(['shared' => ['queryBuilder' => false]]);
    }
}
```

---

## Legacy support (to be removed)

The old `\Atro\Core\Factories\FactoryInterface` is still supported for backwards compatibility. Existing modules using it will continue to work.

**Writing new code using the legacy factory approach is strongly discouraged.** Always use the native Laminas ServiceManager patterns described above.

```php
// ❌ Legacy factory — do not use in new code
class MyFactory implements \Atro\Core\Factories\FactoryInterface
{
    public function create(\Atro\Core\Container $container): MyService { ... }
}

// ✅ Correct — native SM factory
class MyFactory implements \Laminas\ServiceManager\Factory\FactoryInterface
{
    public function __invoke(\Psr\Container\ContainerInterface $container, string $name, ?array $options = null): MyService { ... }
}

// ✅ Correct — registration in onLoad
$this->sm->setFactory('myService', MyFactory::class);
```

---

## Further reading

- [Laminas ServiceManager documentation](https://docs.laminas.dev/laminas-servicemanager/)
- [PSR-11: Container Interface](https://www.php-fig.org/psr/psr-11/)
- [Creating a Module](../../30.own-modules)
- [Entity Services](../15.services)
