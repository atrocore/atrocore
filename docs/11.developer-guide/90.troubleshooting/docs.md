---
title: Troubleshooting
taxonomy:
    category: docs
---

## Overview

This page is intended to help developers debug and fix issues that may occasionally occur when working with the Atrocore system. While such cases are rare, the system's high level of flexibility makes it difficult to predict and test absolutely every possible scenario.

Here, we aim to document the most common issues along with clear instructions on how to identify and resolve them effectively.

## Understanding Where the Error Comes From

The first thing to understand is that Atrocore is an **API-first** system. This means that everything you see in the browser is rendered by a frontend application that communicates with the backend exclusively via a REST API.

If you see an error on the screen, it could originate from either the **frontend** or the **backend**.

To determine where the issue lies, you should open your browser's Developer Tools (usually accessible via the `F12` key in most modern browsers) and navigate to the **Console** tab.

- If you see **red text** in the console, that typically indicates a frontend error.
- Sometimes, these errors may be caused by installed browser extensions, not by Atrocore itself.

To verify whether the issue is related to Atrocore or your browser environment:
1. Try opening the same page in a different browser.
2. If the issue occurs in both browsers, it is most likely a real Atrocore error.

## What to Do When You Find an Error

If you have identified an issue and confirmed that it is not caused by your browser or extensions, the next step is to report it.

### If You Have a Premium Support Agreement

If your company has a **Premium Support Agreement** with us, please use any of the communication channels listed in that agreement to contact our support team directly. We treat such cases with **highest priority** and will respond as quickly as possible.

### If You Do Not Have a Premium Support Agreement

