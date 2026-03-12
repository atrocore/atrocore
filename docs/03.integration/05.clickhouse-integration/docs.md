---
title: ClickHouse Integration
taxonomy:
    category: docs
---

The [ClickHouse Integration](https://store.atrocore.com/en/clickhouse-integration/20246) module allows offloading a significant portion of data from the primary database (PostgreSQL, MariaDB, MySQL) into **ClickHouse**. This approach significantly improves the performance of the primary database by reducing its load and prevents it from being cluttered with less critical data.

For example, all tables that store various logs, such as `ActionHistoryRecord` (represents [Archive](../../01.atrocore/03.administration/11.entity-management/01.entity-types/docs.md#archive) type entity `Action History`), can transfer their data to ClickHouse. This process is seamless for the user:

- No data is lost.
- From the perspective of the **AtroCore** UI, everything works as before.
- Sorting and filtering continue to operate normally.

By moving a large volume of data to a separate DBMS, the size of backups for the primary database becomes significantly smaller and easier to manage.

## Benefits

- **Performance Improvement:** The primary database is relieved from handling large volumes of log and historical data, improving query speed and responsiveness.
- **Data Integrity:** All data is preserved, ensuring no loss or inconsistency.
- **Seamless User Experience:** Users interact with the UI in the same way; no changes are visible.
- **Simplified Backups:** Smaller backup sizes for the main database due to offloaded historical/log data.
- **Advanced Analytics:** ClickHouse can be leveraged for reporting and analytics, providing high-speed data aggregation and query capabilities.

## Use Cases

1. **Log Management:** Tables like `ActionHistoryRecord` or other audit/event logs are offloaded to ClickHouse.
2. **Reporting & Analytics:** Build reports and dashboards using ClickHouse without impacting the performance of the primary DB.
3. **Data Archiving:** Store historical data in ClickHouse to reduce load on the primary DB while keeping it accessible.

## How It Works

- Data from selected tables in the main database is periodically synchronized with ClickHouse.
- The synchronization process ensures that new records are added to ClickHouse without affecting the live data in the primary DB.
- The **AtroCore** application continues to operate on the primary database for normal operations, while ClickHouse serves as a high-performance backend for historical and analytical queries.

## Key Features

- Transparent integration with minimal user impact.
- Supports multiple primary DBMSs: PostgreSQL, MariaDB, MySQL.
- Optimized for high-volume data scenarios.
- Enables advanced reporting without compromising main DB performance.
- Reduces backup size and improves backup efficiency.

## Setup and Configuration

Follow these steps to install and configure the ClickHouse Integration module:

1. **Purchase the Module:**
   Acquire the module from [AtroCore Store](https://store.atrocore.com/).

2. **Install ClickHouse:**
   Follow the official installation guide for Debian/Ubuntu: [ClickHouse Installation](https://clickhouse.com/docs/install/debian_ubuntu).
   > **Optional:** Create a dedicated ClickHouse user according to the ClickHouse documentation.

3. **Create a Database:**
   Example:
   ```sql
   CREATE DATABASE atropim;
   ```

4. **Configure the Connection:**
   Edit ```data/config.php``` and add the ClickHouse connection details:
   ```php
   'clickhouse' => [
     'active' => false,
     'partitionType' => 'month', // possible types: week, month, year
     'database' => [
        'host' => 'localhost',
        'port' => 8123,
        'user' => 'DBUSER',
        'password' => 'PASSWORD',
        'dbname' => 'atropim',
      ]
    ],
   ```
   > Note: Keep ```'active' => false``` at this stage, as you first need to configure the database schema.

5. **Rebuild the Schema:**
   Run the command:
   ```bash
   php console.php clickhouse rebuild
   ```

6. **Sync Data:**
   Run the command to transfer data from the primary database to ClickHouse:
   ```bash
   php console.php clickhouse sync
   ```

7. **Automate Syncing:**
   Set up a cron job to run the sync command regularly, for example, every minute:
   ```cron
   * * * * * php /var/www/pim/console.php clickhouse sync
   ```

8. **Activate ClickHouse Integration:**
   Once the schema is configured and data is syncing properly, set ```'active' => true in data/config.php```. AtroCore will now use ClickHouse for the selected data.

After completing these steps, ClickHouse is fully integrated. The system will automatically transfer data moving forward without user intervention.

## Backup Configuration

To ensure data safety and maintain historical copies of your ClickHouse database, we recommend using the provided **bash script** `clickhouse_backup.sh`. This script handles backup creation and automatically removes old backups according to your retention policy.

### Script: clickhouse_backup.sh

```bash
#!/bin/bash

# === Configuration ===
DB_NAME="atropim"
BACKUP_DIR="/var/www/pim/clickhouse-backups"
CLICKHOUSE_USER="default"
CLICKHOUSE_PASSWORD="PASSWORD"
KEEP_BACKUPS=7

# === Functions ===
timestamp() { date '+%Y-%m-%d %H:%M:%S'; }

log() { echo "[$(timestamp)] $*"; }

# === Create backup ===
TIMESTAMP=$(date +%F_%H-%M-%S)
BACKUP_NAME="${DB_NAME}_${TIMESTAMP}"
BACKUP_PATH="${BACKUP_DIR}/${BACKUP_NAME}"

log "Starting backup: ${BACKUP_NAME}"

clickhouse-client \
  --user="${CLICKHOUSE_USER}" \
  --password="${CLICKHOUSE_PASSWORD}" \
  --query="BACKUP DATABASE ${DB_NAME} TO File('${BACKUP_PATH}');"

if [[ $? -ne 0 ]]; then
  log "Backup failed!"
  exit 1
fi

# === Cleanup old backups ===
cd "$BACKUP_DIR" || {
  log "Failed to access backup directory: $BACKUP_DIR"
  exit 1
}

# Count backups
TOTAL_BACKUPS=$(find . -maxdepth 1 -type d -name "${DB_NAME}_*" | wc -l)

if (( TOTAL_BACKUPS > KEEP_BACKUPS )); then
  log "Cleaning up old backups (keeping ${KEEP_BACKUPS})..."
  find . -maxdepth 1 -type d -name "${DB_NAME}_*" \
    | sort -r \
    | tail -n +$((KEEP_BACKUPS + 1)) \
    | while read -r OLD_BACKUP; do
        log "Deleting old backup: $OLD_BACKUP"
        rm -rf "$OLD_BACKUP"
      done
else
  log "No cleanup needed. Total backups: $TOTAL_BACKUPS"
fi

log "Remaining backups:"
ls -1dt ${DB_NAME}_*/ 2>/dev/null || log "No backups found."

```

### Setup Instructions

1. **Make the script executable:**
   ```bash
   sudo chmod +x /usr/local/bin/clickhouse_backup.sh
   ```

2. **Allow ClickHouse to store backups in your project directory:**
   Edit or create ```/etc/clickhouse-server/config.d/allowed_backups.xml``` and add:
   ```xml
   <clickhouse>
      <backups>
         <allowed_path>/var/www/pim/clickhouse-backups/</allowed_path>
      </backups>
   </clickhouse>
   ```

3. **Restart ClickHouse server to apply changes:**
   ```bash
   sudo systemctl restart clickhouse-server
   ```

4. **Create the backup directory and set proper permissions:**
   ```bash
   sudo mkdir -p /var/www/pim/clickhouse-backups
   sudo chown -R clickhouse:clickhouse /var/www/pim/clickhouse-backups
   ```

5. **Schedule automatic backups with cron:**
   For example, to run the backup script once per day at 3:00 AM, add the following line to your crontab (```crontab -e```):
   ```cron
   0 3 * * * /usr/local/bin/clickhouse_backup.sh >> /var/log/clickhouse_backup.log 2>&1
   ```

After completing these steps, your ClickHouse database will have automated backups, safely stored in your designated directory, and old backups will be pruned automatically.
