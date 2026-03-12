
---
title: Entities
taxonomy:
    category: docs
---


AtroCore represents data using **entities**, which are object-oriented representations of your business data. Entities
form the backbone of your application's data model, providing a structured way to define, store, and manipulate
information.

## Entity Types

AtroCore supports four distinct entity types, each designed for specific use cases:

### Base Entities

**Type:** `Base`

- **Purpose**: Standard entities stored in a single database table
- **Use Case**: Most of your business entities (Products, Orders, Customers, etc.)
- **Storage**: Traditional database tables with full CRUD operations

### Hierarchy Entities

**Type:** `Hierarchy`

- **Purpose**: Entities with parent-to-child hierarchical relationships
- **Use Case**: Categories, organizational structures, taxonomies
- **Special Feature**: Automatically creates a companion `{EntityName}Hierarchy` entity to manage the hierarchy data
- **Example**: `Category` entity in `PIM` the module

### Relation Entities

**Type:** `Relation`

- **Purpose**: Abstracts the middle table in many-to-many relationships
- **Use Case**: Junction tables with additional attributes
- **Auto-Generation**: Created automatically for many-to-many relationships
- **Example**: `ProductCategory` relation with additional fields like `sorting` or `mainCategory`

### Reference Data Entities

**Type:** `ReferenceData`

- **Purpose**: Static or semi-static data stored in files rather than database
- **Use Case**: Configuration data, lookup tables, system constants
- **Storage**: JSON files in `data/reference-data/` directory or any other custom one.
- **Benefits**: Fast access, automatic frontend injection, version control friendly
- **Example**: `Locale` entity stored at `data/reference-data/Locale.json`

---

## Creating a New Entity

Let's create a comprehensive example by building a **`Manufacturer`** entity in a module called `ExampleModule`. This
entity will demonstrate various field types and relationship patterns.

### Entity Requirements

Our `Manufacturer` entity will include:

**Fields:**

- `name` (string, required)
- `description` (text, optional)
- `logo` (file upload, conditionally required)
- `createdAt` / `modifiedAt` (datetime, auto-managed)
- `createdBy` / `modifiedBy` (user references, auto-managed)

**Relationships:**

- `products` (one-to-many with Product)
- `brands` (many-to-many with Brand)
- `createdBy` / `modifiedBy` (many-to-one with User)

### Required Files

To create this entity, you need three metadata files in your module's `Resources/metadata/` directory:

```
ExampleModule/
└── app/
    └── Resources/
        └── metadata/
            ├── scopes/Manufacturer.json          # Entity behavior configuration
            ├── entityDefs/Manufacturer.json      # Fields and relationships definition
            └── clientDefs/Manufacturer.json      # Frontend rendering configuration
```

> **Prerequisites**: Learn how to create a module in the [Module Development Guide](../../30.own-modules).

---

## 1. Scope Configuration

**File:** `scopes/Manufacturer.json`

This file defines the entity's overall behavior, permissions, and system integration options.

```json
{
    "entity": true,
    "layouts": true,
    "tab": true,
    "acl": true,
    "customizable": true,
    "importable": true,
    "object": true,
    "type": "Base",
    "module": "ExampleModule",
    "hasAssignedUser": true,
    "hasOwner": false,
    "hasTeam": false,
    "hasActive": true
}
```

### Scope Parameters Reference

