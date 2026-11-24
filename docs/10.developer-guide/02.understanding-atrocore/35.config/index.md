---
title: Config Parameters
---

AtroCore configuration parameters are located in two files:

- `data/config.php` - the parameters the system manages itself. The administration panel and the `config` utility write them.
- `data/config.system.php` - the system parameters, maintained by hand. Neither the administration panel nor the `config` utility writes this file.

You can access the values of both files in the code by retrieving the `config` utility from the service container.

## System Parameters

`data/config.system.php` holds the parameters that neither the administration panel nor the code may change: the
database connection, the secret keys and everything the administrator decides to fix. The file is a plain PHP array,
just like `data/config.php`:

```php
<?php
return [
    'isInstalled'        => true,
    'database'           => [
        'driver'   => 'pdo_pgsql',
        'host'     => 'localhost',
        'port'     => '',
        'charset'  => 'utf8',
        'dbname'   => 'atrocore',
        'user'     => 'atrocore',
        'password' => 'secret',
    ],
    'logger'             => [
        'path'          => 'data/logs/atro.log',
        'level'         => 'WARNING',
        'rotation'      => true,
        'maxFileNumber' => 7,
    ],
    'amountOfDbDumps'    => 14,
    'filesPath'          => 'upload/files/',
    'thumbnailsPath'     => 'upload/thumbnails/',
    'encryptionKey'      => '...',
    'passwordPepper'     => '...',
    'oauthEncryptionKey' => '...',
];
```

The rules are simple:

- **A parameter of `data/config.system.php` overrides the same parameter of `data/config.php`.** Associative arrays
  are merged key by key, so `data/config.system.php` may define only `database.password` and leave the rest of the
  connection in `data/config.php`. Lists and scalar values are replaced as a whole.
- **Every top-level key of `data/config.system.php` is a system parameter.** `set()` and `remove()` throw a
  `LogicException` for it, the Settings form shows its fields read-only, and `PATCH /api/settings` rejects a change of
  it. If the file defines `database.password`, the whole `database` key is a system parameter.
- **Any parameter becomes a system one the moment you move it to `data/config.system.php`** - `siteUrl`,
  `recordsPerPage`, anything. Move it back to `data/config.php` to make it editable again.
- **The file is optional.** A missing file defines no system parameters.

A fresh installation puts `isInstalled`, `database`, `logger`, `amountOfDbDumps`, `filesPath`, `thumbnailsPath`,
`encryptionKey`, `passwordPepper` and `oauthEncryptionKey` into `data/config.system.php`. The upgrade to AtroCore 3.0
moves the same parameters there from `data/config.php`, unless `data/config.system.php` already exists.

When you move a parameter by hand, remove it from `data/config.php`, otherwise its stale value stays on the disk. Keep
`encryptionKey`, `passwordPepper` and `oauthEncryptionKey` unchanged when moving them: the stored credentials, the
passwords of the users and the issued tokens depend on them.

`Config::isSystemKey()` tells whether a parameter is defined in `data/config.system.php`:

```php
if ($config->isSystemKey('siteUrl')) {
    // the value cannot be changed by the system
}
```

### Environment Variables

Neither the administration panel nor the `config` utility rewrites `data/config.system.php`, so any PHP expression there stays as it is. Supply a value from
the environment with `getenv()`:

```php
<?php
return [
    'database'        => [
        'driver'   => 'pdo_pgsql',
        'host'     => getenv('ATRO_DB_HOST'),
        'port'     => '',
        'charset'  => 'utf8',
        'dbname'   => getenv('ATRO_DB_NAME'),
        'user'     => getenv('ATRO_DB_USER'),
        'password' => getenv('ATRO_DB_PASSWORD'),
    ],
    'amountOfDbDumps' => (int)(getenv('ATRO_AMOUNT_OF_DB_DUMPS') ?: 14),
];
```

- `getenv()` returns `false` for a missing variable, so use `?:` for a default value, not `??`.
- `getenv()` always returns a string: cast numbers with `(int)` and booleans with
  `filter_var(..., FILTER_VALIDATE_BOOL)`.
- Supply the variables to every process: the web server, the console commands and the workers. Restart the workers
  after changing the variables or the file.

