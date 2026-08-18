---
title: Updates and Modules
---

The AtroCore core and all its modules are distributed as software packages. Everything related to them – updating the system, installing and uninstalling modules, choosing the version to update to and reviewing what has been changed – is done on the "Administration > Maintenance > Updates & Modules" page.

The page is available to **administrators only**. It is not a regular entity: records cannot be created, deleted or renamed here, and the only value you can edit is the target version of a package.

## Requirements

Installations, uninstallations and updates are not executed by the web request itself. The web interface only puts the command into a queue, which is then processed by the background daemons started by the system cron job. Therefore:

* the crontab must be [configured correctly](../01.installation/index.md) and the background daemons must be running, otherwise every action on this page is rejected with a corresponding error message;
* no job may be running in the Job Manager at the moment the action is triggered – the system refuses to start an update while other jobs are being processed;
* the PHP functions `exec()` must be allowed and `allow_url_fopen` must be enabled, otherwise the system can neither run the installer nor read the package list from the AtroCore store (see [Shared Hosting](../01.installation/06.shared-hosting/index.md)).

## The Package List

The page shows one table with all software packages that are relevant for your instance:

* the **AtroCore core** itself, always in the first row;
* all **installed modules**;
* all **modules available for installation** – that is, modules that are free or for which your instance has an active licence. Modules with an expired licence are not offered for installation any more.

The information about the available packages is fetched from the AtroCore store and cached for one hour.

An expired licence never removes an already installed module: it stays installed and keeps working for as long as you do not uninstall it yourself. What you lose is the right to newer releases – no version published after the end of your licence period is offered as a target version any more.

The two paid licence models differ in what happens to a module that is *not* installed:

* a **rented** module disappears from the table as soon as the rental period is over. From that moment on there is no way to install it at all – to get it back you have to prolong the rental;
* a **purchased** module stays in the table forever, but only the versions that are covered by your purchase can be installed. Whether such an installation is still possible depends on the version of your core: every module version declares which core version it works with, and the system never downgrades itself just to make an older module fit. So if your instance has meanwhile been updated beyond that point, the module can no longer be added, even though it remains your property.

> Keep in mind that a module frozen this way can hold back the whole installation. Every package declares which core and module versions it is compatible with, so the newest version you are still entitled to may prevent the core and the other modules from being updated further. If the system stops offering new versions, check whether one of your licences has expired.

The following columns are displayed:

