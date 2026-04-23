---
title: Frontend development
---

## Introduction

AtroCore is a modular framework that provides a robust foundation for building enterprise applications. The frontend architecture is built on BackboneJS and Handlebars, offering a flexible and extensible system for customizing views, fields, and behaviors.

This guide covers the essential concepts and patterns for frontend development in AtroCore, including view inheritance, custom controllers, field customization, and configuration management.

---

> **Migration to SvelteKit in Progress**
>
> We are currently in the process of migrating the frontend to **SvelteKit**. The documentation for module development on the new engine will be published separately once the transition is complete.
>
> The content in this guide describes the **legacy engine** (BackboneJS + Handlebars), which will be removed in a future release. It remains valid for the current stable version, but we recommend keeping an eye on the **release notes** to stay informed about when the new SvelteKit-based workflow becomes the standard and when the legacy engine is retired.

---

## Technology Stack


### BackboneJS

AtroCore uses BackboneJS (v1.3.3) for its view layer. BackboneJS provides:

- **Models**: Data representation and business logic
- **Collections**: Ordered sets of models
- **Views**: User interface components that respond to model changes
- **Events**: Custom events and event-driven architecture
- **Router**: URL routing and navigation

Key BackboneJS concepts in AtroCore:

- Views extend `Backbone.View` or AtroCore's custom view classes
- Events are bound using the `events` hash or `listenTo` method
- Views are rendered using the `render()` method
- DOM manipulation is done through `this.$el` (jQuery-wrapped element)

### Handlebars Template Engine

Handlebars is used as the template engine for rendering dynamic HTML. Features include:

- **Expressions**: `{{variable}}` for outputting data
- **Helpers**: Built-in and custom functions for template logic
- **Partials**: Reusable template fragments
- **Block Helpers**: Conditional rendering and iteration (`{{#if}}`, `{{#each}}`)

Templates in AtroCore are located in the `res/templates` directory and are referenced by their path relative to this directory.

**Full Handlebars Documentation**: https://handlebarsjs.com/guide

### Custom Handlebars Helpers

AtroCore registers custom Handlebars helpers in the `view-helper.js` file. These helpers extend the functionality of Handlebars templates and are available throughout the application. Below is a comprehensive list of custom helpers registered via the `_registerHandlebarsHelpers` method:

| Helper Name | Parameters | Description | Usage Example |
|------------|------------|-------------|---------------|
| `translate` | `label`, `category`, `scope` | Translates a label to the current language | `{{translate "Save" category="labels" scope="Global"}}` |
| `translateOption` | `value`, `field`, `scope` | Translates an enum option value | `{{translateOption status field="status" scope="Product"}}` |
| `button` | `name`, `data` | Renders a button with action attributes | `{{button "save" label="Save"}}` |
| `hyphen` | `string` | Converts camelCase to hyphen-case | `{{hyphen "productName"}}` → `product-name` |
| `toDom` | `string` | Escapes string for safe DOM insertion | `{{toDom unsafeString}}` |
| `breaklines` | `text` | Converts line breaks to HTML `<br>` tags | `{{breaklines multilineText}}` |
| `complexText` | `text` | Processes text with markdown-like formatting | `{{complexText formattedText}}` |
| `translatePath` | `path`, `scope` | Translates a dot-separated path | `{{translatePath "fields.name.label" scope="Product"}}` |
| `prop` | `object`, `name` | Gets a property from an object | `{{prop model "name"}}` |
| `var` | `name`, `value` | Sets a template variable for later use | `{{var "counter" 0}}` |
| `ifEqual` | `value1`, `value2` | Block helper - renders block if values are equal | `{{#ifEqual status "active"}}...{{/ifEqual}}` |
| `ifNotEqual` | `value1`, `value2` | Block helper - renders block if values are not equal | `{{#ifNotEqual status "inactive"}}...{{/ifNotEqual}}` |
| `ifPropEquals` | `object`, `prop`, `value` | Checks if object property equals value | `{{#ifPropEquals model "status" "active"}}...{{/ifPropEquals}}` |
| `ifAttrEquals` | `object`, `attr`, `value` | Checks if object attribute equals value | `{{#ifAttrEquals this "type" "product"}}...{{/ifAttrEquals}}` |
| `ifAttrNotEmpty` | `object`, `attr` | Checks if object attribute is not empty | `{{#ifAttrNotEmpty model "name"}}...{{/ifAttrNotEmpty}}` |
| `ifNotEmptyHtml` | `value` | Block helper - renders if value is not empty HTML | `{{#ifNotEmptyHtml description}}...{{/ifNotEmptyHtml}}` |
| `get` | `object`, `key` | Gets a value from an object by key | `{{get model "name"}}` |
| `length` | `array` | Returns the length of an array | `{{length items}}` |
| `formatDate` | `date` | Formats a date string to user format | `{{formatDate createdAt}}` |
| `formatDateTime` | `datetime` | Formats a datetime string to user format | `{{formatDateTime modifiedAt}}` |
| `formatTime` | `time` | Formats a time string to user format | `{{formatTime startTime}}` |
| `numberFormat` | `number`, `decimals` | Formats a number with thousand separators | `{{numberFormat 1234.56}}` → `1,234.56` |
| `ceil` | `number` | Rounds up to nearest integer | `{{ceil 4.3}}` → `5` |
| `floor` | `number` | Rounds down to nearest integer | `{{floor 4.7}}` → `4` |
| `parseInt` | `string` | Parses string to integer | `{{parseInt "42"}}` → `42` |
| `contains` | `array`, `value` | Checks if array contains value | `{{#if (contains tags "featured")}}...{{/if}}` |
| `includes` | `string`, `substring` | Checks if string includes substring | `{{#if (includes name "Product")}}...{{/if}}` |
| `join` | `array`, `separator` | Joins array elements with separator | `{{join tags ", "}}` |
| `concat` | `...strings` | Concatenates multiple strings | `{{concat "Hello" " " "World"}}` |
| `urlEncode` | `string` | URL encodes a string | `{{urlEncode searchTerm}}` |
| `basePath` | - | Returns the application base path | `{{basePath}}/images/logo.png` |
| `frontendUrl` | - | Returns the frontend URL | `{{frontendUrl}}` |
| `apiUrl` | - | Returns the API base URL | `{{apiUrl}}` |
| `img` | `path` | Generates image tag with proper path | `{{img "path/to/image.png"}}` |
| `file` | `id` | Generates file download URL | `{{file attachmentId}}` |
| `stripTags` | `html` | Removes HTML tags from string | `{{stripTags htmlContent}}` |
| `lower` | `string` | Converts string to lowercase | `{{lower "HELLO"}}` → `hello` |
| `upper` | `string` | Converts string to uppercase | `{{upper "hello"}}` → `HELLO` |
| `ucfirst` | `string` | Capitalizes first character | `{{ucfirst "hello"}}` → `Hello` |