To install the system with such a file, create `data/config.system.php` before running the installer. The installer
then takes the database connection from the file instead of asking for it and never writes the file. The parameters
the file does not define - the secret keys, `isInstalled` and the others - go to `data/config.php`. A parameter that the
installer has to set but the file defines, e.g. `isInstalled`, is reported as a warning: set it in the file yourself.

Never use `getenv()` in `data/config.php`: the system rewrites that file on every save and replaces the expression with
its current value.

### Backups

Include `data/config.system.php` in every backup together with `data/config.php`: without its secret keys no user can
log in.


## Accessing a Value

To retrieve a configuration value, use the `get()` method with the parameter's key. You can access nested values using dot notation (e.g., `'database.host'`).

**Example:**

```php
/** @var \Atro\Core\Utils\Config $config */
$config = $container->get('config');

// Get a top-level key's value
$siteUrl = $config->get('siteUrl');

// Get a nested key's value
$dbHost = $config->get('database.host');
```

## Store or modify a value

To change a configuration value, use the `set()` method. **Changes are not applied until you call `save()`**. This method persists the changes to `data/config.php`.

**Example:**

```php
/** @var \Atro\Core\Utils\Config $config */
$config = $container->get('config');

// Set a new value for a configuration parameter
$config->set('applicationName', 'My Custom App');

// Persist the change to the configuration file
$config->save();
```

