---
title: What is AtroCore
---  

AtroCore is a free and open-source software ecosystem released under the GPLv3 license, purpose-built for the rapid development of modern, web-based, responsive business applications. It serves as a flexible foundation for systems such as ERP, PIM, CRM, DMS, MDM, DAM, and many other data-driven solutions. With a rich set of features available out of the box, AtroCore significantly reduces development time and total cost of ownership.

Architecturally, AtroCore is a single-page application (SPA) built on an API-centric, service-oriented design. Its highly flexible data model is based on configurable entities, attributes, and relationships, enabling you to model virtually any business domain. Data structures and workflows can be created and adapted largely through intuitive configuration, minimizing the need for custom code.

AtroCore centralizes all your business data and content in one place, making it easy to manage, enrich, and distribute information across multiple channels. By replacing fragmented spreadsheets and manual processes with a structured, scalable platform, AtroCore helps organizations regain control over their data, streamline operations, and eliminate the chaos of Excel-based data management.

> If you need a customizable core platform that grows with your business and adapts to changing data requirements, AtroCore provides a future-proof alternative to rigid, monolithic enterprise systems.

## For Whom

AtroCore is designed for organizations that need a flexible platform to solve complex business challenges and integrate seamlessly into existing IT landscapes. If your organization needs adaptability, integration capabilities, and long-term scalability rather than rigid standard solutions, AtroCore provides a strong and future-proof foundation.

AtroCore is the best fit **for businesses**, who want to:

- address custom and evolving business requirements that cannot be covered by off-the-shelf software;
- centrally store, manage, and structure data while orchestrating end-to-end business processes;
- implement a middleware layer to reliably integrate and synchronize third-party systems and services;
- deliver measurable added value and a superior experience for employees, customers, and partners;
- extend and modernize existing software ecosystems without replacing established systems.

## For What

AtroCore is a versatile software platform that supports a wide range of data, process, and integration use cases across the enterprise.

A single AtroCore instance can simultaneously serve as an MDM hub, PIM system, and integration layer—eliminating data silos while enabling consistent data flows across ERP, eCommerce, and analytics platforms.

AtroCore software can be effectively used as a foundation for:
- Master Data Management (MDM) – centralizing, governing, and maintaining consistent core business data;
- Product Information Management (PIM) – managing, enriching, and distributing product data across multiple channels;
- Application Development Platform – rapidly building custom, web-based business applications on a flexible core;
- System Integration Software – acting as a middleware layer to connect, synchronize, and orchestrate third-party systems;
- Business Process Management (BPM) – modeling, executing, and optimizing business workflows and processes;
- Data Warehouse Software – structuring and consolidating data for reporting, analytics, and decision-making;
- Digital Asset Management (DAM) – organizing, enriching, and distributing digital assets such as images and media files;
- Reference Data Management (RDM) – maintaining controlled vocabularies and standardized reference datasets;
- File Management – securely storing, organizing, and controlling access to files across the organization.


## AtroCore's Main Concepts

This chapter introduces the foundational pillars of the AtroCore platform. Understanding these concepts is essential for leveraging the system's full potential, particularly its ability to adapt to complex and evolving data structures without the need for extensive custom development.

### 1. Entity: The Building Block

At the heart of AtroCore lies the concept of the **Entity**. In the system, an entity represents a distinct type of object or data record—such as a Product, a Customer, an Asset, or a Task.

While AtroCore comes with standard entities out-of-the-box, its true power resides in its **Configurable Data Model**. This model allows administrators to modify existing entities or create entirely new ones directly from the user interface.

**Key capabilities include:**

* **Custom Entity Creation:** You are not limited to the default data schema. You can define new business objects that match your specific operational reality.
* **Field Management:** Add, remove, or modify fields (properties) for any entity. Whether you need a simple text field, a complex relation, or a calculated value, the data model supports it.
* **Relationship Mapping:** Define how different entities interact. You can configure One-to-One, One-to-Many, or Many-to-Many relationships (e.g., linking a "Product" entity to multiple "Supplier" entities).


### 2. Data Auditing

Data integrity and traceability are critical for enterprise applications. AtroCore addresses this with a robust data auditing system that records every change, whether made by a human or the system itself.
This creates an immutable history of the data lifecycle, ensuring transparency and accountability.

**The Stream records:**

* **Who:** The user or API integration that initiated the change.
* **What:** The specific field that was altered, showing both the *Old Value* and the *New Value*.
* **When:** The precise timestamp of the modification.

This feature is invaluable for debugging data issues, tracking user activity, and meeting compliance requirements. You can view the history of any specific record to see exactly how it has evolved over time.

### 3. Classifications and Attributes

To handle diverse and heterogeneous data, particularly in Product Information Management (PIM) contexts, AtroCore uses a system of **Classifications** and **Attributes**. Classifications can be applied to any entity in the system, not just products.

Unlike standard fields which apply to every record of an entity (e.g., every product has a "Name" and "SKU"), Attributes are **dynamic fields** that are assigned based on the classification of the record.

**How it works:**

