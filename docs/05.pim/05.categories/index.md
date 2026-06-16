---
title: Categories
---

**Category** – an efficient way to organize the products by their type, which helps target consumer find the desired products faster.

Categories make up a powerful tool that can be used not only to sort your content, but also to develop a proper, i.e. meaningful and semantic, structure of your product catalog. Categories have a hierarchical taxonomy, meaning that there are parent and child categories.

Customers search for the desired products in online shops and marketplaces in two ways: via the search function or via categories and sub-categories. Product attributes are used for additional filtering of the products found in either of these two ways. The faster the customer finds the desired product, the more likely he will make a positive purchase decision, that is why correct categorization is of great importance. For marketing purposes, each product is usually assigned to one or more categories.

**Category tree** – the aggregate of all categories and parent–child relations among them. Category tree starts with a *root category* – a category, which has no parent category, and ends with many branches of categories without subcategories (i.e. *child categories*).

**Parent Category** – a category to which the category is assigned. If "Berlin" is a category, "Germany" may be its parent category.

**Subcategories** – all child categories, assigned to a certain category. Subcategories for category "Germany" may be "Berlin", "Munich", "Hannover" and so on.

There can be many category trees in AtroPIM. Each category can have only one parent category. Each category may have a lot of subcategories. Many products can be assigned to one category, and each product can be assigned to more than one category in accordance with the catalog content.

## One Category Tree vs Multiple Category Trees

Each adopter of [AtroPIM](../../01.atrocore/02.getting-started) may decide for himself what works better for him – setting up and supporting multiple category trees or just one. Irregardless of the choice, it is still possible to synchronize different content for products you want to supply.

## Product Categories in Multiple Languages

Even if you want to manage your product content in different languages, there is no need in maintaining multiple category trees.

There are two ways to set up your product catalog if you carry product information in different languages:

1. Create a separate category tree for each language / locale.
2. Create just one category tree using multi-language fields for the category name.

The first approach is preferable, if you want to provide different channels with different product catalogs, e.g. some product should be transferred to channel 1, but not to channel 2. The second one is a better choice if you want to deliver the product information about all your products to all channels.

## Category Fields

The category entity comes with the following preconfigured fields; mandatory are marked with *:

| **Field Name**           | **Description**                                                                                                                                                               |
|--------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Active                   | Activity state of the category record. Activating a category automatically activates all its parent categories; deactivating it automatically deactivates all child categories. |
| Name (multi-lang) *      | The category name.                                                                                                                                                            |
| Parent Category          | The category to be used as a parent for this category.                                                                                                                        |
| Code                     | Unique value used to identify the category. It can only consist of lowercase letters, digits and underscore symbols.                                                          |
| Description (multi-lang) | Description of the category usage.                                                                                                                                            |

To make changes to the category entity (e.g. add new fields or modify category views), go to Administration / Entities / Category.

## Listing

To open the list of category records available in the system, click the `Categories` option in the navigation menu:

![Categories list view page](./_assets/categories-list-view.png){.large}

Categories are displayed as a hierarchical **tree view**, reflecting the parent–child structure of the category trees. You can expand and collapse branches directly in the list. To switch between tree and flat list display, use the tree-view toggle button above the list.

![Categories tree view](./_assets/tree-view.jpg){.large}

