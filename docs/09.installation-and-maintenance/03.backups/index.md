---
title: Backups
---

To ensure the security and availability of the atrocore, it is crucial to implement a reliable backup strategy. We recomend to use the *3-2-1 Backup Strategy*

## 3-2-1 Backup Strategy ##
The 3-2-1 backup strategy ensures redundancy and protection against data loss. It consists of:
* 3 copies of the data: One primary and two backups.
* 2 different storage types to reduce the risk of failure.
* 1 offsite copy to protect against disasters.

## Backup Components ##
A complete backup should include:
* Files: All PHP files, configuration files, and other relevant assets.
* Database: A full backup of the PostgreSQL (or other) database.

## Backup Strategy Implementation ##
1. Local Backup (On-Server)
   * Use ```rsync``` or ```tar``` to create a backup of files.
   * Use ```pg_dump``` for PostgreSQL or ```mysqldump``` for MySQL databases.
   * Store backups in /var/backups/ or a dedicated location with restricted access.
   * Automate backups using ```cron```.

2. External Backup (Different Storage)
   * Copy backups to an external disk or network-attached storage (NAS).
   * Use a dedicated backup server within the local network.

3. Offsite Backup (Cloud or Remote Server)
   * Sync backups to a cloud storage provider (e.g., AWS S3, Google Drive, or Backblaze B2).
   * Use a remote server with SSH and rsync for encrypted file transfers.

## Backup Frequency ##
We recommend having a daily backup for a week. Also a monthly backup for a year.