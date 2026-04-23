---
title: Config Parameters
---

AtroCore configuration parameters are located in the `data/config.php` file. You can access and modify these values in the
code by retrieving the `config` utility from the service container.


## Accessing a Value

To retrieve a configuration value, use the `get()` method with the parameter's key. You can access nested values using dot notation (e.g., `'database.host'`).

**Example:**

```php
/** @var \Atro\Core\Utils\Config $config */
$config = $container->get('config');

// Get a top-level key's value
$passwordSalt = $config->get('passwordSalt');

// Get a nested key's value
$dbHost = $config->get('database.host');
```

## Store or modify a value

To change a configuration value, use the `set()` method. **Changes are not applied until you call `save()`**. This method persists the changes to the configuration file.

**Example:**

```php
/** @var \Atro\Core\Utils\Config $config */
$config = $container->get('config');

// Set a new value for a configuration parameter
$config->set('applicationName', 'My Custom App');

// Persist the change to the configuration file
$config->save();
```

## General System Settings

| Parameter         | Type      | Description                                                                                                                              |
|:------------------|:----------|:-----------------------------------------------------------------------------------------------------------------------------------------|
| `isInstalled`     | `boolean` | The installation state of the application. Set to `true` upon completion of the installation wizard. If `false`, the wizard is launched. |
| `passwordSalt`    | `string`  | A unique string used to encrypt passwords.                                                                                               |
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
| `passwordSalt`                 | `string`  | A unique string used to encrypt passwords.                                                                                                                       |
| `authTokenLifetime`            | `integer` | Defines the total lifetime of an authentication token in seconds. A value of `0` means the token never expires.                                                  |
| `authTokenMaxIdleTime`         | `integer` | Defines the maximum duration in seconds that an authentication token can be inactive before it expires. A value of `0` means it never expires due to inactivity. |
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
