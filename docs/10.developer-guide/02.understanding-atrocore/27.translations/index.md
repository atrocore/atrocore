---
title: Translations
---

**AtroCore** provides a flexible localization system that supports multiple languages. Translation data is stored in **JSON** files within the `Resources/i18n` folder of the core and each module, and is synchronized to the `Translation` database entity via the `refresh translations` command.

Each language has its own subfolder named after the language code (e.g., `en_US`). Supported translation categories:

* **`Global.json`** — global labels, scope names, and universal strings.
* **`{EntityName}.json`** — entity-specific translations: `fields`, `options`, `labels`, `messages`, `tooltips`, `exceptions`.

**Example: `en_US/Example.json`**

```json
{
  "fields": {
    "type": "Type"
  },
  "options": {
    "type": {
      "simple": "Simple",
      "composite": "Composite"
    }
  },
  "labels": {
    "myLabel": "My label"
  },
  "messages": {
    "myMessage": "My message"
  },
  "tooltips": {
    "type": "Use to define the type of example"
  },
  "exceptions": {
    "errorWhenCreating": "There was an error when creating"
  }
}
```

Translations from the core and all modules are merged during system initialization. Any module can override translations for any entity.

---

## Synchronizing Translations

JSON files are the source of truth for system-provided translations. They are synchronized to the database via:

```bash
php console.php refresh translations
```

This command loads all i18n JSON files from all modules, merges them, and upserts the result into the `Translation` entity. Records marked as `isCustomized = true` (user-edited via UI) are never overwritten.

> Learn more about the `ReferenceData` entity type in the [Entities section](../05.entities/index.md).

---

## Reading Translations (Language utility)

The `Language` utility (`\Atro\Core\Utils\Language`) is responsible **only for reading** — translating keys into strings for the current user's locale. It uses **lazy loading**: each key is fetched from the database on first access and cached in memory for the duration of the request.

Use the `language` service from the container:

```php
/** @var \Atro\Core\Utils\Language $language */
$language = $container->get('language');

// Translate a label
$text = $language->translate('type', 'fields', 'Example');
// Returns "Type" for en_US

// Translate a static-list option
$text = $language->translateOption('simple', 'type', 'Example');
// Returns "Simple" for en_US
```

**Parameters:**

| Method | Parameters |
|---|---|
| `translate($name, $category, $scope)` | `$name` — translation key; `$category` — `fields`, `labels`, `tooltips`, etc.; `$scope` — entity name or `Global` |
| `translateOption($value, $field, $scope)` | `$value` — option code; `$field` — field name; `$scope` — entity name |

The language is resolved automatically from the current user's locale. To override it explicitly:

```php
$language->setLanguage('de_DE');
// or by locale ID:
$language->setLocale($localeId);
```

In the frontend, the same keys are available on any view:

```js
let text = this.translate('type', 'fields', 'Example');
```

---

## Writing Translations (Translation repository)

To create, update, or delete translations in code use the `Translation` repository directly. The `Language` utility does **not** write to the database.

```php
/** @var \Atro\Repositories\Translation $repo */
$repo = $entityManager->getRepository('Translation');

// Create or update a translation for the current user's language
$repo->setTranslation($scope, $category, $name, $value);

// Delete a translation
$repo->deleteTranslation($scope, $category, $name);

// Create or update a single enum/multiEnum option translation
$repo->setTranslationOption($scope, $field, $optionCode, $value);

// Replace all option translations for a field (add new, update changed, delete removed)
$repo->setTranslationOptions($scope, $field, $valuesArray);

// Delete a single option translation
$repo->deleteTranslationOption($scope, $field, $optionCode);
```

**`setTranslationOptions`** performs a full replace: options absent from `$valuesArray` are deleted, new ones are inserted, existing ones are updated. Use it when saving the complete set of translated options for a field.

**Example — saving a field label and tooltip from a repository:**

```php
$translationRepo = $entityManager->getRepository('Translation');

$translationRepo->setTranslation($entity->get('entityId'), 'fields', $entity->get('code'), $entity->get('name'));
$translationRepo->setTranslation($entity->get('entityId'), 'tooltips', $entity->get('code'), $entity->get('tooltipText'));
```

> Translations written via the repository are marked `isCustomized = true` and will not be overwritten by `refresh translations`.

---

## Dynamic Translations (Language Resolvers)

Some labels are not stored anywhere – they are derived from metadata: language suffixes of multilang fields
(`nameDeDe`), the `From` and `To` parts of a range field, association labels, thumbnail fields and so on.
A Language Resolver builds such a label on the fly, for one requested key at a time, so nothing has to be
generated in advance or kept in memory.

Resolvers run inside `\Atro\Core\Utils\Language`: `translate()` and `translateOption()` ask each resolver
whether it owns the requested key before falling back to the `Translation` table, and `getAll()` collects every
key each resolver can build to serve the frontend.

### Implementing a Resolver

Extend `\Atro\Core\LanguageResolvers\AbstractLanguageResolver` and implement three methods:

| Method | Purpose |
|---|---|
| `supports(TranslationKeyDTO $key)` | Whether the resolver owns the key – must stay cheap, it runs for every uncached key |
| `resolve(TranslationKeyDTO $key)` | The translation for that single key, or `null` |
| `getKeys()` | Every key the resolver can build – keys only, no values |

A resolver never sees another resolver's output and never depends on the order it is registered in. It declares
which keys it owns and how to build one; `Language` builds the whole tree by resolving every declared key
through the same path `translate()` uses, so a translation is produced in exactly one place.