**Note**: Block helpers (those starting with `#`) require a closing tag. For example:
```handlebars
{{#ifEqual status "active"}}
    <span class="badge badge-success">Active</span>
{{else}}
    <span class="badge badge-secondary">Inactive</span>
{{/ifEqual}}
```

### Svelte Components

In addition to BackboneJS views, AtroCore includes Svelte(v4.*) components that provide modern, reactive UI elements. These components are globally available and can be used in any view throughout the application.

**Available Svelte Components**:

| Component Name | Description |
|----------------|-------------|
| `LayoutComponent` | Layout management component for flexible UI structures |
| `RightSideView` | Right sidebar panel component |
| `TreePanel` | Hierarchical tree view for nested data |
| `ApiRequestComponent` | Component for handling API requests with UI feedback |
| `Navigation` | Main navigation menu component |
| `Favorites` | Favorites/bookmarks management component |
| `BaseHeader` | Base header component for pages |
| `ListHeader` | Header component specifically for list views |
| `ListActionsContainer` | Action buttons container for list views |
| `PlateActionsContainer` | Action buttons container for plate views |
| `DetailHeader` | Header component for detail/record views |
| `DashboardHeader` | Header component for dashboard pages |
| `FilterSearchBar` | Search and filter bar component |
| `Gallery` | Image gallery display component |
| `LocaleSwitcher` | Language/locale switcher component |
| `ContentFilter` | Advanced content filtering component |
| `AnchorNavigation` | Anchor-based navigation component |
| `RebuildDatabaseModal` | Modal dialog for database rebuild operations |
| `Administration` | Administration panel component |
| `SelectionLeftSidePanel` | Left sidebar for selection interfaces |

**Using Svelte Components**:

Svelte components can be integrated into BackboneJS views to leverage modern reactive features alongside the traditional Backbone architecture. These components are particularly useful for:
- Complex interactive UI elements
- Real-time data updates
- Advanced filtering and navigation
- Administrative interfaces

The components are available globally in the `Svelte` variable and can be mounted within any view's DOM structure, providing a bridge between the BackboneJS foundation and modern reactive UI patterns.

### Core JavaScript Libraries

The following core libraries are included by default in the minified JavaScript bundle and do not need to be imported separately:

- **jQuery**: DOM manipulation and utilities
- **Backbone.js**: MVC framework
- **Handlebars**: Template engine
- **Underscore.js**: Utility functions
- **Svelte**: Reactive component framework (for Svelte components listed above)

