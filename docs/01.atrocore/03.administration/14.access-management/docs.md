---
title: Access management
---

Our AtroCore software offers excellent opportunities for efficient collaboration, both internally between employees and with external agencies or freelancers. The company can have its own product managers who prepare technical product descriptions. However, external experts can also be used to create good marketing or SEO texts and professional product images.

For this reason, the effective design of teamwork in an AtroCore system is of great importance both for the quality of the work results and for the speed of their achievement.

An important specialty of AtroCore is the ability to manage access not only to individual entities and their entries, but also at the field level.

## ACL Strict Mode

By default, the `not-set` option is specified for access permissions for each entity, which means that access to this entity has not yet been configured. If `ACL Strict Mode` (in [Settings](../01.system-settings/)) is activated, the user has no access rights by default if access is not configured. If `ACL Strict Mode` is not activated, the user has access to all entities for which their authorizations are not configured. We recommend activating `ACL Strict Mode` immediately after installing the system. For a detailed explanation of how this setting affects role permissions and entity access behavior, see [Roles](03.roles/docs.md#how-roles-interact-with-acl).

## Roles, users and teams at a glance

AtroCore has a very flexible access control system based on roles, teams and users. The authorizations are configured individually for each role. You can set the permissions for both standard and custom entities individually.

An AtroCore user is an account for system access for a specific person. The user is assigned one or more roles that determine their authorizations in the system. A role can be assigned to one or more users.

Each user can belong to one or more teams. Each team can contain several users.

## Access Management Components

- **[Users](01.users/docs.md)** - User accounts and their management
- **[Teams](02.teams/docs.md)** - Team creation and assignment
- **[Roles](03.roles/docs.md)** - Role configuration and permissions
