---
title: Expression Language
---

## Overview

Conditions in AtroCore are written in the [Symfony Expression Language](https://symfony.com/doc/current/components/expression_language.html). Whenever an Action or a Workflow needs to decide whether it may run, the rule behind that decision is an expression.

AtroCore uses two scripting tools side by side, each aimed at its own kind of task:

| Tool | Task | Where it is used |
|---|---|---|
| [Twig](../80.twig-tutorial/index.md) | Producing content | Script fields, export and import feed templates, notification and e-mail bodies, PDF markup, merging and consolidation scripts, action payloads |
| Expression Language | Evaluating a rule | Conditions of Actions and Workflows |

Twig is a template engine: it renders text from data. The Expression Language is an expression evaluator: it computes one value from data. A condition is exactly one value — a boolean — which is the shape the Expression Language is built for, and that gives it two properties conditions rely on:

* **A condition is a single expression.** There is nothing to output and no variable to assign; the expression itself is the result, cast to `bool`.
* **Expressions are compiled.** When the record is saved, the expression is turned into a plain PHP class under `data/custom-code/Compiled/Condition/`. Evaluating the condition later means instantiating that class and calling one method. This matters because conditions are among the most frequently executed code in the system: a workflow condition runs on every create, update and delete of its trigger entity.

## Where expressions are used

An expression sees exactly the properties of its context class — nothing else. Each consumer defines its own context:

| Consumer | Context class |
|---|---|
| Action | `Atro\Core\ExpressionLanguage\Compiled\ActionConditionContext` |
| Workflow | `Workflows\Core\ExpressionLanguage\Compiled\WorkflowConditionContext` |

In both cases the condition is activated by setting `Conditions Type` to `Expression` and writing the rule into the `Conditions Expression` field.

### Action condition variables

| Variable | Type | Description |
|---|---|---|
| `entity` | `Entity` | The source entity of the action. See [Working with the entity object](#working-with-the-entity-object) |
| `uiRecord` | `array\|null` | The record as it currently looks in the edit form, including unsaved changes. Filled only when the action is invoked from the user interface |
| `uiRecordFromName` | `string\|null` | The entity type the user navigated from, when the form was opened in the context of another record |
| `uiRecordFrom` | `array\|null` | The record the user navigated from |

The three `uiRecord*` variables are `null` for actions executed from a workflow, a scheduled job or the API. Guard them before use.

### Workflow condition variables

| Variable | Type | Description |
|---|---|---|
| `entity` | `Entity` | The record that triggered the workflow. See [Working with the entity object](#working-with-the-entity-object) |
| `user` | `Entity` | The current user; the same kind of object as `entity` |
| `importJobId` | `string\|null` | The ID of the import job, when the change comes from an import; `null` otherwise |

Examples:

```
entity.isAttributeChanged('status')
```

```
entity.get('status') == 'completed' and entity.get('priority') > 2
```

```
lowercase(entity.get('name')) matches '/^atro/' and entity.get('isActive')
```

The result of the expression is cast to `bool`. There is no variable to assign and nothing to output — the expression *is* the result.

## Working with the entity object

`entity` — and `user` in a workflow condition — is an ORM entity object, not an array. A dot in the Expression Language compiles to a method call, so `entity.get('sku')` becomes `$entity->get('sku')` in the generated class. Every public method of the entity is therefore reachable, but a condition needs four of them:

| Expression | Returns | Description |
|---|---|---|
| `entity.get('field')` | mixed | The value of an attribute or field |
| `entity.has('field')` | bool | Whether the attribute is set on the record |
| `entity.isAttributeChanged('field')` | bool | Whether the value differs from the one loaded from the database |
| `entity.isNew()` | bool | Whether the record is being created |

`entity.get('id')` is a special case, answered before anything else, so it works on every record.

### Related records

`entity.get('brand')` does not return an id — it returns the related **record object**, and it fetches that record from the database on the spot. Two things follow from this.

**Every relation costs a query.** The related record is loaded the first time the relation is read and kept on the entity for the rest of the request. A workflow condition, however, runs on every create, update and delete of its trigger entity, so reading a relation in a condition means an extra query on every one of those operations.

**A chain breaks on an empty relation.** The expression is compiled into plain PHP, so

```
entity.get('brand').get('name') == 'Atro'
```

becomes `$entity->get("brand")->get("name") == 'Atro'`. On a record without a brand that is a fatal `Call to a member function get() on null` — the operation fails instead of the condition returning `false`.

Prefer the flat field. A `belongsTo` link keeps its id in a regular column, so comparing identity needs neither a query nor a guard:

```
entity.get('brandId') == '019e727f-12ed-72a6-952f-7d089df22710'
```

When you genuinely need a field of the related record, guard the chain. `and` compiles to PHP `&&` and short-circuits, so the right-hand side is never reached for a record without a brand:

```
entity.get('brandId') and entity.get('brand').get('name') == 'Atro'
```

### entity and uiRecord are different states

`entity` is the record as it is stored in the database. It does **not** contain the values the user has just typed into the form.

This is worth spelling out, because the two condition types differ here. `Atro\ActionTypes\AbstractAction::canExecute()` applies the form data to the entity in the `basic` branch — `$sourceEntity->set($input->uiRecord)` — before the conditions are checked. The `expression` branch does not: its context is built straight from the stored record, and the form data stays in `uiRecord`.

A condition that has to react to what the user is editing right now therefore reads `uiRecord`, not `entity`:

```
uiRecord and uiRecord['status'] == 'draft'
```

Note the square brackets. `uiRecord` and `uiRecordFrom` are arrays, not entity objects — `uiRecord['status']`, never `uiRecord.status`. The leading `uiRecord and` guards against them being `null` outside the user interface.

## Validation

Expressions are validated before the record is written. `Atro\Repositories\Action::validateConditionsExpression()` runs in `beforeSave` and:

* rejects an empty expression with `BadRequest` and the `expressionCannotBeEmpty` message;
* calls `ExpressionLanguage::lint()` with the list of allowed variable names, and converts a `SyntaxError` into a `BadRequest`.

Because `lint()` receives the variable names, referencing a variable that the context does not provide is reported as a syntax error at save time instead of failing at runtime. `Workflows\Repositories\Workflow::validateConditionsExpression()` is the mirror implementation for workflows.

## The generated class

This is a real generated file for the action condition `entity.get('isActive') == true`:

```php
<?php

namespace Compiled\Condition;

/**
 * GENERATED — do not edit. Regenerated from expression() below.
 */
final class Afc500c0a162d9f258ec914e24ae6458b implements \Atro\Core\ExpressionLanguage\Compiled\CompiledActionCondition
{
    public static function expression(): string
    {
        return 'entity.get(\'isActive\') == true';
    }

    public function eval(\Atro\Core\ExpressionLanguage\Compiled\ActionConditionContext $context): bool
    {
        $entity = $context->entity;

        return (bool) (($entity->get("isActive") == true));
    }
}
```

Two things to note:

* `expression()` returns the original source of the expression. This is what the UI displays when you open the record — see [Deployment notes](#deployment-notes).
* `eval()` unpacks only the context properties the expression actually uses, and the expression itself became plain PHP. Nothing of the original syntax has to be interpreted at runtime.

## Runtime evaluation

`Atro\ActionTypes\AbstractAction::canExecute()` switches on `conditionsType`. The `expression` branch resolves the class name from the repository, asserts the contract, builds the context and evaluates:

```php
if ($action->get('conditionsType') === 'expression') {
    $className = ActionRepository::getCompiledExpressionFullClassName($action);
    if (!is_a($className, CompiledActionCondition::class, true)) {
        throw new Error("'$className' must be an instance of " . CompiledActionCondition::class);
    }

    $context = new ActionConditionContext(
        $this->getSourceEntity($action, $input),
        $input->uiRecord ?? null,
        $input->uiRecordFromName ?? null,
        $input->uiRecordFrom ?? null
    );

    return $this->container->get($className)->eval($context);
}
```

The same switch also contains a `script` branch, kept for backward compatibility with conditions created before the Expression Language was introduced. The `Conditions Script` field is read-only, and `script` is no longer offered as an option in the UI.

## Registering custom functions

Beyond the operators and literals of the Expression Language itself, expressions may call functions. A module can contribute its own.

### 1. Implement the handler

A function handler implements `Atro\Core\ExpressionLanguage\Functions\FunctionInterface`:

```php
interface FunctionInterface
{
    public function compile(string ...$arguments): string;

    public function evaluate(mixed ...$arguments): mixed;
}
```

The two methods serve the two modes of the component:

* `compile()` receives the **already compiled PHP source** of each argument as a string, and returns a PHP expression as a string. This is what ends up inside the generated class.
* `evaluate()` receives the **actual values** and returns the result. It is used when an expression is interpreted rather than compiled.

Both must produce the same result. The core `Lowercase` function is the reference implementation:

```php
<?php

declare(strict_types=1);

namespace Atro\Core\ExpressionLanguage\Functions;

class Lowercase implements FunctionInterface
{
    public function evaluate(mixed ...$arguments): mixed
    {
        return is_string($arguments[0]) ? strtolower($arguments[0]) : $arguments[0];
    }

    public function compile(string ...$arguments): string
    {
        return sprintf('(is_string(%1$s) ? strtolower(%1$s) : %1$s)', $arguments[0]);
    }
}
```

### 2. Register it in metadata

Add the function to `Resources/metadata/app/expressionLanguageFunctions.json` of your module:

```json
{
  "slugify": {
    "handler": "\\MyModule\\ExpressionLanguage\\Functions\\Slugify"
  }
}
```

The top-level key is the name users write in expressions — `slugify(entity.get('name'))`. `handler` is the fully qualified class name and is the only supported property; an entry with an empty `handler` is skipped.

Metadata is merged across modules in load order, so a module can both add new functions and override the `handler` of an existing one. The instance-level `data/metadata` directory is applied last and wins over every module.

The core ships `lowercase`, `uppercase` and `md5` in `src/atrocore/app/Atro/Resources/metadata/app/expressionLanguageFunctions.json`.

### 3. Keep the function isolated

The purpose of the Expression Language is to answer a question or compute a value quickly and safely. That holds only as long as a function stays a self-contained transformation of its arguments: it takes what it was given, computes a result, and touches nothing else. Such a function is predictable, cheap to evaluate on every record, and cannot break the operation whose condition is being checked.

Keep your functions independent of the rest of the system. Work with the arguments you receive and use plain PHP to compute the result — no services, no database, no filesystem, no HTTP calls.

Handlers are created by the DI container, so injecting a service is technically possible:

```php
public function __construct(private readonly EntityManager $entityManager)
{
}
```

We advise against it. A function that reaches into the system carries the cost and the failure modes of whatever it reaches into, and a condition evaluated on every create, update and delete is the worst place for both. There are cases where it is genuinely unavoidable — if you are in one of them, keep the dependency to a minimum and be deliberate about it, but treat it as the exception rather than the pattern.

Registration itself is cheap: every function is registered behind a closure that resolves the handler **on first use**, so declaring a hundred functions costs nothing until an expression actually calls one.

### Writing compile()

`compile()` builds source code, so the usual rule of code generation applies: **never repeat an argument in the output**. `Lowercase::compile()` above inserts `%1$s` three times, which is acceptable for a plain field access but wrong for anything else — an argument such as `entity.get('name')` or a nested call to another function would be executed three times in the generated class.

Substitute each argument once. When the value is needed more than once, assign it first — for example by wrapping the result in an immediately invoked closure:

```php
public function compile(string ...$arguments): string
{
    return sprintf('(static fn($v) => is_string($v) ? strtolower($v) : $v)(%s)', $arguments[0]);
}
```

Also keep in mind that the compiled code is written verbatim into a file: return an expression, not a statement, and do not rely on anything being in scope apart from the arguments you were given.

## Adding a new expression context

The mechanism is not limited to Action and Workflow conditions. A new consumer needs three pieces, copied from `Atro\Repositories\Action`:

**1. A context class** — a `final readonly class` whose public properties are exactly the variables the expression may use:

```php
final readonly class MatchingConditionContext
{
    public function __construct(
        public Entity $entity,
        public ?array $candidate = null
    ) {
    }
}
```

**2. An interface** extending `CompiledExpression` that fixes the evaluation signature:

```php
interface CompiledMatchingCondition extends CompiledExpression
{
    public function eval(MatchingConditionContext $context): bool;
}
```

**3. Repository wiring** — the `CONDITIONS_EXPRESSION_NAMES` constant listing the variable names, the class-name helpers, and the validate / save / delete hooks in `beforeSave`, `afterSave` and `afterRemove`. Since all consumers share the `Compiled\Condition` namespace, make sure your class names cannot collide with those of an existing consumer.

> The list of variables lives in two places — the properties of the context class and the `CONDITIONS_EXPRESSION_NAMES` constant of the repository. Adding a variable requires changing both.

## Resources

* [Symfony Expression Language component](https://symfony.com/doc/current/components/expression_language.html)
* [Expression syntax reference](https://symfony.com/doc/current/reference/formats/expression_language.html)
* [Twig Templating](../80.twig-tutorial/index.md) — the template engine used for content generation
* [Custom Condition Types](../70.custom-condition-type/index.md) — when an expression is not enough
