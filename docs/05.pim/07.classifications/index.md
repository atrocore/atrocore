---
title: Classifications
---

**Classification** is a grouping of similar products that share the same production processes, physical characteristics, and potentially the same customer segments, distribution channels, or pricing methods. In AtroPIM, Classifications serve as templates that define which [attributes](../../01.atrocore/03.administration/12.attribute-management/01.attributes/index.md) are collected for a given group of products. When you assign a Classification to a product, all attributes defined in that Classification are automatically linked to it — making Classifications the primary tool for ensuring consistent and structured product data across your catalog.

For full technical details — including field descriptions, creation, duplication, editing, deletion, listing, attribute assignment, and enabling Classifications for an entity — refer to the [AtroCore Classifications documentation](../../01.atrocore/03.administration/12.attribute-management/04.classifications/index.md).

## Why Use Classifications

Without Classifications, every product would need its attributes configured manually and individually. Classifications eliminate that by letting you define the attribute set once and apply it to as many products as needed. This is especially valuable when you manage large catalogs with many products of the same type.

For example, all products in the "Clothing" category share the same descriptive attributes — material, color, size, gender, season. Instead of linking those attributes to each product separately, you create a "Clothing" Classification with all of them defined, and every clothing product automatically gets the full set the moment you assign the Classification.

By default, a product can be assigned to multiple Classifications, which is useful when a product belongs to more than one family. A waterproof hiking jacket might belong to both "Outerwear" and "Sports Equipment", inheriting attributes from both. If your data model requires exactly one Classification per product, the **Single Classification only** option can be configured in `Administration > Entities`.

## Planning Your Classifications

Before creating a new Classification, check whether a suitable one already exists to avoid duplicates. Name each Classification clearly so its attribute set is obvious from the name alone — "Shoes", "Laptops", "Fresh Produce" are better than generic names like "Category A".

When two Classifications have similar names, use the **Description** field to explain the difference and clarify which products each one covers. The **Code** is always unique and can serve as an unambiguous identifier when names are too similar to distinguish at a glance.

A common pattern is to start from an existing Classification and duplicate it when a product family needs a slightly different attribute set. For example, if a new clothing collection introduces a "Style" attribute that older collections did not have, you can duplicate the "Clothing" Classification, name it "Clothing New", and add "Style" as a required attribute — without affecting the original Classification or the products already assigned to it.

## Managing Products Within a Classification

The **Products** panel on the Classification detail view lists all products currently assigned to that Classification. From this panel you can create new products directly within the Classification, link existing products, and view, edit, unlink, or remove individual product records. Use **Show full list** to open the full product list pre-filtered by that Classification.

## Controlling How Attributes Work on Products

The way Classifications and attributes interact on product records can be adjusted per entity in `Administration > Entities`. This is useful for enforcing data governance rules across your catalog:

- **Delete attribute values after unlinking classifications** — useful when a Classification is reassigned and you want to clean up stale attribute values that no longer apply to the product
- **Disable direct attribute linking** — useful in tightly governed catalogs where all product attributes must come from a Classification, preventing editors from adding ad-hoc attributes that fall outside the defined templates
- **Single Classification only** — useful when your data model or downstream integrations expect exactly one product template per record
- **Link Attributes with the Classification automatically** — useful during catalog buildout when editors are still discovering which attributes a product family needs; any attribute added directly to a product is also pushed back to its Classification, keeping the template up to date

Full descriptions of each option are in the [AtroCore Classifications documentation](../../01.atrocore/03.administration/12.attribute-management/04.classifications/index.md#enabling-classifications-for-an-entity).

## Recommended Extensions

- **[Advanced Classification](https://store.atrocore.com/en/advanced-classification/20110)** — adds greater control over attribute inheritance and classification-to-record relationships
- **[ETIM Classification](https://store.atrocore.com/en/etim-classification/20132)** — enables working with the international ETIM standard for classifying technical products, relevant for the electrical and electronics industries
- **[Data Quality](https://store.atrocore.com/en/data-quality/20218)** — tracks content completeness based on attributes defined in Classifications. Automatically calculates a completeness score per product based on filled-in attributes, helping teams identify and prioritize incomplete products before publication or export.
- **[Translations](https://store.atrocore.com/en/translations/20191)** — provides automatic translation for multilingual Classification fields such as name and description
