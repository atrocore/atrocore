---
title: Backend development
---

Welcome to the developer documentation for **AtroCore**, a powerful open-source data platform designed for **Master Data Management (MDM)** and **Data Integration**. Highly configurable and feature-rich out of the box, AtroCore enables cost-effective, agile application development with minimal coding. It's ideal for businesses seeking scalable, customizable solutions for managing and integrating enterprise data.

This guide is primarily for developers looking to build new features and solutions on top of AtroCore's modular architecture.

-----

## Core Concepts

**AtroCore** is an **API-first** framework built on the principle of **Convention over Configuration (CoC)**. This approach speeds up development by using sensible defaults for entities, services, and handlers, instead of requiring extensive configuration. The framework's modular architecture simplifies adding or modifying features. It supports both **event-driven architecture** and **dependency injection** for extending and customizing behavior.

The core of the framework is the `\Atro\Core\Application` class, which loads and runs the application. When a request is received, AtroCore automatically detects its type and either serves the client-side view of a single-page application (SPA) or processes it as an API request.

## Core Features

Here's a look at the core concepts that make AtroCore a robust and flexible framework:

#### Service Container

AtroCore features a **Service Container** for managing and injecting dependencies throughout your application.
This allows you to offload the manual creation of complex objects and their dependencies to a central container, ensuring your code remains loosely coupled and easier to test.
It also comes with many **ready-to-use dependencies** already available in the container, providing developers with a flexible and reliable way to manage object lifecycles and application architecture.

To learn more, check out the [Service Container documentation](01.service-container/index.md).

#### Metadata

**Metadata** is a cornerstone of AtroCore, defining everything from entity structures to frontend behavior. Stored in JSON format, metadata is a versatile system that plays a crucial role in both the application and database layers. It allows you to define scopes, entities, client-side behavior, and more. You can extend metadata statically by adding or overriding JSON files, or dynamically through listeners for more complex modifications.

To learn more, check out the [Metadata documentation](02.metadata/index.md).

#### Entities

Entities are the object-oriented representations of your data. AtroCore provides four types of entities to handle various data structures:

* **Base**: For standard data stored in a single database table.
* **Hierarchy**: For data with a parent-child relationship.
* **Relation**: For managing many-to-many relationships between entities.
* **ReferenceData**: For simple, file-based data that doesn't require a database table.

For more details, see the [Entities documentation](05.entities/index.md).


#### Repositories

The **Repository** provides a high-level API for interacting with your entities.
It abstracts away the complexity of database operations, allowing you to easily create, read, update, and delete entities without writing raw SQL queries.
This repository-style approach simplifies data manipulation and promotes cleaner, more maintainable code.

For a deeper dive, refer to the [Repository documentation](06.repositories/index.md).

#### Handlers

**Handlers** are the entry point for API requests. Each handler is a PSR-15 middleware class responsible for a single endpoint. It receives the HTTP request, delegates business logic to a service, and returns a PSR-7 response. Routing, authentication, and request/response validation are handled automatically by the pipeline — the handler focuses solely on its own logic.

For more information, see the [Handlers documentation](12.handlers/index.md).


#### Entity Services

**Entity Services** are where the core business logic of your application resides.
When a controller receives a request, it delegates the complex operations, data manipulation, and business rule enforcement to a service method.
This design pattern ensures that your business logic is centralized, reusable, and easier to test and maintain.

!!! Entity Services should not be mistaken with core feature services available in the container

For a deeper dive, refer to the [Services documentation](15.services/index.md).

#### Select Managers

AtroCore includes a sophisticated Select Manager for building high-level queries and managing complex data retrieval.
It allows you to create specific query structures to filter, sort, and process data from the entity repository, ensuring your application can handle complex data requirements efficiently.

For a deeper dive, refer to the [Select Managers documentation](10.select-manager/index.md).

#### Listeners

**Listeners** are a powerful way to extend and customize AtroCore's functionality. They allow you to hook into various events that occur throughout the application lifecycle, such as when an entity is created, updated, or deleted. By listening for these events, you can execute custom code to add new features, modify existing behavior, or integrate with third-party services.

To learn more, check out the [Listeners documentation](20.listeners).

#### Access Control List (ACL)

Security is a top priority in AtroCore, and the **Access Control List (ACL)** is a key component of its security model.
The ACL allows AtroCore to define granular permissions for different user roles, ensuring that users can only access the data and perform the actions that they are authorized to.
This role-based access control system provides a robust and flexible way to secure your application.

To learn more, check out the [ACL documentation](21.access-control-list/index.md).

#### Caching

**Caching** is a crucial aspect of any high-performance web application.
AtroCore provides a flexible caching layer that allows you to store frequently accessed data in files or in memory, reducing the need for expensive database queries and improving response times.
You can configure different caching strategies to suit your application's needs, ensuring optimal performance.

To learn more, check out the [Caching documentation](23.caching/index.md).

#### Layout Management

AtroCore features a flexible layout management system that gives you complete control over how your application's interfaces are structured and displayed.
With JSON-based layout definitions, you can customize list views, detail pages, and relationship displays for each entity type in your application.
The system supports detailed configuration of panels, rows, and fields, allowing you to create intuitive and efficient user interfaces tailored to your specific business needs.

To learn more, check out the [Layout documentation](30.layouts/index.md).

#### Job Management

AtroCore includes a powerful **job management system** for handling long-running or resource-intensive tasks in the background.
This allows you to offload tasks such as sending emails, generating reports, or processing large files to a separate process, ensuring that your application remains responsive and performant.
You can schedule jobs to run at specific times or trigger them based on certain events, providing a flexible and reliable way to manage background tasks.

For a deeper dive, refer to the [Services documentation](33.job-management/index.md).

#### System Configurations
AtroCore utilizes a set of configurable parameters that serve as simple key-value pairs to adjust the application's behavior.
This allows you to easily change settings for features such as database connections.

Check [Config parameters](35.config/index.md) to have list of all available parameters.
