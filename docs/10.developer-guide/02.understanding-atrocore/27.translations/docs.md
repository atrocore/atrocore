---
title: Translations
---

**AtroCore** provides a powerful and easy-to-use localization system to support multiple languages. All translation data is stored in **JSON** format within the `Resources/i18n` folder of the core or any custom modules.

Each language has its own folder named after the language code (e.g., `en_US` for US English). These folders contain two types of files:

* **`Global.json`**: Contains translations for global elements like entity names and universal labels.
* **`{EntityName}.json`**: Provides specific translations for a given entity, including `fields`, `options`, `labels`, `messages`, `tooltips`, and `exceptions`.

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
  "tooltips":{
      "type": "Use to define the type of example"
  },
  "exceptions": {
      "errorWhenCreating": "There was an error when creating"
  }
}
```

Translations from the core and all modules are loaded and merged during system initialization. Similar to [Metadata](../02.metadata/docs.md), any module can add or update translations for any entity.

## Regenerating System Translations

System translations are managed through a command-line utility. The translation data is not sourced directly from JSON files but is compiled into a database entity for performance and consistency.

**Command:**

```bash
php console.php refresh translations
```

**Process:**
The `refresh translations` command executes a process that:

* **Loads:** All translation data from the JSON files across all modules.
* **Merges:** The loaded data into a single, comprehensive translation set.
* **Persists:** This merged data into the `Translation` `ReferenceData` entity.

This approach ensures the application has a single source of truth for all localization strings, which is the `Translation` entity.

> Learn more about the `ReferenceData` entity type in the [Entities section](../05.entities/docs.md).

-----

## Accessing Translations

Use the `language` service from the container to access translations.

```php
/** @var Espo\Core\Utils\Language $language */
$language = $container->get('language');

$localizeField = $language->translate($tranlationKey, $categorie, $scope);
```

* `$tranlationKey` is a JSON key from within a translation file.
* `$category` is the top-level key (`fields`, `labels`, etc.).
* `$scope` is the entity name, or `Global` by default.

For the particular of translating options for static list fields use :

```php
/** @var Espo\Core\Utils\Language $language */
$language = $container->get('language');

$localizeOption= $language->translateOption($optionCode, $field, $scope);
```
* `$option` is a JSON key from within a translation file.
* `$field` static list field of the entity  `$scope`.
* `$scope` is the entity name

The system automatically detects the user's language or uses a fallback language from the configuration.

**Example Usage**:
Assuming the user's language is English (`en_US`):

```php
/** @var Espo\Core\Utils\Language $language */
$language = $container->get('language');

$localizeField = $language->translate('type', 'fields', 'Example');
// $localizeField is "Type"

$localizeLabel = $language->translate('myLabel', 'labels', 'Example');
// $localizeField is "My label"


$localizeOption = $language->translateOption('simple', 'type', 'Example');
// $localizeField is Simple
```

To set a specific language, use the `setLangage` method:

```php
/** @var Espo\Core\Utils\Language $language */
$language = $container->get('language');

$language->setLangage('de_DE'); // Sets the language to German
//or set the locale directly by using $language->setLocale($localeId)
$localizeText = $language->translate('type', 'fields', 'Example');
```

In the frontend, the `translate` method is also available in any view:

```js
let localizeField = this.translate('type', 'fields', 'Example');
// localizeField is "Type"
```

## Updating Translation in code
As we have seen above, the translations is persisted using the entity `Translation`.
The container service Language  convenient means to make modification like:
* Localize a new key
* Update an existing localization
* Delete a localization
**How it works***

```php
/** @var Espo\Core\Utils\Language $language */
$language = $container->get('language');

// create or update (if it exists)
$language->set($scope, $category, $key, $newValue);

// delete
$language->delete($scope, $category, $key);

// create or update and option's translation (if it exists)
$language->setOption($scope, $field, $optionName, $newValue);

// delete an option's translation
$language->deleteOption($scope, $field, $optionName);

// apply change
$language->save();
```

> Translation modified this way will not change after refreshing translation regeneration. To modify translation in the UI go to Administration / Translation

**Example:**

```php
/** @var Espo\Core\Utils\Language $language */
$language = $container->get('language');
 $language->set($entity->get('entityId'), 'tooltips', $entity->get('code'), $entity->get('tooltipText'));
```

## Dynamic extension via listener

Check the section [Extending with listener](../20.listeners) for more information.