| Parameter         | Type    | Description                                                                            | Impact                                                                                                 |
|-------------------|---------|----------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------|
| `entity`          | boolean | Marks this as an entity scope                                                          | **Required**: Must be `true` for entities                                                              |
| `layouts`         | boolean | Enable layout customization via admin panel                                            | Adds layout management UI                                                                              |
| `tab`             | boolean | Show entity in main navigation menu                                                    | Creates menu entry                                                                                     |
| `acl`             | boolean | Enable Access Control List permissions                                                 | Adds permission management                                                                             |
| `customizable`    | boolean | Allow admin panel customization                                                        | Enables field/layout modifications                                                                     |
| `importable`      | boolean | Enable data import functionality                                                       | Adds import/export capabilities                                                                        |
| `object`          | boolean | Enable object-level permissions                                                        | Allows record-level access control                                                                     |
| `type`            | string  | Entity type (`Base`, `Hierarchy`, `ReferenceData`)                                     | **Required**: Determines storage and behavior                                                          |
| `module`          | string  | Parent module name                                                                     | **Required**: Used for services, repositories, controllers loading                                     |
| `hasAssignedUser` | boolean | Enable user assignment                                                                 | Adds `assignedUser` field and functionality                                                            |
| `hasOwner`        | boolean | Enable ownership tracking                                                              | Adds `owner` field (alternative to assignedUser)                                                       |
| `hasTeam`         | boolean | Enable team assignment                                                                 | Adds `team` field and team-based permissions                                                           |
| `hasActive`       | boolean | Enable active/inactive status                                                          | Adds `isActive` boolean field                                                                          |
| `hasAttribute`    | boolean | Enable  support for attribute values                                                   |
| `hasAssociate`    | boolean | Enable support for associated table (for relation between itself)                      | Create companion entity Associated{Entity} of Type Relation with a foreign field to entity Association
| `streamDisabled`  | boolean | Disable the activities about fields updates  and the subscriptions feature (following) | Create relevant tables in the databases

---

## 2. Entity Definition

**File:** `entityDefs/Manufacturer.json`

This file defines the entity's data structure, including fields, validation rules, and relationships.

```json
{
    "fields": {
        "name": {
            "type": "varchar",
            "required": true,
            "trim": true,
            "maxLength": 255
        },
        "description": {
            "type": "text",
            "tooltip": true
        },
        "logo": {
            "type": "file",
            "fileTypeId": "a_image",
            "tooltip": true,
            "conditionalProperties": {
                "required": {
                    "conditionalGroups": [
                        {
                            "type": "isTrue",
                            "attribute": "isActive"
                        }
                    ]
                }
            }
        },
        "products": {
            "type": "linkMultiple",
            "tooltip": true
        },
        "brands": {
            "type": "linkMultiple",
            "tooltip": true
        },
        "createdAt": {
            "type": "datetime",
            "readOnly": true
        },
        "modifiedAt": {
            "type": "datetime",
            "readOnly": true
        },
        "createdBy": {
            "type": "link",
            "readOnly": true,
            "view": "views/fields/user"
        },
        "modifiedBy": {
            "type": "link",
            "readOnly": true,
            "view": "views/fields/user"
        }
    },
    "links": {
        "products": {
            "type": "hasMany",
            "entity": "Product",
            "foreign": "manufacturer"
        },
        "brands": {
            "type": "hasMany",
            "entity": "Brand",
            "relationName": "BrandManufacturer",
            "foreign": "manufacturers"
        },
        "createdBy": {
            "type": "belongsTo",
            "entity": "User"
        },
        "modifiedBy": {
            "type": "belongsTo",
            "entity": "User"
        }
    }
}
```

### Field Types Reference

| Field Type            | Description                                                                  | Example Use Case               |
|-----------------------|------------------------------------------------------------------------------|--------------------------------|
| `varchar`             | Short text field (max 255 chars)                                             | Names, titles, SKUs            |
| `text`                | Long text field                                                              | Descriptions, notes            |
| `wysiwyg`             | Long text field with html view                                               | Descriptions, notes            |
| `markdown`            | Long text field with markdown                                                | Descriptions, notes            |
| `file`                | File upload field                                                            | Images, documents, attachments |
| `linkMultiple`        | Multiple entity references                                                   | Many-to-many relationships     |
| `link`                | Single entity reference                                                      | Many-to-one relationships      |
| `datetime`            | Date and time field                                                          | Timestamps, schedules          |
| `date`                | Date only field                                                              | Birth dates, deadlines         |
| `enum`                | Single choice from predefined options                                        | Status, category               |
| `multiEnum`           | Multiple choices from predefined options                                     | Tags, features                 |
| `extensibleEnum`      | Single choice from dynamic options represented by theentity ExtensibleEnum   | Status, category               |
| `extensibleMutliEnum` | Multiple choice from dynamic options represented by theentity ExtensibleEnum | Tags, features                 |
| `bool`                | Boolean (true/false) field                                                   | Flags, toggles                 |
| `int`                 | Integer number field                                                         | Quantities, counts             |
| `float`               | Decimal number field                                                         | Prices, measurements           |

