---
title: Custom Condition Types
taxonomy:
    category: docs
---

## Overview

Condition Types define the logic that determines **whether an Action is allowed to execute** under specific conditions. This mechanism ensures that actions are only triggered when predefined rules are met.

Each Action has a `Conditions Type` field that determines how the execution rules are defined. The system supports two built-in condition types and also allows for custom ones.

## Condition Types

### Basic

When the `Basic` type is selected, a simple rule builder UI is displayed, allowing users to define conditions using common logical comparisons. These conditions are evaluated based on the current entity context.

Examples:
- Status is "Completed"
- Quantity > 10

### Script

When the `Script` type is selected, users can write condition logic using the Twig templating language. This provides more flexibility and allows referencing complex expressions and conditions.

Example:
```twig
{% set proceed = entity.status == 'completed' and entity.priority > 2 %}
{{ proceed }}
```

## Custom Condition Types

For advanced use cases where neither `Basic` nor `Script` types are sufficient or performant — for example, when complex data relationships or multiple database lookups are required — the system provides support for **Custom Condition Types**.

## Use Cases for Custom Condition Types

- Determine eligibility based on relationships across multiple entities.
- Implement role-based restrictions based on a dynamic permission model.
- Perform cross-entity validation or join-based lookups in the database.
- Evaluate external data or trigger remote validation services.

## Best Practices

- **Optimize Logic:** Keep the `proceed()` method efficient and avoid unnecessary database queries.
- **Use Services:** Leverage existing service classes to encapsulate complex logic or data access.
- **Keep It Focused:** Design each condition type for a single, clear purpose to improve reusability and maintainability.

---

## Creating a Custom Condition Types

To create a new custom condition type, run the following command on the server:

```bash
php console.php create condition type OwnCondition
```

Here, `OwnCondition` is the name of your custom condition class.

This command generates a PHP file with the following structure:

```php
<?php

namespace CustomConditionTypes;

use Atro\ConditionTypes\AbstractConditionType;

class OwnCondition extends AbstractConditionType
{
    public static function getTypeLabel(): string
    {
        return 'OwnCondition';
    }
    
    public static function getEntityName(): string
    {
        return 'Product';
    }

    public function proceed(\stdClass $input): bool
    {
        return true;
    }
}
```

## Key Methods

| Method Signature                                          | Description                                                                 |
|----------------------------------------------------------|-----------------------------------------------------------------------------|
| `getTypeLabel()`          | Returns the label shown in the UI when selecting this condition type. |
| `getEntityName()`         | Defines which entity type this condition is applicable for. The system uses this to link the condition to the correct context. |
| `proceed(\stdClass $input)` | Contains the main logic that determines whether the action is allowed to execute. |