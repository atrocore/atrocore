---
title: Layout management
---

AtroCore provides a flexible system for customizing how entity data is displayed in your application through JSON-based layout files. These files control the appearance of both list views (tables) and detail views (record pages) for each entity type in your system.

This documentation explains how to configure and customize these layouts to create intuitive, efficient interfaces for your data.

## Layout File Structure

Each entity's layout configuration is stored in its own subdirectory within the `Resources/layouts` folder. For example, layouts for the `Product` entity would be located in `Resources/layouts/Product/`.

### Core Layout Files

Each entity typically has three primary layout definition files:

#### 1. `list.json` - Controls Table View Layout

This file defines which fields appear as columns in the entity's list view (table view). Each entry in the array represents a column to display.

**Example: `Category/list.json`**

```json
[
    { "name": "name", "link": true },     // Makes the name column clickable to open the detail view
    { "name": "mainImage", "notSortable": true },  // Images cannot be sorted
    { "name": "code" },                   // Simple column with no special properties
    { "name": "categoryRouteName" },      // Custom field
    { "name": "channels", "notSortable": true },  // Relationship field that can't be sorted
    { "name": "isActive" }                // Boolean field
]
```

**Available Properties:**
- `name`: Field name to display (required)
- `link`: When `true`, makes the column clickable to navigate to the detail view
- `notSortable`: When `true`, disables sorting for this column
- `width`: Specify a custom column width (e.g., `"width": "15%"`)
- `align`: Set text alignment (`"left"`, `"center"`, or `"right"`)

#### 2. `detail.json` - Controls Detail View Layout

This file defines the layout for individual record views. The structure consists of panels, which contain rows, which contain fields.

**Example: `Category/detail.json`**

```json
[
    {
        "label": "Details",           // Panel title
        "style": "default",           // Panel styling (default, success, danger, etc.)
        "rows": [
            [
                { "name": "isActive" },  // First field in row
                false                    // No second field (use false to leave empty)
            ],
            [
                { "name": "name" },      // First field in second row
                { "name": "parent" }     // Second field in second row
            ],
            [
                { "name": "description" },
                { "name": "code" }
            ]
        ]
    },
    // You can add additional panels here
    {
        "label": "Additional Information",
        "style": "info",
        "rows": [
            [
                { "name": "createdAt" },
                { "name": "createdBy" }
            ],
            [
                { "name": "modifiedAt" },
                { "name": "modifiedBy" }
            ]
        ]
    }
]
```

**Panel Properties:**
- `label`: Title displayed at the top of the panel
- `style`: Visual style of the panel (`default`, `success`, `danger`, `info`, `warning`, etc.)
- `rows`: Array of rows, each containing 1-2 field definitions

**Field Properties:**
- `name`: Field name to display
- `fullWidth`: When `true`, field spans entire row width

#### 3. `relationships.json` - Controls Related Records Display

This file defines which related entities should be displayed on the detail view. By default, related records are shown in a table using the related entity's standard `list.json` layout.

**Example: `Category/relationships.json`**

```json
[
    { "name": "products" },    // Display related products
    { "name": "channels" }     // Display related channels
]
```

## Advanced Layout Customization

### Custom Relationship Layouts

You can create specialized layouts for how related entities appear within specific parent entities. This allows you to show different columns based on context.

To create a custom layout for how entity A appears when viewed from entity B:

1. Create a file named `listIn{ParentEntityName}.json` in the related entity's layout folder
2. Define the columns as you would in a standard `list.json` file

**Example:**
To customize how `Category` entities are displayed on the `Product` detail page:

Create file: `Resources/layouts/Category/listInProduct.json`

```json
[
    { "name": "name", "link": true },    // Only show name (clickable)
    { "name": "isActive" }               // And active status
    // Other columns from the standard list view are not shown
]
```

### Dynamic Layout Extensions

You can programmatically modify layouts using listener classes, which allow you to:

- Add new fields to layouts
- Hide or modify existing fields
- Add custom buttons or actions
- Change layout behavior based on user permissions

For more details on extending layouts with code, see the [Extending with Listeners](../20.listeners) section.

### User-Specific Layouts with LayoutProfiles

AtroCore supports customizing layouts for specific users through **LayoutProfiles**. This powerful feature allows administrators to:

- Create different layout configurations for different user roles
- Simplify interfaces for users who need limited functionality
- Provide specialized views for different departments

A LayoutProfile is an entity that contains a set of custom layout configurations. When assigned to a user, it overrides the default layouts with its custom settings.

For more comprehensive information on LayoutProfiles, refer to the [Layouts Administration](../../../01.atrocore/03.administration/13.user-interface/02.layouts/index.md) documentation.

## Best Practices

- **Keep list views focused**: Include only the most important fields in list views for better performance and usability
- **Group related fields**: Organize detail view fields into logical panels
- **Consider screen space**: Remember that users may access your application on different screen sizes
- **Test your layouts**: Verify your layouts with sample data before deploying to production
- **Use custom relationship layouts**: Create context-specific views for related entities when needed

## Troubleshooting

- If your layout changes aren't appearing, try clearing your application cache
- Ensure your JSON files are valid (no missing commas, brackets, etc.)
- Check file permissions if layouts aren't being loaded
- Review your browser console for JavaScript errors that might indicate layout problems

---

This documentation covers the essentials of layout management in AtroCore. For more advanced configurations or specific use cases, refer to the full AtroCore developer documentation.
