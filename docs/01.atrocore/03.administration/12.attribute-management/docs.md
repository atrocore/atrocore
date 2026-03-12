---
title: Attribute management
taxonomy:
    category: docs
---

**Attributes** – characteristics of a certain item that distinguish it from others. For products it can be size, color, functionality, components and features that affect the product's attractiveness or acceptance in the market.

Attributes can be added to [Products](../../../05.pim/03.products/docs.md) and any other entity within the system. By default, attributes are enabled for Products. To use attributes with other entities, you must activate the "Has Attributes" option in the [Entity Manager](../11.entity-management/docs.md).

In AtroCore, an Attribute is a dynamic field that behaves similarly to a standard [field](../11.entity-management/03.fields-and-attributes/docs.md) but offers greater flexibility. Unlike static fields, attributes are not added to all records of an entity by default. Instead, they can be applied to specific groups or individual records as needed.

This approach allows attributes to describe characteristics that vary across records — ideal for cases where not all items share the same properties. When you create a field in the system, a dedicated column is automatically created in the database, and the field becomes available for all records of the entity. In contrast, an attribute is added only to specific records where it is explicitly assigned.

Key Features of Attributes:
- **Selective Application:** Attributes are assigned only to relevant records, reducing unnecessary data complexity.
- **Field-Like Behavior:** Attributes have the same data types and behavior as fields.
- **Inheritance:** Attributes are inherited from parent to child entities.
- **Activity Tracking:** Changes to attributes are tracked in the Activities.
- **Import & Export:** Attributes can be imported and exported just like regular fields.
- **Duplication:** They can be duplicated when copying records.
- **Workflow Integration:** Attributes are fully supported in workflows, allowing you to build automation based on their values.