System, computed and contributed parameters cannot be written this way - see [System Parameters](#system-parameters) and [Read-Only Parameters](#read-only-parameters).

## Read-Only Parameters

Some parameters are not stored in `data/config.php` at all - the system derives them on every load.
Writing them is a programming error, so `set()` and `remove()` throw a `LogicException` for such a key:

- `locales`, `mainLanguage`, `inputLanguageList`, `isMultilangActive` - derived from the Language and Locale records
- `onlyStableReleases` - derived from `minimum-stability` in `composer.json`
- `referenceData` and everything else contributed by a module (see below)

-----

## Contributing Your Own Data

A module adds its own entries to the configuration through the static `getConfigAdditionalData()`
method of its `Module` class. The method returns an associative array of `key => value`. A key may be
segmented, so `'referenceData.Car'` lands in `$config['referenceData']['Car']`:

```php
namespace MyModule;

use Atro\Core\ModuleManager\AbstractModule;
use MyModule\Repositories\Car;

class Module extends AbstractModule
{
    public static function getConfigAdditionalData(): array
    {
        return [
            'referenceData.Car' => Car::getConfigData(),
        ];
    }
}
```

Keep the data-fetching itself in the repository that owns the records, so the configuration never has
to know where or how they are stored:

```php
namespace MyModule\Repositories;

use Atro\Core\Templates\Repositories\ReferenceData;

class Car extends ReferenceData
{
    public static function getConfigData(): array
    {
        $path = self::DIR_PATH . '/Car.json';

        if (!file_exists($path)) {
            return [];
        }

        $items = @json_decode(file_get_contents($path), true);

        if (!is_array($items)) {
            return [];
        }

        $res = [];
        foreach ($items as $key => $row) {
            $res[$key] = [
                'id'   => $row['id'] ?? null,
                'code' => $row['code'] ?? null,
                'name' => $row['name'] ?? null,
            ];
        }

        return $res;
    }
}
```

The method is static because the configuration is built before the service container can provide
module instances. It is called once per process; `DataManager::clearCache()` drops the cached result.

### Never Contribute Sensitive Data

Everything returned by `getConfigAdditionalData()` is exposed to the outside world. It reaches:

- the browser, where it is read as `this.getConfig().get('referenceData')`
- Twig templates that users edit through the UI, as `config.referenceData`
- PDF and export rendering contexts

Restrict every entry to the fields the UI actually needs. API tokens, passwords, connection details
and any other secret must never be contributed this way.

### A Key Can Have Only One Owner

A module may only **add** entries. If a key is already provided by the core, by another module, or is
already present in `data/config.php`, loading the configuration fails with a `LogicException` instead
of silently overwriting the value. Prefix your keys with something specific to the module to avoid
collisions.

Every key a module contributes automatically becomes read-only.

-----

## General System Settings

| Parameter         | Type      | Description                                                                                                                              |
|:------------------|:----------|:-----------------------------------------------------------------------------------------------------------------------------------------|
| `isInstalled`     | `boolean` | The installation state of the application. Set to `true` by the installer as its very last step. If `false`, the system is not installed and the web interface shows a page that says so. |
| `encryptionKey`   | `string`  | A unique secret key used to encrypt stored credentials, such as connection passwords.                                                    |
| `amountOfDbDumps` | `integer` | The maximum number of database backups the system will store. Older backups are automatically deleted when this limit is reached.        |
| `useCache`        | `boolean` | Activates or deactivates the backend and frontend caching mechanisms.                                                                    |
| `locale`          | `string`  | The ID of the system's default language and locale.                                                                                      |
| `applicationName` | `string`  | The name of the application. It is recommended to keep this name short to ensure page titles are fully visible in browser tabs.          |

-----

## Database Connection

| Parameter           | Type      | Description                                                                                       |
|:--------------------|:----------|:--------------------------------------------------------------------------------------------------|
| `database.driver`   | `string`  | The database driver to use (e.g., `pdo_mysql`, `pdo_psql`).                                       |
| `database.host`     | `string`  | The database server hostname.                                                                     |
| `database.port`     | `integer` | The database server port.                                                                         |
| `database.charset`  | `string`  | The character set for the database connection (e.g., `utf8mb4` for MySQL, `utf8` for PostgreSQL). |
| `database.dbname`   | `string`  | The name of the database.                                                                         |
| `database.user`     | `string`  | The database user.                                                                                |
| `database.password` | `string`  | The database user's password.                                                                     |

## Memcache Connection

| Parameter           | Type      | Description               |
|:--------------------|:----------|:--------------------------|
| `memcached.host`     | `string`  | Memcache server hostname. |
| `memcached.port`     | `integer` | Memcache server port.     |

-----

## Logging

| Parameter              | Type      | Description                                                                                                       |
|:-----------------------|:----------|:------------------------------------------------------------------------------------------------------------------|
| `logger.path`          | `string`  | The directory path where log files will be saved.                                                                 |
| `logger.level`         | `string`  | The default logging level (e.g., `DEBUG`, `INFO`, `WARNING`, `ERROR`, `CRITICAL`, `ALERT`, `EMERGENCY`).          |
| `logger.rotated`       | `boolean` | If set to `true`, logs are rotated daily, with a limited number of files kept to prevent excessive storage usage. |
| `logger.maxFileNumber` | `integer` | The maximum number of rotated log files to retain. When the limit is reached, the oldest file is removed.         |

-----

## User and Security

| Parameter                      | Type      | Description                                                                                                                                                      |
|:-------------------------------|:----------|:-----------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `passwordPepper`               | `string`  | A unique secret key mixed into every user password hash. Without it, no user can log in, so keep it in every backup of the configuration file.                   |
| `sessionLifetime`              | `float`   | The lifetime of a session of the user interface in hours, since signing in. A value of `0` means no limit.                                                       |
| `userNameRegularExpression`    | `string`  | A regular expression used to validate usernames during new user creation in the administration panel.                                                            |
| `aclStrictMode`                | `boolean` | If `true`, access to a scope is forbidden unless explicitly granted in a role. If `false`, access is allowed unless explicitly denied.                           |
| `noteDeleteThresholdPeriod`    | `string`  | A time-based duration (e.g., `-1 day`) after which an entity can no longer be deleted by its owner. This prevents short-term deletion of content.                |
| `noteEditThresholdPeriod`      | `string`  | A time-based duration (e.g., `-1 hour`) after which an entity can no longer be modified by its owner. This prevents retroactive editing of content.              |
| `checkForConflicts`            | `boolean` | If `true`, the system checks for conflicts when multiple users attempt to update the same entity, preventing data loss.                                          |
| `assignmentEmailNotifications` | `boolean` | If `true` and email settings are configured, users will receive email notifications when they are assigned to an entity.                                         |

-----

## UI & Display

| Parameter                         | Type      | Description                                                                                                                                                          |
|:----------------------------------|:----------|:---------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `displayListViewRecordCount`      | `boolean` | If `true`, the total number of records is displayed in list views.                                                                                                   |
| `disabledCountQueryEntityList`    | `array`   | An array of entity names for which the system will skip calculating the total item count when fetching list views. This improves performance for large entity lists. |
| `textFilterUseContainsForVarchar` | `boolean` | If `true`, the `contains` operator is used for text filtering on `varchar` fields. Otherwise, the `starts with` operator is used.                                    |
| `recordsPerPage`                  | `integer` | The number of records initially displayed in standard list views.                                                                                                    |
| `recordPerPageSmall`              | `integer` | The number of records initially displayed in relationship panels.                                                                                                    |
| `lastViewedCount`                 | `integer` | The maximum number of records to display in the "Last Viewed" panel.                                                                                                 |
| `globalSearchEntityList`          | `array`   | A list of entities to be included in the global search functionality.                                                                                                |

-----

## File Management

| Parameter               | Type      | Description                                                                                                       |
|:------------------------|:----------|:------------------------------------------------------------------------------------------------------------------|
| `filesPath`             | `string`  | The base file path where all user-uploaded files are stored.                                                      |
| `thumbnailsPath`        | `string`  | The base file path where generated image thumbnails are stored.                                                   |
| `chunkFileSize`         | `integer` | The maximum size of each data chunk in bytes that is uploaded to the server during a file upload.                 |
| `fileUploadStreamCount` | `integer` | The maximum number of concurrent streams (or chunks) that can be uploaded simultaneously, improving upload speed. |
| `fetchAllowedHosts`     | `array`   | Hosts the server may fetch from even when they resolve to a private address. Empty by default - see below.        |

### Fetching Files From URLs

Wherever the system downloads a file from a URL the user supplied - the `Upload via URL` field, the
`url` property of `POST /api/File`, `POST /api/File/uploadProxy` - the request is made by the server,
not by the browser. The server therefore reaches whatever it can reach, including services that are
not exposed to the outside world at all.

For that reason only `http` and `https` URLs pointing at publicly routable addresses are accepted.
Loopback, link-local, private and reserved ranges are rejected, redirects are not followed, and the
address the connection actually reached is checked again once the transfer starts.

An installation that legitimately pulls files from its own network - an in-house DAM, a staging
server - names those hosts in `fetchAllowedHosts`, and only those skip the address check:

```php
'fetchAllowedHosts' => [
    'dam.internal.example.com',
    'files.staging.example.com',
],
```

List the hosts you actually fetch from. The parameter is empty by default, so reaching a private
address stays a decision someone made on purpose.

-----

## Background Jobs & Workers

| Parameter                       | Type      | Description                                                                                                                                                                             |
|:--------------------------------|:----------|:----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `maxConcurrentWorkers`          | `integer` | The maximum number of concurrent workers allowed for background job execution.                                                                                                          |
| `maxTransactionJobsPerProcess`  | `integer` | The maximum number of jobs to be processed within a single worker process, preventing excessive memory usage.                                                                           |
| `massUpdateMaxCountWithoutJob`  | `integer` | The threshold for records in a mass update. If the number of records is at or below this value, the update is executed immediately in the current request rather than a background job. |
| `massUpdateMaxChunkSize`        | `integer` | The maximum number of records processed in a single background job chunk during a mass update.                                                                                          |
| `massUpdateMinChunkSize`        | `integer` | The minimum number of records processed in a single background job chunk during a mass update.                                                                                          |
| `massDeleteMaxCountWithoutJob`  | `integer` | The threshold for records in a mass delete. If the number is at or below this value, the deletion is executed immediately.                                                              |
| `massDeleteMaxChunkSize`        | `integer` | The maximum number of records processed in a single job chunk during a mass delete.                                                                                                     |
| `massDeleteMinChunkSize`        | `integer` | The minimum number of records processed in a single job chunk during a mass delete.                                                                                                     |
| `massRestoreMaxCountWithoutJob` | `integer` | The threshold for records in a mass restore. If the number is at or below this value, the restore is executed immediately.                                                              |
| `massRestoreMaxChunkSize`       | `integer` | The maximum number of records processed in a single job chunk during a mass restore.                                                                                                    |
| `massRestoreMinChunkSize`       | `integer` | The minimum number of records processed in a single job chunk during a mass restore.                                                                                                    |
| `maxMassLinkCount`              | `integer` | The threshold for linking related records. If the number of records to link is at or below this value, the operation is executed synchronously. Otherwise, it is delegated to a background job. Default: `20`. |
| `maxMassUnlinkCount`            | `integer` | The threshold for unlinking related records. If the number of records to unlink is at or below this value, the operation is executed synchronously. Otherwise, it is delegated to a background job. Default: `20`. |