```php
<?php
namespace {ModuleNamespace}\LanguageResolvers;

use Atro\Core\LanguageResolvers\AbstractLanguageResolver;
use Atro\DTOs\TranslationKeyDTO;

class ExampleType extends AbstractLanguageResolver
{
    public function supports(TranslationKeyDTO $key): bool
    {
        return $key->getScope() === 'Example' && $key->getCategory() === 'fields' && $key->getName() === 'typeLabel';
    }

    public function resolve(TranslationKeyDTO $key): ?string
    {
        return $this->language->translate('type', 'fields', 'Example') . ' (' . $this->language->translate('Label') . ')';
    }

    public function getKeys(): iterable
    {
        yield new TranslationKeyDTO('Example', 'fields', 'typeLabel');
    }
}
```

Register the class in `app/Resources/metadata/app/languageResolvers.json`:

```json
{
  "exampleType": {
    "className": "\\{ModuleNamespace}\\LanguageResolvers\\ExampleType"
  }
}
```

Every key `getKeys()` returns must also pass `supports()` – declare a key only when the resolver really owns it,
otherwise it silently disappears from the tree served to the frontend.

### The Stored Translation Wins

A resolver waits for the database. If the key already has a translation for the current locale, that value is
used and the resolver is never asked – so a label a user edited in the UI is never overwritten by a computed one.
Most resolvers only ever fill a gap and need to say nothing about this.

The exception is a resolver that **builds its value out of the stored one** – appending a unit, a language name,
a part marker. Such a resolver has to run before the stored value is returned, otherwise the plain value wins and
the decoration never happens. It declares itself:

```php
public function decoratesStoredTranslation(): bool
{
    return true;
}
```

`CombinedField` and `MultilangField` are the only two: `Width` stored for a field split into a number and a unit
has to be shown as `Width (Float)`, and in a locale that displays content languages a multilang field label gains
` / English` to say which language its value is in. Neither label can be stored, because both depend on metadata
or on the current locale.

To decorate a label rather than replace it, ask the pipeline what the key would be without this resolver:
`$this->language->findTranslation($key, $this)`. That is how a multilang field label gets the main language
appended to whatever the other resolvers called it, and it is also what keeps such a resolver clear of the cycle
guard below.

### Choosing A Lookup

The language matters. A key translated into English only is not translated for a German locale, so a resolver
still runs there and may build a German label out of other German values; the English value is used just when
nothing else answers. Three lookups serve that distinction:

| Lookup | Returns |
|---|---|
| `$this->language->getStoredTranslationInCurrentLanguage($key)` | the stored value of the current language alone, `null` when that language does not translate the key |
| `$this->language->getStoredTranslation($key)` | the stored value, falling back to the other languages of the locale |
| `$this->language->findTranslation($key)` | the fully resolved value, resolvers included; `null` instead of echoing the key back |

A resolver that mixes languages produces labels half in one language and half in another, so build a label out of
`getStoredTranslationInCurrentLanguage()` values and return `null` when they are missing.

### Asking For Other Keys, And The Cycle Guard

A resolver may ask for any other key while building one – that is how a label gets composed out of others. The
danger is a chain that comes back to the key it started from: `resolve()` asks for the key it is building, that
call enters `resolve()` again, and the request never finishes. Without a guard this is not a clean error – the
request hangs until the web server or the CLI timeout kills it, with nothing in the log to say why.

So `Language` remembers which keys are currently being built. When a key is asked for a second time while still
unfinished, the chain is cut: the stored value is returned as-is and a warning names the key:

```
Language: cyclic translation resolving detected for 'Global.labels.someKey'.
```

**What to do when it appears.** It is a bug in a resolver, not a data problem – the page keeps working, but that
one label falls back to whatever the database holds. The message names the key, so:

1. find the resolver whose `supports()` matches that key;
2. look at its `resolve()` for a call that asks for the same key again;
3. if it needs the value the *other* resolvers would give for that key – to decorate it, for instance – pass
   itself as the second argument: `$this->language->findTranslation($key, $this)`. That excludes the resolver
   from its own lookup, so there is no cycle and the guard stays quiet.

The guard never fires in a healthy system: resolving the whole tree – around 7500 keys – triggers it zero times.

### Language Listeners (Deprecated)

Before resolvers, a module shaped translations through `Listeners/Language.php` with a `modify()` method. That
hook still runs, so existing modules keep working, but it is deprecated – write a resolver instead.

Two things to know about it:

- **It only reaches the tree served to the frontend.** `Language::getAll()` dispatches it; `translate()` does not,
  and neither does `preload()`. So a label a listener adds is visible in the UI but invisible to PHP code asking
  for that key – which is exactly the split resolvers were introduced to remove.
- **It now receives one language at a time.** The event used to carry every language at once; it now carries
  `[<current language> => <tree>]`. A listener that iterates – `foreach ($data as $locale => $rows)`, as every
  known one does – behaves the same. A listener that reads a hard-coded `$data['en_US']` as a fallback will not
  find it when the interface runs in another language.

To migrate, split the listener into resolvers: one per rule it applies, each declaring the keys it owns.

### Scopes That Borrow From Another Scope

A derivative scope – one declaring `scopes.<Scope>.primaryEntityId` or `scopes.<Scope>.derivativeForRelation` –
falls back to the scope it derives from, and `UserProfile` borrows from `User` by the same rule. `Language`
applies it itself, so a resolver never has to care.

The order a key is answered in:

1. resolvers that decorate the stored value
2. the value stored for this scope in the language of the current locale
3. the scope it derives from, resolved the same way
4. every other resolver
5. the value stored for this scope in the other languages of the locale
6. the same key in the `Global` scope

Steps 3 and 4 are in that order on purpose: what the parent scope calls a field is a statement about this exact
field, while a resolver at step 4 only knows a generic default – naming a link after the entity it points to, for
instance. So a derivative shows the same label as its parent instead of a generic one.
