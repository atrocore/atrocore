---
title: Twig Templating
taxonomy:
    category: docs
---
## Overview

AtroCore integrates Twig templating throughout the platform, enabling developers to create dynamic content and implement business logic in script fields, workflows and export feeds.

## How to Use Twig in AtroCore

### Available Contexts

Twig is accessible in several contexts, each with specific variables and use cases:

| Context | Purpose | Available Variables |
|---------|---------|-------------------|
| Script Fields | Dynamic field content | `config`, `entity` |
| Workflow Conditions | Business rule evaluation | `entity`, `user`, `importJobId` |
| Workflow Actions | Data manipulation | `entity`, `triggeredEntity` |
| Export Feed Templates | Data formatting | `entities`, `feed` |
| Action Scripts | Bulk operations | `sourceEntities`, `triggeredEntity` |

### System Configuration Access

The `config` variable provides access to system settings:

```twig
{{ config.siteUrl }}
{{ config.defaultLanguage }}
{{ config.timeZone }}
```

## Monaco Editor Features

AtroCore uses Monaco Editor (VS Code's editor) for enhanced development experience:

### IntelliSense and Auto-completion

- **Function Suggestions**: Auto-complete shows available custom functions
- **Filter Completion**: Type `|` to see available Twig filters
- **Context-Aware**: Suggestions adapt to your current context

### Development Tools

- **Syntax Highlighting**: Full Twig syntax support
- **Error Detection**: Real-time validation and error highlighting
- **Code Formatting**: Auto-indentation and beautification
- **Documentation on Hover**: Function signatures and parameter info

### Getting Help

1. **Hover Documentation**: Hover over functions to see signatures and examples
2. **Ctrl+Space**: Trigger IntelliSense manually
3. **Error Squiggles**: Red underlines show syntax issues

## Context-Specific Examples

### Script Fields
```twig
{# Access entity properties #}
{{ entity.name }} - {{ entity.createdAt|date('Y-m-d') }}

{# Use system config #}
Generated on {{ config.siteUrl }}
```

### Workflow Conditions
```twig
{# Boolean expression for workflow triggers #}
{{ entity.isActive and user.id != 'system' and importJobId is empty }}
```

### Export Feed Templates
```twig
[
  {% for entity in entities %}
    {
      "id": "{{ entity.id }}",
      "name": "{{ entity.name|escapeDoubleQuote }}"
    }{% if not loop.last %},{% endif %}
  {% endfor %}
]
```

## Where to find twig functions and filters

Twig functions and filters are registered in metadata files that determine their availability:

### Global Functions & Filters (All Contexts)
- **Functions**: `Resources/metadata/twig/functions.json`
- **Filters**: `Resources/metadata/twig/filters.json`

These are available everywhere Twig is used (script fields, workflows, actions, etc.)

### Export Feed Specific Functions & Filters
- **Functions**: `Resources/metadata/app/twigFunctions.json`
- **Filters**: `Resources/metadata/app/twigFilters.json`

These are only available in Export Feed templates for specialized data export functionality.

#### Module Extensions
Each module can extend Twig capabilities by adding entries to their own metadata files

## Adding Custom Twig Functions

### Creating a New Twig Function

1. **Create the Function Class**:

```php
<?php
namespace CustomModule\TwigFunction;

use Atro\Core\Twig\AbstractTwigFunction;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;

class FindEntities extends AbstractTwigFunction
{
    protected EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function run(string $entityName, array $where = [], string $orderField = 'id', string $orderDirection = 'ASC', int $offset = 0, int $limit = \PHP_INT_MAX, bool $withDeleted = false): EntityCollection
    {
        return $this->entityManager->getRepository($entityName)
            ->where($where)
            ->order($orderField, $orderDirection)
            ->limit($offset, $limit)
            ->find(['withDeleted' => $withDeleted]);
    }
}
```

2. **Register in metadata** (`CustomModule/Resources/metadata/twig/functions.json`):

```json
{
  "findEntities": {
    "handler": "\\CustomModule\\TwigFunction\\FindEntities",
    "insertText": "findEntities(${1:entityName}, ${2:{}})"
  }
}
```

`handler` property is the defined twig function class name.

`insertText` property is the text for autocompletion in monaco editor.

3. **Usage in Twig**:

```twig
{% set activeProducts = findEntities('Product', {isActive: 'true'}, 'name', 'ASC', 0, 10}) %}
```

### Function Development Guidelines

- Extend `Atro\Core\Twig\AbstractTwigFunction` base class
- Use dependency injection for services
- Handle errors gracefully with try-catch
- Return appropriate data types
- Document parameters and return values

## Adding Custom Twig Filters

### Creating a New Twig Filter

1. **Create the Filter Class**:

```php
<?php
namespace CustomModule\TwigFilter;

use Atro\Core\Twig\AbstractTwigFilter;

class Md5 extends AbstractTwigFilter
{
    public function filter($value)
    {
        if (!is_string($value)) {
            return null;
        }

        return md5($value);
    }
}
```

2. **Register in metadata** (`CustomModule/Resources/metadata/twig/filters.json`):

```json
{
  "md5": {
    "handler": "\\CustomModule\\TwigFilter\\Md5"
  }
}
```

3. **Usage in Twig**:

```twig
{{ fileContent | md5 }}
```

### Filter Development Guidelines

- Extend `Atro\Core\Twig\AbstractTwigFilter` base class
- First parameter is always the value being filtered
- No Support for additional parameters
- Return modified value of appropriate type
- Chain-friendly design

## Base Classes Reference

### AbstractTwigFunction

```php
abstract class AbstractTwigFunction
{
    public function setTemplateData(array $templateData): void
    public function getTemplateData(string $name)
    abstract public function run(...$args);
}
```

### AbstractTwigFilter

```php
abstract class AbstractTwigFilter extends Injectable
{
    public function setTemplateData(array $templateData): void
    public function getTemplateData(string $name)
    abstract public function filter($value);
}
```

### Data Conversion Function

convertMeasureUnit(float value, string measureId, string fromUnitId, string? toUnitId)
- Description: Converts a numerical value from one measurement unit to another using system configurations.
- Returns: float (converted value) or null if conversion is not possible or array (array of possible
conversions) if toUnitId is not specified.

Example:
```php
{% set convertedValue = convertMeasureUnit(100, 'currency', 'usd', 'eur') %}
```

## Best Practices

### Performance
- Cache expensive operations
- Avoid database queries in loops
- Use findEntities with limits for large datasets

### Security
- Always escape user input: `{{ userInput|escape }}`
- Validate parameters in custom functions
- Use `|raw` filter carefully and only for trusted content

### Maintainability
- Use descriptive function/filter names
- Document complex logic with comments

## Migration and Updates

When updating AtroCore:
- Check `twig` property in the metadata object for new functions/filters
- Review changelog for Twig-related changes

## Resources

- [Official Twig Documentation](https://twig.symfony.com/doc/)
