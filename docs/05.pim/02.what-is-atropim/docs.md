---
title: What is AtroPIM
taxonomy:
    category: docs
---  

Here is the current draft of the **"What is AtroPIM"** chapter, based on your previous requirements. We can treat this text as our working document.

---

# What is AtroPIM?

**AtroPIM** is a modern, open-source Product Information Management (PIM) system designed to help businesses centralize, organize, and enrich their product data. Built on the flexible **AtroCore** platform, it serves as a single source of truth for all product-related information, ensuring consistency and quality across every sales channel.

In a complex e-commerce environment, product data is often scattered across spreadsheets, ERP systems, and supplier files. AtroPIM solves this "data chaos" by providing a user-friendly interface to manage technical specifications, marketing descriptions, and digital assets (images, videos, documents) in one place.

Its API-first architecture makes it highly adaptable, allowing seamless integration with e-commerce platforms (like Magento, Shopify, Shopware), ERPs, and marketplaces.

## For Whom?

AtroPIM is designed for organizations that manage large volumes of product data or sell across multiple channels. It is particularly beneficial for:

### Industries

* **Manufacturers:** To maintain a "Golden Record" of technical data and share it with distributors.
* **Wholesalers & Distributors:** To aggregate data from multiple suppliers, standardize it, and distribute it to retailers.
* **Retailers & E-commerce:** To enrich product descriptions, manage SEO data, and push updates to online stores and marketplaces instantly.

### Roles

* **Product Managers:** Who need an efficient workspace to update specifications and manage product life cycles.
* **Marketing Teams:** Who need to enrich products with compelling descriptions and media assets without touching the ERP.
* **IT & Developers:** Who require a flexible, open-source system with a robust API to build custom integrations and workflows.


## Main Advantages

### Flexibility

AtroPIM stands out because it adapts to your business, not the other way around. Its configurable data model allows you to create new entities, add custom fields, and define complex relationships directly from the user interface—no coding required. This flexibility extends to the user experience as well; you can tailor layouts, dashboards, and navigation menus to match the specific workflows of different teams. Under the hood, the system is built on a modular, API-first architecture, meaning every custom field or configuration you create is instantly available via the REST API. whether you need to deploy on-premise for full data sovereignty or in the cloud for scalability, AtroPIM provides the versatile foundation needed to manage even the most non-standard product catalogs.

### Scalability

AtroPIM is engineered to grow alongside your business, capable of handling everything from small catalogs to enterprise environments with millions of records. The system utilizes a robust Job Manager subsystem to handle resource-intensive tasks—such as bulk data imports, channel feeds, and mass updates—in background queues. This ensures that the user interface remains fast and responsive even during heavy data processing. As your data volume increases, AtroPIM supports both vertical scaling (adding CPU/RAM resources) and efficient load management, allowing you to expand your product range and sales channels without performance bottlenecks.

### Modularity

AtroPIM follows a strict modular design philosophy based on the "building block" principle. The core system is lightweight, providing essential PIM functionality, while specific features—such as connectors to third-party systems (Magento, Shopify, ERPs), advanced export feeds, or specific data processing tools—are added as separate modules.  This approach ensures your system remains lean and performant because you only install and maintain the features your business actually requires. It also simplifies future upgrades and allows developers to build custom modules that plug seamlessly into the existing architecture without altering the core code.

### Configurability

True to its low-code nature, AtroPIM offers extensive configurability that empowers administrators to shape the software without writing a single line of code. Through the intuitive administration interface, you can modify data entities, create custom fields (of any type), and redesign layouts to fit your team's specific workflow. Whether you need to rearrange the dashboard, define unique validation rules, or set up complex permission roles, these changes are applied instantly across the system and the API. This significantly reduces the time and cost typically associated with software customization, allowing the system to evolve as quickly as your business requirements.


## Main Entities

To use AtroPIM effectively, it is essential to understand its core data structures and how they interact to organize your data.

### 1. Products

The central entity of the system. A **Product** record contains all the base information about an item, such as SKU, name, and price. It acts as the container for all other related data (attributes, images, descriptions).

### 2. Classifications

Classifications are used to organize products into hierarchies or taxonomies (e.g., categories or industry standards like ETIM or ECLASS).

* **Context:** A product can belong to one or more classifications.
* **Inheritance:** Classifications drive data structure. When you link a product to a specific Classification (e.g., "Smartphones"), the system automatically assigns the relevant technical attributes (e.g., "Screen Size," "Battery Capacity") to that product, ensuring data completeness.

### 3. Attributes & Attribute Groups

Attributes define the specific characteristics of a product.

* **Attributes:** These are the actual data fields (text, integer, boolean, select lists, etc.) used to describe the product (e.g., *Color, Voltage, Material*).
* **Attribute Groups:** These allow you to bundle related attributes together logically (e.g., grouping *Length, Width, Height* into a "Dimensions" group).

### 4. Attribute Tabs

While groups organize attributes logically, **Attribute Tabs** organize the visual layout of the Product Detail page to improve usability.

* Instead of presenting users with one massive list of fields, you can distribute Attribute Groups into distinct Tabs (e.g., *General Information, Technical Specs, Logistics, Marketing*).
* This ensures that different departments (like Logistics vs. Marketing) can quickly find the specific data they are responsible for within the same product record.

### 5. Channels

Channels represent the destinations where your product data will be published.

* You can define specific scopes for each channel. For instance, a product might have a long description for your *Main Website* channel and a shorter, bullet-point description for your *Amazon* channel.
* Channels allow you to filter which products are active on which platform.

### 6. Associations

Associations define the relationships between products. These are critical for e-commerce strategies.

* **Cross-selling:** Suggesting complementary items (e.g., a camera and a lens).
* **Up-selling:** Suggesting a premium version of the current item.
* **Bundling:** Grouping products to be sold together (kits).

### 7. Digital Assets (DAM)

AtroPIM includes built-in Digital Asset Management. This allows you to upload images, PDFs, and videos directly to the product record. You can link a single image to multiple products or define which image is the "Main Image" for a specific output channel.

## Why Choose AtroPIM?

* **Configurability:** You can add new fields, entities, and relationships directly from the UI without coding.
* **Open Source:** Full control over your data and the ability to host it on your own servers (Self-Hosted) or in the cloud.
* **Headless Ready:** The REST API allows you to decouple the backend from the frontend, making it ideal for modern headless commerce stacks.


## License

AtroPIM is published under the GNU GPLv3.

## Support

- For support please contact us - visit [our website](https://atropim.com/contact).

