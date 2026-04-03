---
title: Required Skills
taxonomy:
    category: docs
--- 

This chapter addresses the required skills for implementing the AtroCore software independently. If you would like to outsource the software implementation (or its parts) to AtroCore Company or our Implementation Partners, please contact us.

AtroCore is a low-code platform. This means that, for most implementation projects, in-depth programming knowledge is not necessary. However, the necessary skill set varies depending on the level of customisation required.

We categorize the required skills into three levels: **General Implementation**, **Advanced Configuration**, and **Custom Development**.

### 1. General Implementation Skills (No-Code)

For standard implementation, setup, and maintenance, no programming skills are required. The system is configured entirely via the user interface using forms, drag-and-drop builders, and setting toggles.

However, a strong **technical understanding of data structures** is essential to properly map business requirements to the software.

**Key Requirements:**

* **Relational Data Modeling:** You must understand the logic behind databases (Entities) and how they connect.
* Understanding relationships: *One-to-Many*, *Many-to-Many*, and *One-to-One*.
* Understanding data types: Enum, Varchar, Boolean, Integer, Float, etc.


* **Business Logic Mapping:** The ability to translate real-world business processes into system workflows, entity structures, and layouts.
* **UI Configuration:** Proficiency in using web-based administration panels to:
* Configure layouts (Detail views, List views) via drag-and-drop.
* Set up User Roles, Teams, and Permissions (ACL).
* Manage Dashboards and Navigation menus.
* Create and manage attributes and field sets.

This level of skill should be sufficient for smaller or "typical" projects.

### 2. Advanced Configuration Skills (Low-Code)

For complex implementations that require dynamic behavior, automated document generation, or complex data transformation, some technical scripting knowledge is beneficial.

**Key Requirements:**

* **Twig Templating Engine:**
* Knowledge of **Twig** syntax is required to write scripts, customize email templates, and generate PDF documents.
* You should understand how to use variables, loops (`for`), and conditions (`if/else`) within a template.


* **Basic Formula/Script Logic:**
* The ability to write small logical expressions for calculated fields or workflow conditions (e.g., `IF price > 100 THEN ...`).


* **JSON Structure:**
* While often handled by the UI, being able to read and edit JSON is helpful for troubleshooting export/import configurations or adjusting advanced settings.

This level of skill is recommended if you want to implement a lot of custom logic, automation or data transformation in your project. This skill level enables you to implement about 95% of project requirements, even for large companies or enterprises.

### 3. Development Skills (Pro-Code)

If you intend to create **custom modules**, develop entirely new features, or modify the core behavior of the system beyond what is possible in the configuration panel, full-stack development skills are required.

**Key Requirements:**

* **Backend (PHP 8.4+):**
* Proficiency in Object-Oriented Programming (OOP) and Dependency Injection.
* Experience with **Composer** for package management.

* **Frontend (JavaScript):**
* **Backbone.js:** The core UI uses Backbone views and models.
* **Svelte:** Newer components and widgets are built using Svelte.
* Asynchronous programming (API calls, Promises).


---

### Skill Level Summary

| Category | Primary Focus | Required Knowledge |
| --- | --- | --- |
| **General** | Data Modeling & Admin | Relational Database Logic, Drag-and-Drop UI, ACL/Permissions |
| **Advanced** | Logic & Templating | **Twig Syntax**, Logical Expressions, Basic JSON |
| **Developer** | Custom Modules | PHP 8, Backbone.js, Svelte, Composer, SQL |