By default, the following fields are displayed on the [list view](../../01.atrocore/04.understanding-ui/index.md#list-view) page for category records:

 - Name
 - Main image
 - Code
 - Channels

To change the category records order in the list, click any sortable column title; this will sort the column either ascending or descending.

Category records can be searched and filtered according to your needs. For details on the search and filtering options, refer to the [**Search and Filtering**](../../01.atrocore/11.search-and-filtering) article in this user guide.

To view some category record details, click the name field value of the corresponding record in the list of categories; the [detail view](../../01.atrocore/04.understanding-ui/index.md#detail-view) page will open showing the category records and the records of the related entities. Alternatively, use the `View` option from the single record actions menu to open the [quick detail](../../01.atrocore/04.understanding-ui/index.md#quick-detail-view-small-detail-view) pop-up.

### Mass Actions

The following mass actions are available for category records on the list view page:

- Remove
- Compare
- Select
- Update
- Export
- Add relation
- Remove relation

![Categories mass actions](./_assets/categories-mass-actions.png){.medium}

For details on these actions, refer to the [**Mass Actions**](../../01.atrocore/04.understanding-ui/index.md#mass-actions) section of the **Views and Panels** article in this user guide.

### Single Record Actions

The following single record actions are available for category records on the list view page:

- View
- Edit
- Delete
- Bookmark

![Categories single record actions](./_assets/categories-single-actions.png){.large}

For details on these actions, please, refer to the [**Single Record Actions**](../../01.atrocore/04.understanding-ui/index.md#single-record-actions) section of the **Views and Panels** article in this user guide.

## Working With Entities Related to Categories

Relations to files, channels, products and child categories are available for all categories by default. These related entities records are displayed on the corresponding panels on the category [detail view](../../01.atrocore/04.understanding-ui/index.md#detail-view) page. If any panel is missing, please, contact your administrator as to your access rights configuration.

To be able to relate more entities to categories, please, contact your administrator.

### Files

Files that are linked to the currently open category record are displayed on its page on the `FILES` panel.

![Images panel](./_assets/images-panel.png){.large}

On this panel, you can link files to the given category record by selecting the existing ones (`Select`) or creating new file records (`Upload`).

In the "Files" pop-up that appears, choose the desired file (or files) from the list and press the `Select` button to link the item(s) to the category record.

To set a file as the main image for a category, select the appropriate option in the menu (the file has to be an image). Files linked to the given category record can be viewed, edited, reuploaded, unlink or removed via the corresponding options from the single record actions menu on the `Files` panel:

![Images actions](./_assets/images-actions-menu.png){.large}

On the `FILES` panel you can also define image records order within the given category record via their drag-and-drop:

![Images order](./_assets/images-order.png){.large}

The changes are saved on the fly.

To view the category related image record from the `FILES` panel, click its name in the images list. The page of the given file will open, where you can perform further actions according to your access rights, configured by the administrator.

### Channels

Channels that are linked to the category record are shown on the `CHANNELS` panel within the category page.

![Channels categories panel](./_assets/Channels-categories-panel.png){.large}

A category can be associated with multiple channels, and a channel can be associated with multiple categories, which makes it possible to maintain channel-specific category trees. For details on this relationship, refer to the [Channel links to Categories, Listings and Classifications](../06.channels/index.md#channel-links-to-categories-listings-and-classifications) section of the Channels article.

When a new subcategory is created under a parent category, it automatically inherits all channels that are linked to the parent category.

When a product is assigned to a category, it is automatically assigned to the channels to which the category tree of that category is linked.

### Products

Products that are linked to the category record are shown on the `PRODUCTS` panel within the category page, and on the `Categories` field within the [Product](../03.products/index.md#categories) page.

Before categorizing products, make sure that all necessary categories have been created and that the existing category trees are assigned to the corresponding channels, so that these categories can be used for the products distributed via those channels.

#### Assigning categories to a product

To assign a product to one or more categories, open the product record and use the `Categories` field on the Taxonomy panel; refer to the [Categories](../03.products/index.md#categories) section of the Products article for details. By default, products can only be assigned to leaf categories (categories with no children); this behavior is configurable in PIM Settings. It is a good idea to refer to the category code, as the category names of different trees can be duplicated.

#### Assigning products to a category

To add products to a category directly from the category side, open the `PRODUCTS` panel on the category record, click the `▼` icon and select `Select`. In the opened pop-up, select all the products that should be added and confirm with `Select`.

![Product categories panel](./_assets/product-categories-panel.png){.large}

Products listed in the `PRODUCTS` panel can be reordered by drag-and-drop. The order is saved per category and is independent for each category tree.

#### Setting a main category for a product

Within the `PRODUCTS` panel, each product–category link can be marked as the **main category** for that product within the given category tree. To set or change the main category, open the detail pop-up of the product record from the `PRODUCTS` panel — the `Main Category` checkbox is displayed there. Only one category per category tree can be marked as the main category for a given product.

![Set main category](./_assets/set-main-category.png){.large}

#### Mass addition, change, or removal of categories for selected products

On the product list page, a relation to one or more categories can be added or removed for several selected products at once (e.g. after filtering), using the `Add Relation` and `Remove Relation` mass actions.

![Categories mass actions](./_assets/categories-mass-actions.png){.medium}

To do this, click `Add Relation` or `Remove Relation`, select `Categories` in the `Select Entity` field of the opened pop-up, and then select the necessary categories. As a result, the relation between the selected categories and the preselected products is added or removed accordingly.

For general information on this functionality, refer to the [**Mass Actions**](../../01.atrocore/12.mass-actions/index.md) article.

### Child categories

Child categories that are linked to the category record are shown on the `CHILD CATEGORIES` panel within the category page.

![Child categories panel](./_assets/Child-categories-panel.png){.large}

Child categories can be reordered by drag-and-drop within this panel. The display order is reflected in the category tree view.

> **Note:** The behavior when deleting a category that has subcategories or linked products is configurable via **Administration → PIM Settings**. By default, deletion cascades — all subcategories are removed and all product links are unlinked. If the *Restrict* behavior is configured, deletion is prevented when products or subcategories exist. See [PIM Administration](../01.administration/index.md) for details.