### Special Field Properties

- **`view`**: Custom frontend view for the field (overrides default rendering)
- **`tooltip`**: Shows help tooltip in the UI
- **`readOnly`**: Field cannot be edited (frontend only)
- **`required`**: Field must have a value
- **`trim`**: Automatically removes whitespace
- **`maxLength`**: Maximum character length for text fields
- **`notNull`**: Add nul constraint in the database if true

There are more fields properties available depending on the field type.

## 3. Defining Entity Relationships

AtroCore provides a flexible system for defining relationships between entities in your metadata. This section explains
how to create various relationship types and their corresponding configuration.

### Relationship Types Overview

AtroCore supports several relationship types, each implemented with specific field types and link configurations:

| Relationship Type | Field Type     | Link Type                     | Description                                                |
|-------------------|----------------|-------------------------------|------------------------------------------------------------|
| Many-to-one       | `link`         | `belongsTo`                   | Creates a reference from many child records to one parent  |
| One-to-many       | `linkMultiple` | `hasMany`                     | Defines a parent's collection of child records             |
| Many-to-many      | `linkMultiple` | `hasMany` with `relationName` | Creates a many-to-many relationship using a junction table |

### Defining One-to-Many Relationships

A one-to-many relationship connects a single record of one entity to multiple records of another entity. For example,
one `Manufacturer` can have many `Products`.

#### Parent Side (One)

To define the parent (one) side of the relationship:

**File:** `entityDefs/Manufacturer.json`

```json
{
    "fields": {
        "products": {
            "type": "linkMultiple"
        }
    },
    "links": {
        "products": {
            "type": "hasMany",
            "entity": "Product",
            "foreign": "manufacturer" // References the field name on the Product entity
        }
    }
}
```

#### Child Side (Many)

To define the child (many) side of the relationship:

**File:** `entityDefs/Product.json`

```json
{
    "fields": {
        "manufacturer": {
            "type": "link" // Single link for Many-to-One relationship
        }
    },
    "links": {
        "manufacturer": {
            "type": "belongsTo",
            "entity": "Manufacturer",
            "foreign": "products" // References the field name on the Manufacturer entity
        }
    }
}
```

When this relationship is established, a foreign field `manufacturerId` will be created in the `Product` entity.

### Defining Many-to-Many Relationships

A many-to-many relationship allows records from both entities to be associated with multiple records from the other
entity. For example, a `Manufacturer` can work with multiple `Brands`, and each `Brand` can work with multiple
`Manufacturers`.

#### First Entity Side

**File:** `entityDefs/Manufacturer.json`

```json
{
    "fields": {
        "brands": {
            "type": "linkMultiple"
        }
    },
    "links": {
        "brands": {
            "type": "hasMany",
            "entity": "Brand",
            "relationName": "BrandManufacturer", // Junction entity  name
            "foreign": "manufacturers" // References the field name on the Brand entity
        }
    }
}
```

#### Second Entity Side

**File:** `entityDefs/Brand.json`

```json
{
    "fields": {
        "manufacturers": {
            "type": "linkMultiple"
        }
    },
    "links": {
        "manufacturers": {
            "type": "hasMany",
            "entity": "Manufacturer",
            "relationName": "BrandManufacturer", // Same junction entity name
            "foreign": "brands" // References the field name on the Manufacturer entity
        }
    }
}
```

When this relationship is established:

- A junction entity of type Relation named `BrandManufacturer` will be automatically created
- This entity will contain foreign fields to both entities (`manufacturerId` and `brandId`)

### Self-Referential Relationships (Associated Records)