Additional third-party libraries can be loaded as described in the [Loading External Libraries](#loading-external-libraries) section.

---

## Module Structure

AtroCore follows a modular architecture where functionality is organized into self-contained modules. Understanding the folder structure is crucial for effective development.

### Directory Layout

```
example-module/
├── app/                                    # Backend files
│   ├── Controllers/                        # PHP controllers
│   ├── Entities/                          # Entity classes
│   ├── Listeners/                         # Event listeners
│   ├── Repositories/                      # Data repositories
│   ├── Resources/                         # Resource files
│   │   ├── metadata/                      # Metadata configurations
│   │   │   ├── clientDefs/               # Client-side entity definitions
│   │   │   ├── entityDefs/               # Server-side entity definitions
│   │   │   ├── scopes/                   # Scope configurations
│   │   │   └── app/                      # Application metadata
│   │   └── i18n/                         # Internationalization files
│   ├── Services/                          # Business logic services
│   └── Module.php                         # Module entry point
└── client/                                # Frontend files
    └── modules/
        └── example-module/                # Module-specific frontend code
            ├── lib/                       # External JavaScript libraries
            ├── res/
            │   └── templates/             # Handlebars templates
            │       └── entity-name/       # Entity-specific templates
            └── src/
                ├── controllers/           # BackboneJS controllers
                ├── models/               # BackboneJS models
                ├── collections/          # BackboneJS collections
                └── views/                # BackboneJS views
                    ├── entity-name/      # Entity-specific views
                    │   ├── fields/       # Custom field views
                    │   └── record/       # Record views
                    │       └── panels/   # Panel views
                    └── fields/           # Global field views
```

### Key Directories

**Backend (`app/`)**:
- Contains PHP code for server-side logic
- `Resources/metadata/` holds configuration files that affect frontend behavior

**Frontend (`client/`)**:
- All frontend code is under `client/modules/[module-name]/`
- `src/` contains JavaScript source files
- `res/templates/` contains Handlebars templates
- `lib/` stores external JavaScript libraries

### Naming Conventions

- Module names use kebab-case: `example-module`
- Entity names use PascalCase: `Product`, `Category`
- View references use colon notation: `module-name:views/path/to/view`
- File paths follow the same structure as core AtroCore files for consistency

---

## View System

### Core View Architecture

All views in AtroCore are accessible at this [location](https://gitlab.atrocore.com/atrocore/atrocore/-/tree/master/client/src/views).


The view system is hierarchical, with most views extending from the base `view.js` class. This provides a consistent set of methods and behaviors across all views.

### View Types

AtroCore provides various specialized views for different use cases. All views are located [here](https://gitlab.atrocore.com/atrocore/atrocore/-/tree/master/client/src/views/record).

**Understanding View Hierarchy**:

AtroCore uses a two-level view structure:
1. **Page-level views** (`views/[type]`) - Complete page with header, breadcrumbs, navigation, and all UI chrome
2. **Record-level views** (`views/record/[type]`) - Main content area without page chrome

**Complete Page Views**:

**`views/detail`**: Complete detail page
- Includes: header, breadcrumbs, right side panel, left panel, and the main record view
- Manages: page-level navigation, breadcrumb trail, page actions
- Contains: `views/record/detail` as the main content

**`views/list`**: Complete list page
- Includes: header, breadcrumbs, navigation elements, search bar
- Manages: page-level actions, navigation, filters
- Contains: `views/record/list` as the main content

**`views/edit`**: Complete edit/create page
- Includes: header, breadcrumbs, page navigation
- Manages: page-level save/cancel actions
- Contains: `views/record/edit` as the main content

**Record Content Views** (Main Content Area):

**`views/record/detail`**: Record detail content view
- Main content area of the detail page (without page chrome)
- Composed of: `detail-middle` (form part) and `detail-bottom` (relation panels)
- Handles: record data display, field rendering, relationship panels

**`views/record/detail-middle`**: Form part of detail view
- Contains the fields and panels for displaying record data
- Manages field rendering and layout

**`views/record/detail-bottom`**: Bottom section of detail view
- Contains all relationship panels
- Manages related entity lists

**`views/record/edit`**: Record edit/create content view
- Main content area of the edit page (without page chrome)
- Handles: record editing form, field validation, form submission
- Similar structure to `views/record/detail` but in edit mode

**`views/record/list`**: Record list content view
- Main content area of the list page (without page chrome)
- Displays records in a table format with rows
- Supports sorting, filtering, and pagination
- Most common view for entity collections

**Additional List View Variants**:

**`views/record/list-expanded`**: Expanded list view
- Shows more details for each record in the list
- Used when additional information needs to be visible without opening detail view

**`views/record/plate`**: Plate/card view
- Displays records as cards/blocks instead of table rows
- Activated by setting `"plateViewMode": true` in clientDefs
- Better for visual content or when more information needs to be displayed per record

**`views/record/kanban`**: Kanban board view
- Displays records in columns based on status or workflow stage
- Activated by setting `"kanbanViewMode": true` in clientDefs
- Supports drag-and-drop for status changes
- Ideal for workflow management and visual task tracking

**Panel Views**:

**`views/record/panels/relationship`**: Relationship panel
- Displays related entities in detail view
- Supports selection, creation, and unlinking of related records
- Can be customized per entity and relationship

**`views/record/right-side-view-panel`**: Right side panel
- Additional panel on the right side of views
- Used for contextual information or tools

### View Hierarchy Summary

```
Complete Page View              Record Content View
─────────────────              ───────────────────
views/detail                    views/record/detail
  ├── Header                      ├── views/record/detail-middle (form)
  ├── Breadcrumbs                 └── views/record/detail-bottom (relations)
  ├── Right side panel
  ├── Left panel
  └── [Main Content] ─────────▶

views/list                      views/record/list
  ├── Header                      └── (table with rows)
  ├── Breadcrumbs
  ├── Search bar
  ├── Filters
  └── [Main Content] ─────────▶

views/edit                      views/record/edit
  ├── Header                      └── (edit form)
  ├── Breadcrumbs
  ├── Page actions
  └── [Main Content] ─────────▶
```

**When to Override Which View**:

- Override `views/detail`, `views/list`, or `views/edit` when you need to customize the **entire page** (header, navigation, breadcrumbs, page structure)
- Override `views/record/detail`, `views/record/list`, or `views/record/edit` when you only need to customize the **main content area** (form, fields, table)

Most customizations should target the `views/record/*` level unless you specifically need to modify page-level chrome.

### View Lifecycle

1. **Definition**: View is defined using `Espo.define()`
2. **Initialization**: Constructor and `initialize()` are called
3. **Setup**: `setup()` method prepares the view
4. **Rendering**: `render()` creates the DOM structure
5. **Post-Render**: `afterRender()` is called after DOM insertion
6. **Cleanup**: `remove()` cleans up when view is destroyed

---

## Overriding Views

AtroCore uses an inheritance-based approach for customizing views. Instead of modifying core files, you extend existing views in your custom module.

### Understanding View Levels

Before overriding views, understand the two-level structure:

1. **Page-level views** (`views/[type]`): Complete page with all UI chrome
    - Override these when you need to modify headers, breadcrumbs, page navigation, or overall page structure

2. **Record-level views** (`views/record/[type]`): Main content area only
    - Override these when you need to modify form layout, field display, or data rendering
    - **Most common customization point**

### Basic View Inheritance

To override a default AtroCore view:

1. Create your custom view file following the same path structure as the core
2. Extend the original view using `Espo.define()`
3. Override specific methods or properties as needed

**Example 1**: Overriding the Product record detail view (main content only)

File: `client/modules/example-module/src/views/product/record/detail.js`

```javascript
Espo.define('example-module:views/product/record/detail', 'views/record/detail', Dep => {
    return Dep.extend({

        // Override setup method
        setup: function() {
            // Call parent method
            Dep.prototype.setup.call(this);

            // Add custom initialization logic
            this.listenTo(this.model, 'change:status', function() {
                console.log('Product status changed');
            });
        },

        // Add custom method
        customMethod: function() {
            // Your custom logic here
        },

        // Override existing method
        afterRender: function() {
            Dep.prototype.afterRender.call(this);

            // Custom post-render logic
            this.$el.find('.custom-element').addClass('highlighted');
        }
    });
});
```

**Example 2**: Overriding the complete Product detail page (with page chrome)

File: `client/modules/example-module/src/views/product/detail.js`

```javascript
Espo.define('example-module:views/product/detail', 'views/detail', Dep => {
    return Dep.extend({

        setup: function() {
            Dep.prototype.setup.call(this);

            // Customize breadcrumbs
            this.setupBreadcrumbs();

            // Add custom header actions
            this.addHeaderAction();
        },

        setupBreadcrumbs: function() {
            // Custom breadcrumb logic
        },

        addHeaderAction: function() {
            // Add custom button to header
            this.addButton({
                name: 'customAction',
                label: 'Custom Action',
                style: 'primary'
            });
        }
    });
});
```

### Registering Custom Views

After creating a custom view, register it in the clientDefs configuration:

**For record-level view override**:
File: `example-module/app/Resources/metadata/clientDefs/Product.json`

```json
{
    "recordViews": {
        "detail": "example-module:views/product/record/detail",
        "edit": "example-module:views/product/record/edit"
    }
}
```

**For complete page view override**:
```json
{
    "views": {
        "detail": "example-module:views/product/detail",
        "list": "example-module:views/product/list"
    }
}
```

**For both levels**:
```json
{
    "views": {
        "detail": "example-module:views/product/detail"
    },
    "recordViews": {
        "detail": "example-module:views/product/record/detail",
        "edit": "example-module:views/product/record/edit"
    }
}
```

### Best Practices

- **Most customizations should target `recordViews`** (the content area) rather than full page `views`
- Always call the parent method when overriding: `Dep.prototype.methodName.call(this)`
- Follow the same directory structure as core files for consistency
- Use meaningful method names that describe the customization
- Document complex overrides with comments
- Keep view-specific logic in views, not in controllers

---

## Creating Custom Controllers

Controllers in AtroCore manage the application flow and coordinate between views and models. You can create custom controllers for new pages or entities.

### Standard Controllers

Most entity pages use the default [record](https://gitlab.atrocore.com/atrocore/atrocore/-/blob/master/client/src/controllers/record.js) controller.

This controller handles:
- Record listing, creation, editing
- View switching (index, list, detail, edit)
- Navigation and routing

### Controller Routes

AtroCore controllers support specific route patterns that map directly to controller methods. The supported routes are:

- `list` - Display list view
- `view` - Display detail view (`view/:id`)
- `edit` - Display edit form (`edit/:id`)
- `create` - Display creation form
- `index` - Display index/home view (can be also used for non-entity pages, like Dashboard)


### Creating a Custom Controller

**Example 1**: Dashboard controller (non-entity page)

Reference [here](https://gitlab.atrocore.com/atrocore/atrocore/-/blob/master/client/src/controllers/home.js).

File: `client/modules/example-module/src/controllers/dashboard.js`

```javascript
Espo.define('example-module:controllers/dashboard', 'controllers/base', Dep => {
    return Dep.extend({

        // Default route action
        defaultAction: 'index',

        // Index method - renders the main dashboard view
        index: function(options) {

            // Create the main dashboard view
            this.main('example-module:views/dashboard', {
                scope: 'Dashboard'
            });
        },

        // Custom route handler (if you add custom routes) accessible using #Scope/customPage
        customPage: function(options) {
            this.main('example-module:views/custom-page', {
                id: options.id
            });
        }
    });
});
```

**Example 2**: Custom entity controller extending record controller

File: `client/modules/example-module/src/controllers/product.js`

```javascript
Espo.define('example-module:controllers/product', 'controllers/record', Dep => {
    return Dep.extend({

        // Override the list method
        list: function(options) {
            // Custom logic before showing list
            console.log('Showing product list');

            // Call parent method
            Dep.prototype.list.call(this, options);
        },

        // Override the view method
        view: function(options) {
            // Custom pre-processing
            if (!this.getAcl().check('Product', 'read')) {
                throw new Espo.Exceptions.AccessDenied();
            }

            // Call parent method
            Dep.prototype.view.call(this, options);
        },

        // Override the edit method
        edit: function(options) {
            // Custom logic for edit view
            Dep.prototype.edit.call(this, options);
        }
    });
});
```

### Configuring Non-Entity Pages

For pages that don't represent database entities (like Dashboard), you need to configure the scope:

**Scope Configuration**: `app/Resources/metadata/scopes/Dashboard.json`

```json
{
    "entity": false,
    "module": "example-module",
    "disabled": false
}
```

**ClientDefs Configuration**: `app/Resources/metadata/clientDefs/Dashboard.json`

```json
{
    "controller": "example-module:controllers/dashboard",
    "iconClass": "presentation-chart"
}
```

`IconClass` a key for entity icon, all the available icons can be checked [here](https://gitlab.atrocore.com/atrocore/atrocore/-/blob/master/app/Atro/Resources/metadata/app/systemIcons.json).


### Available Controller Methods

Controllers can override these standard methods:

**`list(options)`**
- Called for: `#Entity/list`
- Displays the list view
- `options`: May contain filter, pagination parameters

**`view(options)`**
- Called for: `#Entity/view/:id`
- Displays detail view for a specific record
- `options.id`: Record ID to display

**`edit(options)`**
- Called for: `#Entity/edit/:id`
- Displays edit form for existing record
- `options.id`: Record ID to edit

**`create(options)`**
- Called for: `#Entity/create`
- Displays creation form for new record
- `options`: May contain default field values

**`index(options)`**
- Called for: `#Entity` (root path for the entity/scope)
- Main entry point for non-entity pages
- Used primarily for dashboard-like views

### Controller Method Signature

All route methods receive an `options` object that may contain:

```javascript
{
    id: 'recordId',           // For view/edit routes
    model: modelInstance,     // Pre-loaded model (optional)
    rootUrl: '#Entity/list',  // Return URL
    params: {
        urlParamerter1: 'value' // url paramaters  #/Scope?urlParameter1=value&urlParameter2=value2
    }
    // ... other route-specific parameters
}
```

### Best Practices

- Always call parent methods when extending: `Dep.prototype.methodName.call(this, options)`
- Check ACL before displaying views
- Handle navigation using `this.getRouter().navigate()`
- Keep business logic in services and models, not controllers

---

## Overriding Relation Panels

Relationship panels display related entities in detail views. You can customize their behavior by extending the base relationship panel view.

### Base Relationship Panel

The core relationship panel is located [here](https://gitlab.atrocore.com/atrocore/atrocore/-/blob/master/client/src/views/record/panels/relationship.js).

### Creating a Custom Relationship Panel

**Example**: Customizing the Categories panel in Product detail view

File: `client/modules/example-module/src/views/product/record/panels/categories.js`

```javascript
Espo.define('example-module:views/product/record/panels/categories',
    'views/record/panels/relationship', Dep => {

    return Dep.extend({

        // Override setup to add custom logic
        setup: function() {
            Dep.prototype.setup.call(this);

            // Listen to custom events
            this.listenTo(this.model, 'change:status', function() {
                this.reRender();
            });
        },

        // Override action handlers
        actionSelectRelated: function() {
            // Custom selection logic
            this.notify('Selecting categories...', 'info');

            // Call parent method
            Dep.prototype.actionSelectRelated.call(this);
        },
    });
});
```

### Registering the Custom Panel

File: `example-module/app/Resources/metadata/clientDefs/Product.json`

```json
{
    "relationshipPanels": {
        "categories": {
            "create": false,
            "selectAction": "selectRelatedEntity",
            "createAction": "createRelated",
            "selectBoolFilterList": [
                "onlyLeafCategories"
            ],
            "view": "example-module:views/product/record/panels/categories",
            "rowActionsView": "views/record/row-actions/relationship"
        }
    }
}
```

### Panel Configuration Options

- `create`: Enable/disable inline creation of related records
- `select`: Enable/disable selecting existing records
- `selectAction`: Action to use for selection (default: `selectRelatedEntity`)
- `createAction`: Action to use to create related entity (default: `createRelated`)
- `selectBoolFilterList`: Filters applied during selection
- `view`: Custom panel view
- `rowActionsView`: Custom view for row actions
- `orderBy`: Default sorting field
- `orderDirection`: Sort direction (`asc` or `desc`)

---

## Overriding Field Views

Field views control how individual fields are displayed and edited. Each field type has a corresponding view that can be customized.

### Available Field Views

All field views are located [here](https://gitlab.atrocore.com/atrocore/atrocore/-/tree/master/client/src/views/fields)

Common field types include:
- `enum`: Dropdown selection
- `varchar`: Text input
- `text`: Textarea
- `int`: Integer input
- `float`: Decimal input
- `bool`: Checkbox
- `date`: Date picker
- `datetime`: Date and time picker
- `link`: Related record link
- `linkMultiple`: Multiple related records
- `array`: Multiple values

### Entity-Specific Field Customization

When you need to customize a field's behavior for a specific entity:

**Example**: Custom enum field for Product entity

File: `client/modules/example-module/src/views/product/fields/status.js`

```javascript
Espo.define('example-module:views/product/fields/status', 'views/fields/enum', Dep => {

    return Dep.extend({

        // Custom validation
        validateRequired: function() {
            if (this.isRequired()) {
                var value = this.model.get(this.name);

                // Custom validation logic
                if (!value || value === 'draft') {
                    var msg = this.translate('fieldIsRequired', 'messages')
                        .replace('{field}', this.getLabelText());
                    this.showValidationMessage(msg);
                    return true;
                }
            }
            return false;
        },

        // Custom setup
        setup: function() {
            Dep.prototype.setup.call(this);

            // Dynamic options based on user role
            if (this.getUser().get('rolesIds').includes('portal')) {
                this.params.options = ['draft', 'pending'];
            }

            // Add change listener
            this.listenTo(this.model, 'change:type', function() {
                this.reRender();
            });
        },

        // Override data method
        data: function() {
            var data = Dep.prototype.data.call(this);

            // Add custom data for template
            data.customClass = this.model.get('priority') === 'high' ? 'text-danger' : '';

            return data;
        },

        // Custom fetch (getting value from UI)
        fetch: function() {
            var data = Dep.prototype.fetch.call(this);

            // Additional processing
            if (data[this.name] === 'completed') {
                data.completedAt = new Date().toISOString();
            }

            return data;
        }
    });
});
```

### Registering the Custom Field View

File: `example-module/app/Resources/metadata/entityDefs/Product.json`

```json
{
    "fields": {
        "status": {
            "type": "enum",
            "options": ["draft", "pending", "approved", "published"],
            "default": "draft",
            "required": true,
            "view": "example-module:views/product/fields/status"
        }
    }
}
```

### Custom Field Template

If you need a custom template for your field:

File: `client/modules/example-module/res/templates/product/fields/edit/status.tpl`

```handlebars
<div class="field-status-wrapper {{customClass}}">
        <select class="form-control" data-name="{{name}}">
            {{#each params.options}}
                <option value="{{./this}}" {{#ifEqual ../value this}}selected{{/ifEqual}}>
                    {{translate this scope=../scope field=../name}}
                </option>
            {{/each}}
        </select>
</div>
```

File: `client/modules/example-module/res/templates/product/fields/detail/status.tpl`

```handlebars
<div class="field-status-wrapper {{customClass}}">
        <span class="status-badge status-{{value}}">
            {{translateOption value scope=scope field=name}}
        </span>
</div>
```

File: `client/modules/example-module/res/templates/product/fields/list/status.tpl`

```handlebars
 <span class="status-badge status-{{value}}">
            {{translateOption value scope=scope field=name}}
  </span>
```

Reference the template in your view:

```javascript
Espo.define('example-module:views/product/fields/status', 'views/fields/enum', Dep => {
    return Dep.extend({
        listTemplate: 'example-module:product/fields/list/status',
        detailTemplate: 'example-module:product/fields/detail/status',
        editTemplate: 'example-module:product/fields/edit/status'
    });
});
```

---

## Class Replace Feature

The class-replace feature allows you to modify field behavior globally across the entire application, not just for a specific entity.

### When to Use Class Replace

Use class-replace when you need to:
- Change default behavior of a field type everywhere
- Add global validation rules
- Modify rendering for all instances of a field type
- Inject additional functionality into core fields

### Implementing Class Replace

**Example**: Enhancing the global enum field

File: `client/modules/example-module/src/views/fields/enum.js`

```javascript
Espo.define('example-module:views/fields/enum',
    'class-replace!views/fields/enum', Dep => {

    return Dep.extend({

        // Override template if needed
        template: 'example-module:fields/enum',

        // Add global behavior
        setup: function() {
            Dep.prototype.setup.call(this);

            // Add global search capability to all enum fields
            this.searchEnabled = true;

            // Add color coding based on options
            this.colorMap = this.params.colorMap || {};
        },

        // Enhance rendering
        afterRender: function() {
            Dep.prototype.afterRender.call(this);

            // Apply color coding
            var value = this.model.get(this.name);
            if (this.colorMap[value]) {
                this.$el.css('color', this.colorMap[value]);
            }

            // Add search capability
            if (this.searchEnabled && this.mode === 'edit') {
                this.enableSearch();
            }
        },

        // New method available to all enum fields
        enableSearch: function() {
            var $select = this.$el.find('select');

            // Initialize search plugin (e.g., Select2)
            $select.select2({
                placeholder: this.translate('Select') + '...',
                allowClear: !this.params.required
            });
        }
    });
});
```

### Registering Class Replace

File: `example-module/app/Resources/metadata/app/clientClassReplaceMap.json`

```json
{
    "views/fields/enum": [
        "__APPEND__",
        "example-module"
    ],
    "views/fields/varchar": [
        "__APPEND__",
        "example-module"
    ]
}
```

### Understanding the Replace Chain

The `__APPEND__` placeholder represents all previous modifications from modules loaded before yours:

1. **Core View**: `views/fields/enum` (original)
2. **Module A**: Adds feature X
3. **Module B**: Adds feature Y
4. **Your Module**: `__APPEND__` includes X and Y, then adds feature Z

### Loading Order

The loading order of modules is determined by the `getLoadOrder()` method in each module's `Module.php` file located at `app/Module.php`:

```php
class Module extends AbstractModule {
    public static function getLoadOrder(): int {
        return 9999;
    }
}
```

- Higher values indicate that the module will load later
- Later-loading modules can override earlier modules
- Core AtroCore modules typically use lower values (e.g., 1000-5000)
- Your custom modules should use higher values (e.g., 9000-9999) to ensure they load last


!!! Be careful with class-replace as it affects the entire application. Always:
!!! - Test thoroughly across different entities
!!! - Document your changes
!!! - Consider backward compatibility
!!! - Use specific field overrides when possible instead of global changes

## Loading External Libraries

AtroCore provides a structured way to load external JavaScript libraries into your modules.

### Available Core Libraries

Core libraries are defined [here](https://gitlab.atrocore.com/atrocore/atrocore/-/blob/master/app/Atro/Resources/metadata/app/jsLibs.json).

These include common libraries like jQuery, Backbone, Handlebars, and utility libraries.

### Adding Custom Libraries

To add a new external library to your module:

**Step 1**: Add the library file to your module

Place your JavaScript library in:
```
client/modules/example-module/lib/leaflet.js
```

**Step 2**: Register the library

File: `example-module/app/Resources/metadata/app/jsLibs.json`

```json
{
    "Leaflet": {
        "path": "client/modules/example-module/lib/leaflet.js",
        "exportsTo": "window",
        "exportAs": "L"
    },
    "ChartJS": {
        "path": "client/modules/example-module/lib/chart.min.js",
        "exportsTo": "window",
        "exportAs": "Chart"
    },
    "CustomPlugin": {
        "path": "client/modules/example-module/lib/custom-plugin.js",
        "exportsTo": "$",
        "exportAs": "customPlugin"
    }
}
```

### Library Configuration Options

- `path`: Relative path to the library file from the project root
- `exportsTo`: Where to export the library (`"window"` or `"$"` for jQuery)
- `exportAs`: The global variable name for the library

**Example with `exportsTo: "window"`**:
```javascript
// Library will be available as window.L or just L
var map = L.map('map-container');
```

**Example with `exportsTo: "$"`**:
```javascript
// Library will be available as jQuery plugin
$('.element').customPlugin();
```

### Using Libraries in Views

**Step 3**: Load the library in your view

File: `client/modules/example-module/src/views/fields/point.js`

```javascript
Espo.define('example-module:views/fields/point',
    ['views/fields/base', 'lib!Leaflet'], Dep => {

    return Dep.extend({

        template: 'example-module:fields/point',

        // Leaflet is now available
        afterRender: function() {
            Dep.prototype.afterRender.call(this);

            // Use the loaded library
            var coordinates = this.model.get(this.name) || [0, 0];

            var map = L.map(this.$el.find('.map-container')[0]).setView(coordinates, 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            L.marker(coordinates).addTo(map);
        },

        remove: function() {
            // Clean up map instance
            if (this.map) {
                this.map.remove();
            }

            Dep.prototype.remove.call(this);
        }
    });
});
```

### Loading Multiple Libraries

You can load multiple libraries in a single view:

```javascript
Espo.define('example-module:views/dashboard',
    ['view', 'lib!Leaflet', 'lib!ChartJS', 'lib!CustomPlugin'],
    Dep => {

    return Dep.extend({
        // Use all loaded libraries
    });
});
```

### Loading CSS for Libraries

For libraries that require CSS, you can dynamically inject the stylesheet in the `afterRender` method:

```javascript
afterRender: function() {
    Dep.prototype.afterRender.call(this);

    // Check if CSS is already loaded
    if (!$('link[href*="leaflet.css"]').length) {
        $('<link>')
            .attr('rel', 'stylesheet')
            .attr('href', 'client/modules/example-module/lib/leaflet.css')
            .appendTo('head');
    }

    // Initialize library
    this.initializeMap();
}
```

### Best Practices for External Libraries

1. **Version Management**: Include version numbers in filenames (`leaflet-1.9.4.min.js`)
2. **Minification**: Use minified versions for production
3. **CDN Alternative**: Consider using CDN links for common libraries
4. **License Compliance**: Ensure proper licensing for all external libraries
5. **Cleanup**: Always clean up library instances in the `remove()` method
6. **Lazy Loading**: Load libraries only in views that need them

---

## Base View Methods

All views in AtroCore extend from the base [view]( https://gitlab.atrocore.com/atrocore/atrocore/-/blob/master/client/src/view.js).

This base class provides a comprehensive set of methods available to all views.

### Lifecycle Methods

**initialize(options)**
- Called when the view is first created
- **Important**: This method handles complex tasks like template compilation and nested view rendering. It is almost never overridden directly.


**setup()**
- Called after initialization but before rendering
- Use for preparing data, setting up listeners, and creating child views
- Most view customization happens here

```javascript
setup: function() {
    Dep.prototype.setup.call(this);

    this.listenTo(this.model, 'change', this.onModelChange);
    this.createChildViews();
}
```

**render(callback)**
- Renders the view by applying the template and inserting into DOM
- Takes an optional callback function executed after rendering completes

```javascript
this.render(() => {
    console.log('View rendered');
});
```

**afterRender()**
- Called after the view is rendered and inserted into the DOM
- Use for DOM manipulation, initializing plugins, and setting up event handlers
- Safe to access `this.$el` and DOM elements

```javascript
afterRender: function() {
    Dep.prototype.afterRender.call(this);

    this.$el.find('.custom-element').on('click', this.handleClick.bind(this));
    this.initializePlugins();
}
```

**remove()**
- Called when the view is being destroyed
- Use for cleanup: removing event listeners, destroying plugins, clearing timers
- Always call parent method to ensure proper cleanup

```javascript
remove: function() {
    // Clear custom timers
    if (this.updateTimer) {
        clearInterval(this.updateTimer);
    }

    // Clean up plugins
    this.$el.find('.select2').select2('destroy');

    Dep.prototype.remove.call(this);
}
```

### Rendering Methods

**reRender(force)**
- Re-renders the view
- `force`: If true, forces re-rendering even if view hasn't changed

```javascript
// Trigger re-render
this.reRender();

// Listen for render completion
this.listenTo(this, 'after:render', () => {
    console.log('View re-rendered');
});
```

**data()**
- Returns data object passed to the template
- Override to add custom template variables

```javascript
data: function() {
    var data = Dep.prototype.data.call(this);
    data.customValue = this.calculateCustomValue();
    data.isActive = this.model.get('status') === 'active';
    return data;
}
```

### DOM Events

AtroCore views handle DOM events through the `events` object rather than manual event binding. This ensures proper cleanup and follows Backbone conventions.

**Basic Events Object**:

```javascript
Espo.define('example-module:views/custom', 'view', Dep => {
    return Dep.extend({
        events: {
            'click a.sort': function(e) {
                var field = $(e.currentTarget).data('name');
                this.toggleSort(field);
            },
            'change select.filter': function(e) {
                this.applyFilter(e.currentTarget.value);
            },
            'submit form': function(e) {
                e.preventDefault();
                this.save();
            }
        }
    });
});
```

**Extending Parent Events**:

When extending a view that already has events, you need to merge the parent's events with your new ones:

```javascript
Espo.define('example-module:views/custom', 'views/record/list', Dep => {
    return Dep.extend({
        events: _.extend({
            'click .link': function(e) {
                e.stopPropagation();
                e.preventDefault();
                this.handleLinkAction();
            },
            'click .custom-button': function(e) {
                this.handleCustomAction();
            }
        }, Dep.prototype.events), // Merge with parent events
    });
});
```

**Event Syntax**:
- Format: `'event selector': function(e) { }`
- Event: click, change, submit, mouseover, etc.
- Selector: CSS selector scoped to this view's element
- If no selector provided, event binds to root element (`this.$el`)

### Child View Management

**createView(key, view, options, callback)**
- Creates a child view
- `key`: Unique identifier for the view
- `view`: View name or constructor
- `options`: Options passed to child view (including `el` for the container element)
- `callback`: Called when child is ready

```javascript
this.createView('header', 'views/header', {
    model: this.model,
    el: this.options.el + ' .header-container'
}, view => {
    view.render();
});
```

**hasView(key)**
- Checks if a child view exists
- Returns boolean

```javascript
if (this.hasView('sidebar')) {
    this.getView('sidebar').reRender();
}
```

**getView(key)**
- Returns a child view by key
- Returns undefined if view doesn't exist

```javascript
var listView = this.getView('list');
if (listView) {
    listView.collection.fetch();
}
```

**clearView(key)**
- Removes and destroys a child view
- Cleans up event listeners and DOM elements

```javascript
this.clearView('modal');
```

### Event Listening

**listenTo(object, event, callback)**
- Sets up event listener that's automatically cleaned up when view is removed
- Can listen to models, collections, or other views
- Use instead of direct event binding for proper memory management

```javascript
// Listen to model changes
this.listenTo(this.model, 'change:status', this.onStatusChange);

// Listen to collection events
this.listenTo(this.collection, 'sync', this.onCollectionSync);

// Listen to another view's events
this.listenTo(someView, 'after:render', this.onViewRendered);

// Listen to custom events on views
this.listenTo(this.getView('child'), 'customEvent', this.handleCustomEvent);
```

**listenToOnce(object, event, callback)**
- Like listenTo but fires only once

```javascript
this.listenToOnce(this.model, 'sync', () => {
    console.log('Model synced for the first time');
});
```

### Triggering Custom Events

In addition to listening to events, you can trigger custom events on models, collections, and views. This is useful for inter-component communication and custom workflows.

**Triggering events on models**:
```javascript
// Trigger a custom event on the model
this.model.trigger('customEvent', {data: 'value'});
this.model.trigger('statusChanged', this.model.get('status'));

// Other views listening to this model will receive the event
this.listenTo(this.model, 'customEvent', function(data) {
    console.log('Custom event received:', data);
});
```

**Triggering events on collections**:
```javascript
// Trigger a custom event on the collection
this.collection.trigger('bulkUpdate', {updated: 10});
this.collection.trigger('filterApplied', filterName);

// Listen to collection custom events
this.listenTo(this.collection, 'bulkUpdate', function(result) {
    this.notify('Updated ' + result.updated + ' records', 'success');
});
```

**Triggering events on views**:
```javascript
// Trigger a custom event on the current view
this.trigger('customAction', {param: 'value'});
this.trigger('validationComplete', isValid);

// Parent views can listen to child view events
var childView = this.getView('child');
this.listenTo(childView, 'customAction', this.handleChildAction);

// Child can trigger the event
childView.trigger('customAction', {param: 'value'});
```

**Triggering events on child views**:
```javascript
// Get a child view and trigger an event on it
var listView = this.getView('list');
if (listView) {
    listView.trigger('refresh', {force: true});
}

// The child view listens to its own events
this.listenTo(this, 'refresh', function(options) {
    if (options.force) {
        this.collection.fetch();
    }
});
```

**Common use cases for custom events**:

**Parent-child communication**:

```javascript
// Parent view
setup: function() {
    this.createView('editor', 'views/editor', {}, view => {
        this.listenTo(view, 'contentChanged', this.onContentChanged);
    });
},

// Child view (editor)
updateContent: function(content) {
    this.content = content;
    this.trigger('contentChanged', content);
}
```
**Workflow coordination**:

```javascript
// In a multi-step form view
completeStep: function(stepNumber) {
    this.trigger('step:completed', stepNumber);

    if (stepNumber === this.totalSteps) {
        this.trigger('form:complete', this.getData());
    }
}
```

**State synchronization**:

```javascript
// Notify other views of state changes
toggleMode: function(mode) {
    this.mode = mode;
    this.trigger('mode:changed', mode);
    this.reRender();
}
```

**Best practices**:
- Use namespaced event names (e.g., `'form:submit'`, `'step:completed'`) to avoid conflicts
- Always clean up listeners with `listenTo()` instead of manual `on()` bindings
- Document custom events in view comments for maintainability
- Pass relevant data with triggered events
- Consider using Backbone's built-in events when possible (e.g., `'sync'`, `'change'`)

**Model and Collection Access**:

In views, you have direct access to:
- `this.model` - The model instance (available in detail, edit, and other single-record views)
- `this.collection` - The collection instance (available in list views)

```javascript
// Accessing model
var productName = this.model.get('name');
this.model.set('status', 'active');

// Accessing collection in list views
var recordCount = this.collection.length;
this.collection.fetch();
```

### Helper Methods

**translate(label, category, scope)**
- Translates a label to the current language
- `category`: Category like 'labels', 'options', 'messages'
- `scope`: Entity or module scope

```javascript
var label = this.translate('Save', 'labels', 'Global');
var option = this.translate('active', 'fields', 'Product');
```

**getHelper()**
- Returns the helper object with utility methods
- Provides access to date formatting, language, metadata, etc.

```javascript
var helper = this.getHelper();
var formattedDate = helper.formatDate(dateString);
```

**getUser()**
- Returns the current user object
- Access user permissions and preferences

```javascript
var user = this.getUser();
if (user.isAdmin()) {
    // Show admin features
}
```

**getConfig()**
- Returns application configuration
- Access system settings

```javascript
var config = this.getConfig();
var localeId = config.get('localeId');
```

**getPreferences()**
- Returns user preferences
- Access user-specific settings

```javascript
var prefs = this.getPreferences();
var dashboardLayout = prefs.get('dashboardLayout');
```

**getMetadata()**
- Returns metadata object
- Access entity definitions, field metadata, etc.

```javascript
var metadata = this.getMetadata();
var fields = metadata.get(['entityDefs', 'Product', 'fields']);
```

**getLanguage()**
- Returns language helper
- Access translation methods

```javascript
var lang = this.getLanguage();
var translated = lang.translate('fieldName', 'fields', 'Entity');
```

**getDateTime()**
- Returns date/time helper
- Format and parse dates

```javascript
var dateTime = this.getDateTime();
var formatted = dateTime.toDisplay('2025-12-25 10:30:00');
```

### Notification Methods

**notify(message, type, timeout, closeButton)**
- Displays a notification message
- `type`: 'info', 'success', 'error', 'warning'
- `timeout`: Duration in milliseconds (default: 2000)
- `closeButton`: Show close button (boolean)

```javascript
this.notify('Record saved successfully', 'success');
this.notify('An error occurred', 'error', 5000);
this.notify('Please wait...', 'warning', null, true);
```

**Msg.notify(message, type, timeout)**
- Alternative notification method
- Can be called without view instance

```javascript
Espo.Ui.notify('Processing...', 'info');
```

### AJAX and Data Methods

**ajaxGetRequest(url, params)**
- Makes GET AJAX request
- Returns Promise

```javascript
this.ajaxGetRequest('MyEntity/action/getData', {
    id: this.model.id
}).then(response => {
    console.log('Data received:', response);
});
```

**ajaxPostRequest(url, data, params)**
- Makes POST AJAX request
- Returns Promise

```javascript
this.ajaxPostRequest('MyEntity/action/updateStatus', {
    id: this.model.id,
    status: 'active'
}).then(response => {
    this.notify('Status updated', 'success');
});
```

### Navigation Methods

**getRouter()**
- Returns the application router
- Use for navigation and URL changes

```javascript
var router = this.getRouter();
router.navigate('#Product/view/' + this.model.id, {trigger: true});
```

**navigate(path, trigger)**
- Navigate to a different page

```javascript
this.getRouter().navigate('#MyEntity/list', {trigger: true});
```

### Utility Methods

**wait(boolean)**
- Controls whether the view is ready to render
- Used in `setup()` to prevent view from rendering until certain conditions are met
- When `wait(true)` is called, rendering is paused
- Call `wait(false)` when ready to allow rendering to proceed

```javascript
setup: function() {
    Dep.prototype.setup.call(this);

    // Prevent rendering until data is loaded
    this.wait(true);

    this.model.fetch().then(() => {
        // Allow rendering to proceed
        this.wait(false);
    });
}
```

**getSelector()**
- Returns CSS selector for this view's element

```javascript
var selector = this.getSelector();
// Returns something like '.view-container[data-id="viewId"]'
```

## Best Practices Summary

1. **Follow Core Structure**: Mirror the folder structure of core AtroCore files
2. **Use Inheritance**: Extend views instead of copying code
3. **Call Parent Methods**: Always invoke parent methods when overriding
4. **Cleanup Resources**: Implement proper cleanup in `remove()` method
5. **Scope Customizations**: Use entity-specific overrides when possible
6. **Document Changes**: Comment complex customizations
7. **Test Thoroughly**: Verify changes across different views and contexts
8. **Version Control**: Track module versions for dependency management
9. **Follow Naming**: Use consistent naming conventions
10. **Minimize Global Changes**: Use class-replace sparingly

---

## Additional Resources

- AtroCore Core [Views](https://gitlab.atrocore.com/atrocore/atrocore/-/tree/master/client/src/views)
- Field [Views](https://gitlab.atrocore.com/atrocore/atrocore/-/tree/master/client/src/views/fields):
- Record [Controller](https://gitlab.atrocore.com/atrocore/atrocore/-/blob/master/client/src/controllers/record.js)
- Base [View]( https://gitlab.atrocore.com/atrocore/atrocore/-/blob/master/client/src/view.js)
- ClientDefs [Reference](https://gitlab.atrocore.com/atrocore/atrocore/-/tree/master/app/Atro/Resources/metadata/clientDefs)
- Module Development [Guide](https://help.atrocore.com/developer-guide/own-modules)

---

## Conclusion

This guide provides a comprehensive foundation for frontend development in AtroCore. The framework's modular architecture, combined with BackboneJS, Handlebars, and Svelte components, offers powerful customization capabilities while maintaining clean code organization.

Key takeaways:
- **View Hierarchy**: Understand the distinction between page-level views (`views/*`) and record-level views (`views/record/*`)
- **Inheritance Pattern**: Views are the primary customization point - always extend rather than modify
- **ClientDefs Control**: Entity behavior and appearance are configured through clientDefs metadata
- **Base View Power**: The base view provides extensive helper methods for common tasks
- **External Libraries**: Seamlessly integrate third-party JavaScript libraries
- **Svelte Integration**: Modern reactive components available alongside BackboneJS views
- **Custom Helpers**: Rich set of Handlebars helpers for template rendering

!! The majority of customizations should target `recordViews` (content area) rather than full page `views`, unless you specifically need to modify page-level chrome like headers, breadcrumbs, or navigation.

For specific implementation details, always refer to the core AtroCore repository and existing module examples.
