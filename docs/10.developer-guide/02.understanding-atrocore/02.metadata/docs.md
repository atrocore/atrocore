---
title: Metadata
taxonomy:
    category: docs
---

This page explains the concept of metadata in **AtroCore** and how developers can work with it to define and extend
entity structures.

## What is Metadata?

Metadata stores various useful data in the system that can be extended by any other modules, like data about entities,
including options, fields, relationships, frontend controllers, custom services and more.
These data are defined in **JSON** format in the `Resources/metadata` folder, located in the root directory of an
application or custom module.
Metadata is loaded and merged into a single structure during system initialization.

**AtroCore** uses metadata in two main layers:
* **Application Layer**: Metadata tells the system how to render fields in the UI (e.g., ```varchar``` → input,
  ```text``` → textarea), how to filter/search, and how to manage relationships.
* **Database Layer**: Metadata defines which fields and indexes should exist in the database.

## Common Metadata

Here are some commonly used metadata available in **AtroCore**:

| Data         | Description                                                                                                                                                                |
|--------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `scopes`     | Defines entity parameters, such as the type. Each file is  name  as `{EntityName}.json` (e.g. Product.json)                                                                |
| `entityDefs` | Describes the structure of an entity: its **fields**, **relationships**, **indexes**, and **collection behavior**. Each file is  name  as `{EntityName}.json`              |
| `clientDefs` | Stores data about frontend controllers, views, panels, and dashlets. This data determines the frontend behavior for entities. each file is  name  as `{EntityName}.json`   |
| `twig`       | Contains `filters.json` and `functions.json`, which register filters and functions used in script fields. Learn more in [Twig Templating](../../80.twig-tutorial/docs.md). |
| `app`        | Defines application-level data. with data like `acl.json` or `adminPanels` listing the links in the Admin panel                                                            |


## Storing new data

Let's store a new data  by creating the file `Resources/metadata/examples/images.json` with the content:

```json
{
    "image1": "image1-url",
    "image2": "image2-url",
    "thumbnail":{
        "iphone": "iphone-thumbnail-url"
    },
    "sizes" : [20, 40]
}
```

## Accessing Metadata

To access data from metadata,  get `metadata`  from the service container:

```php
/** @var \Atro\Core\Utils\Metadata $metadata */
$metadata = $container->get('metadata')

$image1 = $metadata->get(['examples', 'images', 'image1'])
// $image1 is "image1-url"
$image2 = $metadata->get(['examples', 'images', 'image2'])
// $$image2 is image2-url
$image3 = $metadata->get(['examples', 'images', 'image3'])
// $image3 is null
$image3 = $metadata->get(['examples', 'images', 'image3'], 'default-image3-url')
// $image3 is 'default-image3-url'
$thumbnail =  $image3 = $metadata->get(['examples', 'images', 'thumbnail', 'iphone'])
// is iphone-thumbnail-url
```
! The PhpStorm plugin [AtroCore Toolkit](https://plugins.jetbrains.com/plugin/28159-atrocore-toolkit) can help you with the autocompletion of metadata keys.

In the frontend the metadata can be accessed from any view like this:

```js
let image1 = this.getMetadata().get(['examples', 'images', 'image1'])
```

## Extending  Metadata

One of AtroCore's most powerful features is the ability to extend and override metadata from any module.
This allows for customization without modifying core files.
There are two approaches to extending metadata:


### 1. Static Extension

You can extend existing metadata by creating files with the same path in your custom module. AtroCore loads metadata in module order, so later modules can override earlier ones.

**Example:**
To extend the `images.json` metadata we created earlier, add a file with the same path in your module:

`Resources/metadata/examples/images.json`:
```json
{
    "image2": "new-image2-url",
    "image3": "https://picsum.photos/id/2/50/50",
    "sizes": [
        "__APPEND__",
        50
    ]
}
```

Special notes:
- Values are overwritten by default
- For arrays, you can use the special `__APPEND__` keyword to add new values instead of replacing the entire array

After merging, the metadata will contain:
```json
{
    "image1": "image1-url",
    "image2": "new-image2-url",
    "image3": "https://picsum.photos/id/2/50/50",
    "thumbnail": {
        "iphone": "iphone-thumbnail-url"
    },
    "sizes": [20, 40, 50]
}
```

### 2. Dynamic Extension via Listeners
For conditional metadata modifications or complex logic, you can use listener classes to programmatically modify metadata. This approach is covered in the [Extending with Listeners](../20.listeners) section.

## Entity Management and custom Metadata

AtroCore provides a powerful Entity Manager in the administration panel that allows users with appropriate permissions to:

* Add new fields to existing entities
* Create relationships between entities
* Define entirely new custom entities

Changes made through the Entity Manager are stored as metadata files in the `data/metadata/` directory on your server. These files are loaded last in the metadata merging process, ensuring they override any previous definitions.

Check the Administration Entity Manager documentation [here](../../../01.atrocore/03.administration/11.entity-management/docs.md)

## Modifying the content of metadata

To modify metadata in your code, use the `set` and `save` methods:

```php
/** @var \Atro\Core\Utils\Metadata $metadata */
$metadata = $container->get('metadata');

$metadata->set($folderName, $fileName, $keyValueContains);

// apply the change
$metadata->save();
```

* `$folderName `is any directories listed in `Resources/metadata` (or `data/metadata` on your server)
* `$fileName`  Base name of the JSON file (without the `.json` extension)
* `$keyValueContains` Array of values to merge into the existing metadata

**Example:**
Let's assume we want to make the fields `name` of the entity `Product` `required`.
To achieve that, we need to set the property `required` to `true` in the field définition in `Product` entityDefs metadata.

> Learn more about how to create entities definition [here](../05.entities).

```php
/** @var \Atro\Core\Utils\Metadata $metadata */
$metadata = $container->get('metadata');

$metadata->set('entityDefs', 'Product', [
     "fields" => [
        "name" => [
            "required" => true
        ]
    ]
]);

// apply the change
$metadata->save();
```

This change will be saved to `data/metadata/entityDefs/Product.json` on your server and will override any existing definitions from modules.

> A file in the folder data/metadata/ of the server will overwrite any existing value from any modules because is merge at the last position, when metadata is build


## Metadata Caching and Retrieval

### Caching Behavior

For performance reasons, AtroCore compiles all metadata into a single cached file:

* Cached location: `data/cache/metadata.json`
* Rebuilding: Cache is recreated whenever the system cache is cleared
* Manual rebuild: Run `php console.php clear cache` after changing metadata files

> **Developer Tip:** During development, you can disable metadata caching by setting `useCache` to `false` in `data/config.php`. This causes metadata to be rebuilt with every request, eliminating the need to clear cache after metadata changes.

### API Access

You can retrieve the complete metadata through the API using:
```
GET /api/Metadata
```

For more information about the AtroCore API, see the [REST API documentation](../../10.rest-api/docs.md).

## Troubleshooting

- If metadata changes aren't applied, ensure you've cleared the cache
- Check JSON syntax for errors in your metadata files
- Verify file paths match the expected structure
- For dynamic extensions, ensure your listeners are properly registered
- Use logging to debug metadata loading issues

---

This documentation covers the core concepts of metadata in AtroCore. For entity-specific metadata details, refer to the [Entities documentation](../05.entities).