* **Name** and **Description** – the name and the short description of the package.
* **Target Version** – the version the package will be updated to during the next system update or during the update of this single package; see [Target Version](#target-version) below.
* **Current Version** – the version that is currently installed. A dash (&mdash;) means the package is not installed.
* **Latest Version** – the newest version offered by the store for your instance.
* **Usage** – how the package is licensed for your instance:
  * **Free** – the package is part of the open-source platform and is available to everyone;
  * **Purchased** – the module is your property. It never disappears from the table, but only the versions that are covered by your purchase can be installed, and only as long as they are still compatible with your core;
  * **Rented** – the module is at your disposal only while the rental period lasts. It does not become your property, so once the rental ends it is no longer offered for installation.
* **Expiration Date** – the date until which the package is still offered for installation. For rented modules this is the end of the rental period; purchased modules show a date far in the future, because they remain yours. The infinity sign (&infin;) means there is no limit at all, which is the case for the free packages.

Above the table the status of the last system update is displayed – **Success** (green) or **Failed** (red). Nothing is displayed if the system has never been updated.

## Target Version

The `Target Version` column defines to which version each installed package will be updated. It is a drop-down list containing:

* **latest** – the highest version that is compatible with the rest of your packages. This is the default;
* an **explicit version number** – the update will stop exactly at this version.

Only versions that are equal to or newer than the currently installed one are offered: downgrading to a previous version through the interface is not possible.

The target version can only be changed for installed packages; for packages that are not installed yet the column is read-only, because their version is determined at installation time.

Choosing the target version and starting the update are not necessarily two separate steps. The edit dialog of a package offers a **Save & Update** button next to **Save**: it stores the new target version and immediately starts the update of this package. See [Module Update](#module-update).

> Whether release candidates are offered as target versions depends on the "Use Only Stable Releases" option on the [Administration > Settings](../../01.atrocore/03.administration/01.system-settings/index.md) page. As long as it is enabled, only stable releases are taken into account.

## System Update

To bring the system and all installed modules to their target versions, click the **Update System** button and confirm the action.

Please note:

* all users are logged out of the system as soon as the update is applied;
* while the update is running, the application is unavailable and every visitor sees a page with the live log of the running update;
* before the changes are applied, a backup of the system and of the database is created, so the previous state can be restored if something goes wrong. If the update fails, the database is restored automatically;
* the backup does **not** include your assets, i.e. the files stored in the system.

The update can also be performed unattended: create a scheduled job of the type "Update system automatically" on the [Scheduled Jobs](../../01.atrocore/03.administration/05.system-jobs/01.scheduled-jobs/index.md) page.

If the update fails because of an audit error in Composer, refer to [System Update Failures](../05.maintenance/index.md#system-update-failures).

## Module Update

An installed module can be brought to its target version on its own, without updating the core and the other modules. Click the **Update** action in the row of the package and confirm it.

The action is offered for installed modules whose target version is set. It is not offered for the AtroCore core: the core is updated together with the whole installation via **Update System**.

To change the target version and start the update in one step, open the package for editing, choose the new target version and click **Save & Update** instead of **Save**.

As with every other operation on this page, the application is unavailable while the update is running, and the result is recorded in the [update log](#update-log).

> Only the module itself and the packages it depends on are updated. The core and the other packages that the system requires explicitly keep their versions. Therefore the module is updated only as far as those versions allow: if its new version needs a newer core, the operation fails and the log states which core version was expected and which one was found. Use **Update System** in that case.

## Module Installation

Modules can be installed without updating the rest of the system: only the selected packages and the packages they depend on are touched.

There are two ways to install a module:

* click the **Install** action in the row of the desired package. This installs a single module;
* open the drop-down menu next to the "Update System" button and choose **Install Module(s)**. A dialog with all packages that are not installed yet opens, where several modules can be selected at once.

In both cases the action must be confirmed – the system is unavailable while the installation is in progress.

## Module Uninstallation

As with the installation, a single module can be removed via the **Uninstall** action in its row, or several modules at once via **Uninstall Module(s)** in the drop-down menu next to the "Update System" button.

Only modules that were installed explicitly can be uninstalled. The AtroCore core and modules that are present only as a dependency of another module are not offered for uninstallation – remove the module that requires them instead.

> Uninstalling a module deletes all data belonging to it. The action cannot be undone.

## Release Notes and Module Documentation

Two more actions are available in the row of each package:

* **Release Notes** – opens a dialog with the release notes of the package, loaded from the AtroCore help center.
* **Docs** – opens the built-in documentation portal on the section of this module in a new tab. The action is shown only for installed packages that ship their own documentation.

## Update Log

The history of all installations, uninstallations and updates is displayed in the panel on the right side of the page. Each entry states who triggered the operation and when, and whether it finished successfully or failed.

Click **View Details** in an entry to see the complete raw output of the operation. This output is the first thing to look at when an update did not succeed.

## Restore a System

If an update cannot be completed and the system does not recover by itself, restore it manually. Run in the root directory of your installation:

```
php atrocore-installer.phar restore
```

You can force the restoration if the previous command does not help:

```
php atrocore-installer.phar restore --force
```

Run the restore command as the webserver user, e.g. `www-data`; otherwise do not forget to change the ownership of the restored files afterwards.

## Consideration of the Dependencies

During the update the system installs the newest package versions that are compatible with each other. It is therefore possible that some modules are not updated to their latest available version: their newest release may require a core or another module version that conflicts with the rest of your installation. In this case the highest version that keeps the whole installation consistent is chosen.

The update of a [single module](#module-update) resolves the versions within a narrower scope: the core and the other explicitly required packages keep their versions, and only the module and its own dependencies are allowed to move. A module whose new version demands a newer core is thus updated by **Update System** only.

## Module Purchase

The complete list of AtroCore modules is available in our store: [English version](https://store.atrocore.com/en/), [German version](https://store.atrocore.com/de/). The store can be opened directly from the page with the **AtroCore Store** button.

Free modules can be installed right away. A module that has to be purchased or rented appears in the table only after it has been activated for your instance. After the purchase, contact our support and provide your system IDs, which can be found on the "Administration > Settings" page – we need them to activate the module for your software instance. You can provide the IDs of your production, stage and testing environments.

After a successful activation the module shows up in the table within a few minutes, and its licence period is shown in the `Expiration Date` column.

### Purchase Conditions

The price stated on the website does not include VAT. For the price stated you will get the module including updates and upgrades for one year. After that, you may still use your last version of the module, or purchase the module again with a 50% discount, which gives you a right to updates and upgrades for an additional year. Furthermore, our [EULA](https://atropim.com/eula) (End-User License Agreement) will apply.

### Rental Conditions

A rented module is licensed for the duration of the rental period and includes all updates and upgrades released within it. In contrast to a purchase, the module does not become your property: once the rental period is over and is not prolonged, the module is no longer offered for installation and an already installed instance of it does not receive new versions any more.
