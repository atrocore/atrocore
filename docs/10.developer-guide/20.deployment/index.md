---
title: Deployment
---

This page explains how deployments are managed in AtroCore and outlines best practices for module handling and system customization.

## Self-Contained Deployment System

AtroCore is a self-sufficient platform that handles its own deployment processes. Manual file transfers or direct server modifications are **not required**.

All deployment-related actions—such as:

- System updates
- Module installation
- Module upgrades
- Module removal

are performed directly by the system via the **Administration → Modules** interface.

Even the core system is treated as a primary module and is visible on the same page.

## Powered by a Customized Composer

AtroCore uses a modified version of [Composer](https://getcomposer.org/) as its deployment engine. While based on the standard Composer tool, it has been extended to support:

- Installation and removal of modules
- Backup creation before updates
- Database migrations
- Version and dependency management
- System consistency checks
- and more else

Composer was chosen for its robust handling of inter-module dependencies and version control.

## Avoid Manual Interference

Manual changes to system files or bypassing the module manager can compromise system integrity. **Do not modify core files directly.**

All customizations must be implemented via **custom modules**.

## Customization Guidelines

To extend or modify AtroCore functionality, create a custom module. Direct code changes outside of modules are not supported and may break future updates.

Learn how to build your own module:
🔗 [Create Your Own Module](https://help.atrocore.com/developer-guide/own-modules)

AtroCore also supports code generation mechanisms such as:

- Custom Actions
- Custom Condition Types
- Other dynamic extension points

Explore the full developer guide:
🔗 [AtroCore Developer Guide](https://help.atrocore.com/developer-guide)

## Built-In Flexibility

AtroCore is highly configurable even without custom code. Most use cases can be addressed by:

- Configuring built-in workflows and metadata
- Installing premium modules for extended functionality

Even the base version offers extensive flexibility to solve business problems without writing additional code.
