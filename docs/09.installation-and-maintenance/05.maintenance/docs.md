---
title: Correcting Errors
---

There are certain errors in the system which can be easily corrected by an administrator. Indication of such problems could be "Bad Server Response" errors while you visit the list or detail page for some entity or record.
This errors can be corrected by rebuilding database and/or clearing the system cache.

## Database Rebuild

AtroCore uses its own ORM system. It means that the database structure is first described as metadata with the help of JSON files. This metadata is synchronized than with the real database structure.
Sometimes, some system errors result in differences between the database structure and these metadata. To solve this kind of problems the feature `Rebuild Database` was implemented.

In this case, go to "Administration > Maintenance" and select `Rebuild Database` to actualize your database structure. You will be presented with a list of commands that will be executed on the database before confirming the operation. If this does not resolve the issue, contact support.

> If database rebuild is required, the `Rebuild Database` option will also be shown in the [User menu](../../01.atrocore/05.toolbar/docs.md#user-menu) for admin users.

## Clearing System Cache

Metadata and frontend are usually cached. In very seldom case it may results in errors. To solve these errors go to the Maintenance section in the Administration and click on the Function `Clear System cache`. To be sure you have no problems with the database structure click on `Rebuild Database` in the same section.

> The `Clear System cache` option is also available in the [User menu](../../01.atrocore/05.toolbar/docs.md#user-menu) for admin users.

## System Update Failures

When upgrading the system from an outdated version, Composer may fail with errors related to security vulnerabilities in dependencies.

These errors are usually caused by old dependency versions that are flagged by Composer’s security audit. As a result, Composer may block the upgrade process and refuse to install or update dependencies.

To allow the upgrade process to continue, you can temporarily disable blocking on insecure dependencies in Composer. Update your `composer.json` file inside the root of your AtroCore installation and extend the existing `config` section with `audit.block-insecure = false` as follows:

```
{
    ...

    "config": {
        ...

        "audit": {
            "block-insecure": false
        }
    }
}
```

After modifying the file, run update from the `Administration / Modules` page again.

!!! This configuration is intended only as a temporary workaround for upgrading from old system versions.

Once the system has been successfully upgraded to the latest supported version, you must remove recently added `audit.block-insecure` configuration from `composer.json`.