If you do not have a Premium Support Agreement, we ask you to create a **topic** in our [community forum](https://community.atrocore.com/). Please describe your issue clearly and include any relevant error messages or screenshots.

Our team monitors the forum and will respond to your topic as soon as possible, depending on availability.

---

## Backend Errors and HTTP Response Codes

As previously mentioned, an issue can occur not only in the frontend, but also on the **backend**, especially when the frontend calls the API and the API responds with an error.

In such cases, pay close attention to the **HTTP status code** returned in the response. Atrocore follows standard HTTP conventions, so the status code will often indicate the nature of the problem:

- **4xx errors** (e.g., `400`, `422`) usually indicate a **validation error** or an issue with the request itself. This is a normal and expected behavior.
  - Check your request payload or parameters.
  - Carefully read the error message returned by the API — it typically explains what went wrong and how to fix it.

- **5xx errors** (e.g., `500`, `503`) indicate a **server-side error**. This is not expected and usually points to a bug or misconfiguration.

  In case of a 5xx error, you should:
  1. Open the backend logs located in: ```your_atrocore_project/data/logs/```
  2. Look for the file corresponding to the date when the error occurred.
  3. Check the contents of the file for stack traces or error messages that can help you understand the cause.

Once you identify the error, you can either fix it yourself (if applicable) or report it to support using one of the methods described above.

---

## Typical Issues and Solutions

Despite Atrocore's robustness and adaptability, some technical issues may still arise. In this section, we provide a curated list of typical problems encountered during setup, operation, or deployment — along with practical steps to resolve them. This is a growing collection based on observed behavior and known root causes.
Below are the issues currently identified and documented

---

### Issue: Database Inconsistency

One of the most frequent backend-related issues involves database schema mismatches. If log messages indicate that an SQL query failed due to structural inconsistencies, follow these steps:

**How to Diagnose and Fix:**
* Run: ```php console.php sql diff --show``` to detect differences between expected and actual schema.
* If the system outputs SQL commands, run: ```php console.php sql diff --run``` to apply fixes.
* Re-run the show command until you receive confirmation that no further changes are required.
* If corrections cannot be applied automatically, inspect the data manually — for example, remove duplicates preventing a unique index.
  > This is not a normal situation. We perform thorough testing to avoid such issues. During every system update, a **migration mechanism** runs to prepare the database structure for the new version of the system. However, your specific configuration or environment might have prevented the migration from executing as expected.

---

### Issue: Incorrect File Permissions

Another common issue is related to **improper system configuration**, particularly regarding file and directory permissions.

Our installation guide clearly outlines the correct permission setup required for the system to function properly. This is especially critical on Unix-based systems.

If you notice errors in the logs indicating that the system is unable to create or modify a file or directory, this likely means that your project has been configured incorrectly.

Make sure that all necessary files and folders are **owned by the web server user**. On Ubuntu systems, this is typically the `www-data` user. This ownership ensures that the system can read, write, and execute operations on files as needed.

Review your project directory and verify ownership and permissions to prevent such issues.

---

### Issue: Job Manager Not Working (Export Not working, Import Not Working, etc.)

Job Manager is a crucial Atrocore subsystem designed to handle heavy or long-running tasks — such as data import/export, bulk operations, and other resource-intensive jobs where execution time can’t be predicted.

It supports **multi-threaded execution** via worker processes (default: 6, configurable via system settings). For example, when applying bulk changes to many records, the system splits them into chunks and distributes them among available workers, dramatically speeding up processing through parallel execution.

If Job Manager does not seem to function, it usually indicates a misconfigured system environment.

**How to Diagnose and Fix:**
* Check whether the system cron task is correctly configured.
* The following line must exist in your system’s crontab:

  `* * * * * /usr/bin/php /var/www/my-atrocore-project/console.php cron`

!!! The cron job must be configured for the web server user.
On Ubuntu systems, this is typically `www-data`.

To ensure proper execution:
* Use `sudo crontab -u www-data -e` to edit the crontab for that user.
* Add or verify that the above line exists.

Without this, Atrocore cannot execute asynchronous processes, and long-running operations may silently fail or hang.

! If your server has sufficient resources, consider increasing the number of workers in settings to optimize performance even further.

---

### Issue: Broken Labels in Interfaces

In some cases, interface labels may appear broken, missing, or incorrectly rendered — even when the rest of the UI behaves normally. This typically stems from inconsistencies in the translation cache or issues during the translation update process.

**How to Diagnose and Fix:**
* If labels are not displayed correctly, trigger a refresh of the translation cache using the following command:

  `php console.php refresh translations`

This command rebuilds the translation system and restores proper labeling across all modules and interfaces.

> Label issues are rare but may occur after partial updates, custom module installations, or when translation files become unsynchronized with the system cache.

---

### Issue: Broken Cache

Sometimes, unexpected behavior in the system — such as displaying outdated data or missing interface elements — can be caused by a corrupted or stale cache. If you notice something unusual or inconsistent, the cache is one of the first things you should investigate.

**How to Diagnose and Fix:**
- If you’re seeing data that shouldn’t be visible or not seeing data that should be, clear the system cache.

There are two ways to do this:
* **Via UI**: Go to the `Administration` section and click on `Clear cache`.
* **Via CLI**: Run the following command on the server:

  `php console.php clear cache`

Clearing the cache forces the system to rebuild runtime data, configurations, and interface states — often resolving minor display issues or stale data problems.

! Regular cache clearing may be useful during development or after module/config updates.

---

### Issue: Installation Failure (500 Error on ```/api/v1/Installer/getTranslations```)

During the installation process, some users may encounter a 500 Internal Server Error when accessing:
```
/api/v1/Installer/getTranslations
```

This issue is not caused by the application itself but is a result of an incorrect web server configuration.

***Cause:***

The error occurs when the web server is not configured to properly handle the framework’s routing rules. In most cases, this happens on Apache servers where the ```AllowOverride All``` directive is missing from the VirtualHost configuration. Without this setting, the ```.htaccess``` file is ignored, causing routing failures that result in 500 errors.

***Solution:***

Carefully follow the instructions provided in the [Installation Guide](https://help.atrocore.com/installation-and-maintenance/installation), especially the section on configuring your web server. If you are using Apache, ensure that your VirtualHost is set up correctly. A working example is shown below:
```
<VirtualHost *:80>
    ServerName your_domain
    ServerAlias www.your_domain
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/your_domain/public

    <Directory /var/www/your_domain/public/>
        AllowOverride All
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

After applying the correct configuration, restart Apache:
```
sudo systemctl restart apache2
```

> Similar issue and solution on github: [https://github.com/atrocore/atropim/issues/734](https://github.com/atrocore/atropim/issues/734)

---

## What’s Next

The Typical Issues section will be continuously updated as new cases are identified and verified. Stay tuned — we’ll document more problems and their solutions as they emerge.