* **Attributes:** These are specific characteristics, such as "Screen Size," "Voltage," "Fabric Type," or "HDMI Ports." They are defined globally but applied selectively.
* **Classifications:** These act as categories or families (e.g., "Electronics > Televisions" or "Apparel > T-Shirts").
* **Dynamic Assignment:** When a record is linked to a specific Classification, it inherits the relevant Attributes.

> **Example:** A product classified as a "Laptop" will automatically display attributes for *Processor Speed* and *RAM*, whereas a product classified as a "T-Shirt" will display fields for *Size* and *Material*. This ensures your data entry forms remain clean and relevant.


AtroCore is a modern, open-source data platform that serves as the foundation for complex data management solutions like PIM (Product Information Management), MDM (Master Data Management), and DAM (Digital Asset Management). Its architecture is designed specifically to address the limitations of legacy software, focusing heavily on being a "low-code" framework that developers can extend and business users can configure.

Here is an analysis of AtroCore’s architecture across the four key dimensions you requested:


## Main Advantages

### 1. Modularity

Modularity is the central design philosophy of AtroCore. The system is not a monolithic block of code but rather a collection of discrete packages.

* **Core vs. Extensions:** At the bottom layer sits the "AtroCore" system (similar to an operating system for data). On top of this, you install modules. For example, the PIM functionality is actually a module (`AtroPIM`), and the DAM functionality is another (`AtroDAM`).
* **Granular Add-ons:** Beyond the main modules, there are dozens of smaller plugins for specific tasks, such as specific export feeds (e.g., Google Shopping connector), data quality tools, or PDF generators.
* **Dependency Management:** This modularity allows you to keep the system lightweight. If you do not need DAM features, you simply do not install that module, keeping the database cleaner and the interface simpler.
* **Update Safety:** Because functionality is compartmentalized, updates can often be applied to specific modules without risking the stability of the entire core system (provided dependencies are managed correctly via Composer).

### 2. Configurability

AtroCore is designed to be a "Low-Code/No-Code" platform for administrators. It empowers non-technical users to change how the system behaves without needing to hire a developer.

* **Entity Manager:** You can create new data entities (tables) and fields directly from the UI. If you need to track "Suppliers" or "Fabrics," you can create those entities, define their relationships (One-to-Many, Many-to-Many), and start using them immediately.
* **Layout Manager:** The user interface is fully configurable via drag-and-drop. You can define exactly which fields appear on the "Detail," "List," and "Edit" views. You can even create different layouts for different user roles (e.g., a translator sees different fields than a product manager).
* **Dynamic Data Models:** You can add various field types—including Enum, Multi-Enum, Text, Currency, and physical Units—on the fly. The system handles the database schema updates in the background.
* **Workflows (BPM):** AtroCore includes a configurable workflow engine. You can define logic triggers (e.g., "If a product price changes, send an email to the manager" or "If data completeness is 100%, publish to Shopify") without writing PHP code.

### 3. Flexibility

Flexibility refers to the system's ability to adapt to environments and use cases that were not originally anticipated.

* **API-First Architecture:** AtroCore is a Single Page Application (SPA). The frontend communicates with the backend entirely via REST API. This means "Headless" implementation is native to the platform. You can use AtroCore as a backend for a mobile app, a web portal, or a third-party ERP without using its default UI.
* **Data Agnostic:** While primarily used for Product Data, the system is flexible enough to handle any master data (Customer Data, Asset Data, HR Data).
* **Channel Management:** The platform is highly flexible regarding output channels. You can configure disparate channels (e.g., Magento, Amazon, Print Catalog) with completely different data requirements and locale settings, all sourcing from the same golden record.
* **Open Source Nature:** Because it is open source (GPLv3), there is no "black box." If a specific customization is impossible via configuration, developers have full access to the source code to overwrite classes or inject custom services.

### 4. Scalability

Scalability is the system's ability to handle growing amounts of data and concurrent users. AtroCore is optimized for the high-volume nature of PIM/MDM scenarios.

* **Queue Management:** This is critical for data platforms. Heavy operations (like importing 100,000 products or regenerating 50,000 image thumbnails) are offloaded to a background queue system. This ensures the user interface remains responsive even during heavy data processing.
* **Database Optimization:** The system supports database indexing and optimization strategies tailored for EAV (Entity-Attribute-Value) or standard relational models, allowing it to handle millions of records efficiently.
* **Asset Storage:** For scalability in digital assets (DAM), AtroCore supports offloading file storage to cloud solutions (like AWS S3) rather than relying solely on the local server's file system.
* **Horizontal Scaling:** As a PHP-based application (typically running on an Apache/Nginx and MySQL/MariaDB stack), it can be scaled horizontally by adding load balancers and separating the database server from the application server.

### Summary Table

| Feature | Key Characteristic | Benefit |
| --- | --- | --- |
| **Modularity** | Composer-based module system | Install only what you need; easier maintenance. |
| **Configurability** | UI-based Entity & Layout managers | Rapid prototyping; reduces dependency on developers. |
| **Flexibility** | 100% REST API coverage | Easy integration with ERPs, eCommerce, and mobile apps. |
| **Scalability** | Background Job Queues | Handles mass data updates without crashing the UI. |



## License

AtroCore is published under the GNU GPLv3.

## Support

- For support please contact us - visit [our website](https://www.atrocore.com/contact).