AtroCore provides a special feature for creating relationships between records of the same entity type. This is
implemented through the "Associated" feature, which creates a structured way to define different types of associations
between records.

#### Activating Associated Records

To enable self-referential relationships for an entity, add the `hasAssociate` option to the entity's scope definition:

**File:** `scopes/Product.json`

```json
{
    "entity": true,
    "hasAssociate": true,
    "object": true,
    "layouts": true
    // other scope properties
}
```

#### Automatic Field Creation

When you activate the `hasAssociate` feature, AtroCore automatically adds two important `linkMultiple` fields to your
entity:

1. **associatedItems**: Records that are associated with the current record (incoming associations)
2. **associatingItems**: Records that the current record is associated with (outgoing associations)

These fields provide bidirectional access to associated records, allowing you to retrieve:

- All records that reference the current record
- All records that the current record references

#### How It Works

When you activate the `hasAssociate` feature:

1. AtroCore automatically creates an entity named `Associated{EntityName}` (e.g., `AssociatedProduct`)
2. This entity serves as a junction entity between records of the same entity type
3. Each association record includes:
    - References to both related records
    - A link to an `Association` entity that defines the type of relationship

#### Association Types

The `Association` entity defines the types of relationships that can exist between records. For example:

- "Related to"
- "Similar to"
- "Compatible with"
- "Replacement for"

Each association can be unidirectional or bidirectional, depending on your configuration.

#### Data Model

The resulting data model includes:

- Your original entity (e.g., `Product`) with the added fields:
    - `associatedItems` - Records associated with this record
    - `associatingItems` - Records this record is associated with
- An automatically generated `Associated{EntityName}` entity (e.g., `AssociatedProduct`) with:
    - `associatedItem` with foreign field `associatedItemId` - Reference to the first record
    - `associatingItem` with foreign field `associatingItemId` - Reference to the second record
    - `association` with foreign field `associationId` - Reference to the association type from the `Association` entity

### Key Configuration Properties

When defining relationships, these properties are most important:

| Property        | Description                                                                            |
|-----------------|----------------------------------------------------------------------------------------|
| `type` (field)  | The field type: `link` for Many-to-one, `linkMultiple` for One-to-many or Many-to-many |
| `type` (link)   | The link type: `belongsTo` for Many-to-one, `hasMany` for One-to-many or Many-to-many  |
| `entity`        | The target entity name this relationship connects to                                   |
| `foreign`       | The field name on the target entity that completes this relationship                   |
| `relationName`  | For Many-to-many only: defines the junction table name (must match on both sides)      |
| `hasAssociated` | Boolean flag in entity scope that enables self-referential relationships               |

### Database Impact

Different relationship types affect the database schema in different ways:

- **belongsTo (Many-to-one)**: Adds a foreign key column to the entity's table
- **hasMany (One-to-many)**: No direct database change (uses the foreign key from the related entity)
- **hasMany with relationName (Many-to-many)**: Creates a junction table with foreign keys to both entities
- **hasAssociated (Self-referential)**: Creates a complete intermediary entity with its own table that includes
  association type references

### Best Practices

- Always define both sides of a relationship for proper functionality
- Use consistent naming for related fields (e.g., if one side is `products`, the other should be `product` or
  `manufacturer`)
- Choose meaningful names for `relationName` properties that reflect both entities
- For self-referential relationships, create meaningful association types in the `Association` entity
- Use `associatedItems` and `associatingItems` to access related records in both directions
- Remember to clear the application cache after adding new relationships

## 4. Client Configuration

**File:** `clientDefs/Manufacturer.json`

This file configures how the entity appears and behaves in the frontend interface.

```json
{
    "controller": "controllers/record",
    "iconClass": "plus-square"
}
```

