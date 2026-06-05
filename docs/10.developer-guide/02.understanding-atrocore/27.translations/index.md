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

## Dynamic Extension via Listener

Check the section [Extending with listener](../20.listeners) for more information.