---
title: Installation on Shared / Managed Hosting
---

AtroCore Applications can also be installed on a shared or managed hosting (e.g. a managed server without root access). A dedicated (virtual) server remains the recommended setup, but it is not a strict requirement — the installation works fine on a shared/managed hosting, as long as the environment is configured correctly.

This guide describes the requirements your hosting must meet and the differences from the [main installation guide](../../01.installation).

## Requirements

### 1. DocumentRoot must point to the `public` subfolder

The web server must serve the application from the `public` subfolder of your project, not from the project root. As a rule, this can be configured even on shared servers — either in the hosting control panel or by asking the hoster's support to change the DocumentRoot for your account, e.g.:

```
DocumentRoot: /public_html/public
```

### 2. Web server and CLI must run under the same user

The PHP scripts executed by the web server and the console commands executed via cron/SSH must run under the **same system user**, so that both have identical permissions on the project files.

On most shared/managed hostings this is already the case: PHP is executed via FastCGI/suexec under your account user, and cron jobs run under the same user. In this setup the `chown` steps from the main installation guide are not needed — all files already belong to your account user.

### 3. PHP configuration

Make sure the PHP configuration (for **both** the web server and CLI) meets the following requirements:

* A supported PHP version with the required extensions (see the main guide).
* `allow_url_fopen = On`. On usual dedicated servers this is the default, but many shared hostings have it **turned off**. With `allow_url_fopen = Off` the installation and the Module Manager fail, because the application cannot fetch data from the AtroCore store. Enable it in the hoster's PHP configuration panel.
* The `exec()` function must **not** be listed in `disable_functions`. The system uses `exec()` to run console commands (e.g. by the installer and the Module Manager). Some hostings disable it for the web PHP — in this case ask the hoster's support to enable it.
* Resource limits as described in the main guide: `post_max_size`, `upload_max_filesize`, `max_execution_time`, `max_input_time`, `memory_limit`.

> Keep in mind that CLI and web PHP usually have separate configuration files — check both, e.g. via `php -i` on the console and via a temporary script with `phpinfo()` in the browser.

### 4. Cron jobs

The hosting must allow running a cron job **every minute**. Use absolute paths and change into the project directory explicitly:

```
* * * * * cd /path/to/your/project && /usr/bin/php console.php cron
```

Most hostings provide a cron manager in the control panel; alternatively `crontab -e` via SSH can be used, if allowed.

## Explicit PHP binary path (`phpBinPath`)

To execute console commands in the background the system detects the path to the PHP binary automatically, in the following order:

1. The `phpBinPath` parameter from `data/config.php`
2. The `PHP_PATH` environment variable
3. `$_SERVER['_']`
4. `PHP_BINDIR` constant

On a shared/managed hosting the automatic detection may pick a wrong binary or an empty value. If console commands or background jobs are not being executed, set the path explicitly in `data/config.php`:

```php
'phpBinPath' => '/usr/bin/php',
```

## Installation Steps

The installation itself follows the [main installation guide](../../01.installation) with the following differences:

* All commands are executed **without `sudo`**, under your hosting account user (via SSH).
* Skip the `chown` step — the files already belong to your account user.
* Upload the project files into your web directory via git, SSH or SFTP, then run over SSH:
  ```
  php composer.phar self-update && php composer.phar update
  ```
* Set the permissions to directories `755` and files `644`:
  ```
  find . -type d -exec chmod 755 {} + && find . -type f -exec chmod 644 {} +
  ```

## Troubleshooting

* **403 Forbidden** — suexec-based hostings reject group-writable files and directories. Make sure directories have `755` and files `644` permissions (`664`/`775`/`777` result in a 403 error). Also check that an index file exists in the web root.
* **Empty response from the AtroCore store, module list not loading, `file_get_contents()` returns `false`** — `allow_url_fopen` is `Off`. Enable it in the PHP configuration.
* **Console commands or background jobs are not executed** — a wrong PHP binary is detected; set `phpBinPath` explicitly in `data/config.php`. Also make sure cron commands use absolute paths with an explicit `cd` into the project directory.
* **Long-running processes are terminated** — managed hostings may automatically kill long-living or resource-intensive processes (daemons). The every-minute cron job ensures that the jobs are still processed.

## Example: Hetzner Managed Server

A real-world example of a working configuration on a Hetzner Managed Server (konsoleH control panel):

* **SSH access**: port 222, login and password of the main FTP user of the account.
* **DocumentRoot**: changed to the `public` subfolder via a support request.
* **PHP configuration**: *konsoleH → account → Services → PHP Configuration* — this is where `allow_url_fopen` was enabled (it is `Off` by default there). The CLI PHP (`/usr/bin/php`) had no `disable_functions` restrictions.
* **Cron**: *konsoleH → account → Services → Cron Job Manager*. Note that this menu item may be hidden depending on the account type (level) — change the account type or contact the support in this case.
* **PHP binary path**: the automatic detection did not work reliably, so it was set explicitly in `data/config.php`:
  ```php
  'phpBinPath' => '/usr/bin/php',
  ```