| Parameter    | Description                          | Available Values                                                                                                                     |
|--------------|--------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------|
| `controller` | Backbone.js controller for rendering | `controllers/record` (default)                                                                                                       |
| `iconClass`  | Font Awesome icon class              | See [systemIcons.json](https://gitlab.atrocore.com/atrocore/atrocore/-/blob/master/app/Atro/Resources/metadata/app/systemIcons.json) |

> [AtroCore Toolkit](https://plugins.jetbrains.com/plugin/28159-atrocore-toolkit) PhpStorm plugin can automatically
> generate these entity files, saving time and reducing errors.

---

## Database Synchronization

After creating your entity metadata files, you need to sync the database schema with your new definitions.

### 1. Clear Cache

After metadata changes, clear the application cache:

```bash
php console.php cache clear
```

### 2. Preview Database Changes

First, review what changes will be made:

```bash
php console.php sql diff --show
```

**Expected Output for PostgreSQL:**

```sql
-- Main entity table
CREATE TABLE manufacturer
(
    id               VARCHAR(36)                  NOT NULL,
    name             VARCHAR(255) DEFAULT NULL,
    deleted          BOOLEAN      DEFAULT 'false',
    description      TEXT         DEFAULT NULL,
    created_at       TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    modified_at      TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    is_active        BOOLEAN      DEFAULT 'false' NOT NULL,
    logo_id          VARCHAR(36)  DEFAULT NULL,
    created_by_id    VARCHAR(36)  DEFAULT NULL,
    modified_by_id   VARCHAR(36)  DEFAULT NULL,
    assigned_user_id VARCHAR(36)  DEFAULT NULL,
    PRIMARY KEY (id)
);

-- Performance indexes
CREATE INDEX IDX_MANUFACTURER_CREATED_BY_ID ON manufacturer (created_by_id, deleted);
CREATE INDEX IDX_MANUFACTURER_MODIFIED_BY_ID ON manufacturer (modified_by_id, deleted);
CREATE INDEX IDX_MANUFACTURER_ASSIGNED_USER_ID ON manufacturer (assigned_user_id, deleted);

-- User following table (for notifications)
CREATE TABLE user_followed_manufacturer
(
    id              VARCHAR(36) NOT NULL,
    deleted         BOOLEAN     DEFAULT 'false',
    created_at      TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    modified_at     TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    created_by_id   VARCHAR(36) DEFAULT NULL,
    modified_by_id  VARCHAR(36) DEFAULT NULL,
    user_id         VARCHAR(36) DEFAULT NULL,
    manufacturer_id VARCHAR(36) DEFAULT NULL,
    PRIMARY KEY (id)
);

-- Many-to-many junction table
CREATE TABLE brand_manufacturer
(
    id              VARCHAR(36) NOT NULL,
    deleted         BOOLEAN     DEFAULT 'false',
    created_at      TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    modified_at     TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    created_by_id   VARCHAR(36) DEFAULT NULL,
    modified_by_id  VARCHAR(36) DEFAULT NULL,
    brand_id        VARCHAR(36) DEFAULT NULL,
    manufacturer_id VARCHAR(36) DEFAULT NULL,
    PRIMARY KEY (id)
);

-- Foreign key for one-to-many relationship
ALTER TABLE product
    ADD manufacturer_id VARCHAR(36) DEFAULT NULL;
CREATE INDEX IDX_PRODUCT_MANUFACTURER_ID ON product (manufacturer_id, deleted);
```

### 3. Apply Database Changes

Once you've verified the changes look correct:

```bash
php console.php sql diff --run
```

---

## Working with Entities

### Basic Entity Operations

Once your entity is created, you can perform standard CRUD operations:

```php
<?php

/** @var \Espo\Core\ORM\EntityManager $entityManager */
$entityManager = $container->get('entityManager');

// CREATE - Create a new manufacturer
/** @var \Atro\Core\Templates\Entities\Base $manufacturer */
$manufacturer = $entityManager->getEntity('Manufacturer');
$manufacturer->set('name', 'ACME Manufacturing');
$manufacturer->set('description', 'Leading manufacturer of quality products');
$manufacturer->set('isActive', true);

// Save to database
$entityManager->saveEntity($manufacturer);

// READ - Retrieve a manufacturer
$manufacturer = $entityManager->getRepository('Manufacturer')->get('manufacturer-id');

// Alternative shorter syntax
$manufacturer = $entityManager->getEntity('Manufacturer', 'manufacturer-id');

// getting the name
$name = $manufacter->get('name');
// getting the brands list lazy loaded
$brands = $manufacter->get('brands');
// Get related products (one-to-many)

// UPDATE - Modify existing data
$manufacturer->set('description', 'Updated description');
$entityManager->saveEntity($manufacturer);

// DELETE - Remove manufacturer
$entityManager->removeEntity($manufacturer);
```

## Custom Entity Classes

To add custom logic, create a custom entity class that extends the appropriate base template.

### Creating Custom Entity Class

**File:** `Entities/Manufacturer.php`

```php
<?php

namespace ExampleModule\Entities;

use Atro\Core\Templates\Entities\Base;

class Manufacturer extends Base
{
    /**
     * Get capitalized manufacturer name
     */
    public function getCapitalizedName(): string
    {
        return ucfirst($this->get('name') ?? '');
    }

}
```

### Using Custom Entity Methods

```php
<?php

/** @var \ExampleModule\Entities\Manufacturer $manufacturer */
$manufacturer = $entityManager->getEntity('Manufacturer', 'manufacturer-id');

// Use custom methods
$capitalizedName = $manufacturer->getCapitalizedName();


echo "Manufacturer: {$capitalizedName}";
```

### Available Base Entity Methods

| Method                       | Description              | Usage                                 |
|------------------------------|--------------------------|---------------------------------------|
| `get($field)`                | Get field value          | `$entity->get('name')`                |
| `set($field, $value)`        | Set field value          | `$entity->set('name', 'ACME')`        |
| `has($field)`                | Check if field exists    | `$entity->has('name')`                |
| `clear($field)`              | Clear field value        | `$entity->clear('description')`       |
| `isNew()`                    | Check if entity is new   | `$entity->isNew()`                    |
| `isAttributeChanged($field)` | Check if field changed   | `$entity->isAttributeChanged('name')` |
| `getFetched($field)`         | Get original field value | `$entity->getFetched('name')`         |

> You can check the parent class to check additional methods.

---

## Conditional Properties

Conditional properties allow you to dynamically control field behavior based on the values of other fields. This
powerful feature works on both frontend and backend, ensuring consistent validation and user experience.

### Available Conditional Properties

| Property         | Scope              | Description                                      |
|------------------|--------------------|--------------------------------------------------|
| `visible`        | Frontend only      | Show/hide fields(or relationship panels)         |
| `required`       | Frontend + Backend | Make fields require                              |
| `readOnly`       | Frontend only      | Disable field editing in UI                      |
| `protected`      | Frontend + Backend | Disable field modification (enforced by backend) |
| `disableOptions` | Frontend + Backend | Disable specific options in list fields          |

### Basic Conditional Structure

Each conditional property contains `conditionalGroups`, which are arrays of conditions that must be met:

```json
{
    "conditionalProperties": {
        "propertyName": {
            "conditionalGroups": [
                {
                    "type": "operator",
                    "attribute": "fieldName",
                    "value": "comparisonValue"
                }
            ]
        }
    }
}
```

### Practical Examples

#### Example 1: Conditional Visibility

Show color field only when `hasColor` is true:

```json
{
    "fields": {
        "hasColor": {
            "type": "bool"
        },
        "color": {
            "type": "enum",
            "options": [
                "red",
                "blue",
                "green",
                "yellow"
            ],
            "conditionalProperties": {
                "visible": {
                    "conditionalGroups": [
                        {
                            "type": "isTrue",
                            "attribute": "hasColor"
                        }
                    ]
                }
            }
        }
    }
}
```

#### Example 2: Conditional Requirements

Make `warrantyPeriod` required when `price` is greater than 1000:

```json
{
    "fields": {
        "price": {
            "type": "float"
        },
        "warrantyPeriod": {
            "type": "int",
            "conditionalProperties": {
                "required": {
                    "conditionalGroups": [
                        {
                            "type": "greaterThan",
                            "attribute": "price",
                            "value": 1000
                        }
                    ]
                }
            }
        }
    }
}
```

#### Example 3: Disable Options

Disable certain colors for specific brands:

```json
{
    "fields": {
        "color": {
            "type": "enum",
            "options": [
                "red",
                "blue",
                "white",
                "black",
                "green"
            ],
            "conditionalProperties": {
                "disableOptions": [
                    {
                        "options": [
                            "white",
                            "black"
                        ],
                        "conditionalGroups": [
                            {
                                "type": "in",
                                "attribute": "brandId",
                                "value": [
                                    "brand-id-1",
                                    "brand-id-2"
                                ]
                            }
                        ]
                    }
                ]
            }
        }
    }
}
```

### Supported Operators by Field Type

#### Boolean Fields (`bool`)

- `isEmpty`, `isNotEmpty`, `isTrue`, `isFalse`

```json
{
    "type": "isTrue",
    "attribute": "isActive"
}
```

#### Text Fields (`varchar`, `text`, `wysiwyg`)

- `isEmpty`, `isNotEmpty`, `equals`, `notEquals`, `contains`, `notContains`

```json
{
    "type": "contains",
    "attribute": "name",
    "value": "ACME"
}
```

#### Numeric Fields (`int`, `float`)

- `isEmpty`, `isNotEmpty`, `equals`, `notEquals`
- `lessThan`, `greaterThan`, `lessThanOrEquals`, `greaterThanOrEquals`

```json
{
    "type": "greaterThanOrEquals",
    "attribute": "quantity",
    "value": 100
}
```

#### Date Fields (`date`, `datetime`)

- All numeric operators plus: `isToday`, `inFuture`, `inPast`

```json
{
    "type": "inFuture",
    "attribute": "expiryDate"
}
```

#### Enum Fields (`enum`)

- `isEmpty`, `isNotEmpty`, `equals`, `notEquals`, `in`, `notIn`

```json
{
    "type": "in",
    "attribute": "status",
    "value": [
        "active",
        "pending"
    ]
}
```

#### Multi-Enum Fields (`multiEnum`)

- `isEmpty`, `isNotEmpty`, `has`, `notHas`

```json
{
    "type": "has",
    "attribute": "features",
    "value": "wireless"
}
```

#### Link Fields (`link`)

Use the foreign key field (e.g., `manufacturerId` for `manufacturer` link):

```json
{
    "type": "equals",
    "attribute": "manufacturerId",
    "value": "specific-manufacturer-id"
}
```

#### Link Multiple Fields (`linkMultiple`)

Use the virtual IDs field (e.g., `brandsIds` for `brands` linkMultiple):

```json
{
    "type": "contains",
    "attribute": "brandsIds",
    "value": "specific-brand-id"
}
```

### Combining Conditions with Logical Operators

Use `and`, `or`, and `not` operators to create complex conditions:

#### AND Logic Example

Make `description` required when quantity is between 10 and 100:

```json
{
    "fields": {
        "description": {
            "type": "text",
            "conditionalProperties": {
                "required": {
                    "conditionalGroups": [
                        {
                            "type": "and",
                            "value": [
                                {
                                    "type": "greaterThanOrEquals",
                                    "attribute": "quantity",
                                    "value": 10
                                },
                                {
                                    "type": "lessThanOrEquals",
                                    "attribute": "quantity",
                                    "value": 100
                                }
                            ]
                        }
                    ]
                }
            }
        }
    }
}
```

#### OR Logic Example

Show special field when product is either expensive OR rare:

```json
{
    "conditionalProperties": {
        "visible": {
            "conditionalGroups": [
                {
                    "type": "or",
                    "value": [
                        {
                            "type": "greaterThan",
                            "attribute": "price",
                            "value": 1000
                        },
                        {
                            "type": "equals",
                            "attribute": "rarity",
                            "value": "limited-edition"
                        }
                    ]
                }
            ]
        }
    }
}
```

#### NOT Logic Example

Hide field when status is NOT active:

```json
{
    "conditionalProperties": {
        "visible": {
            "conditionalGroups": [
                {
                    "type": "not",
                    "value": [
                        {
                            "type": "equals",
                            "attribute": "status",
                            "value": "inactive"
                        }
                    ]
                }
            ]
        }
    }
}
```

### Current User Conditions

You can create conditions based on the current user making the request using the special virtual field`__currentUserId`:

```json
{
    "fields": {
        "sensitiveData": {
            "type": "text",
            "conditionalProperties": {
                "visible": {
                    "conditionalGroups": [
                        {
                            "type": "equals",
                            "attribute": "__currentUserId",
                            "value": "admin-user-id"
                        }
                    ]
                }
            }
        }
    }
}
```

**Supported operators for `__currentUserId`:**

- `isEmpty`, `isNotEmpty`, `equals`, `notEquals`

### Multiple Conditional Groups

You can define multiple conditional groups. The property applies when All group's conditions are met (AND logic between
groups):

```json
{
    "conditionalProperties": {
        "required": {
            "conditionalGroups": [
                {
                    "type": "equals",
                    "attribute": "type",
                    "value": "premium"
                },
                {
                    "type": "greaterThan",
                    "attribute": "price",
                    "value": 500
                }
            ]
        }
    }
}
```

This makes the field required when either `type` equals "premium" OR `price` is greater than 500.

---

## Best Practices

### Entity Design

- **Keep entities focused**: Each entity should represent a single business concept
- **Use meaningful names**: Choose clear, descriptive names for entities and fields
- **Plan relationships carefully**: Consider the impact of relationship changes on existing data

### Performance Considerations

- **Index frequently queried fields**: Add database indexes for fields used in WHERE clauses
- **Avoid deep relationship chains**: Limit relationship depth to maintain query performance
- **Use appropriate field types**: Choose the most efficient field type for your data
- **Consider data volume**: Plan for scalability from the start

### Validation and Data Integrity

- **Use conditional properties**: Implement business rules through metadata when possible
- **Validate in custom entities**: Add complex validation logic in custom entity classes
- **Handle edge cases**: Consider null values, empty strings, and boundary conditions
- **Test thoroughly**: Verify both positive and negative scenarios

### Maintenance

- **Version your changes**: Use migration scripts for schema changes
- **Back up before changes**: Always backup data before major schema modifications
- **Test in development**: Validate changes in a development environment first
- **Monitor performance**: Watch for performance impacts after schema changes

---

## Troubleshooting

### Common Issues

**Entity Not Appearing in UI:**

- Verify all three metadata files are created correctly
- Check that `entity: true` is set in scopes configuration
- Ensure module name matches your actual module
- Clear cache after making changes

**Database Sync Failures:**

- Check for syntax errors in JSON files
- Verify relationship definitions are bidirectional
- Ensure foreign entity exists before creating relationships
- Review database logs for specific error details

**Conditional Properties Not Working:**

- Verify operator is supported for the field type
- Check attribute names match exactly (case-sensitive)
- Ensure referenced fields exist in the entity definition
- Test conditions with simple cases first

**Performance Issues:**

- Add indexes for frequently queried fields
- Limit the number of relationships per entity
- Consider using Reference Data for lookup tables
- Monitor query execution plans

**Custom Entity Class Not Loading:**

- Verify namespace matches module structure
- Ensure class extends appropriate base template
- Check for PHP syntax errors
- Clear cache after creating custom classes

### Debugging Tips

**Inspect Generated SQL:**

```bash
# See what changes will be made
php console.php sql diff --show
```

**Enable Debug Logging:**
Add to your configuration to see detailed query logs and debug information.

**Test Incrementally:**

- Start with basic entity structure
- Add fields one at a time
- Test relationships separately
- Add conditional logic last

This comprehensive guide should give you everything needed to create robust, well-designed entities in AtroCore.
Remember to always test your entities thoroughly and consider the impact on existing data when making changes.
