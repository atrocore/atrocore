---
title: Scheduled Jobs
taxonomy:
    category: docs
---

Scheduled Jobs are configurations that automatically create [Jobs](../) at specified intervals. They allow you to orchestrate multiple actions, such as import and export feeds, job retrials, system updates, etc. to be executed automatically by the Job Manager subsystem.

> Some job types are available only with additional modules purchase.

## Main functions

![Main menu](_assets/scheduled-jobs-main-menu.png){.large}

Every job has the following fields:

- **Name**: The name of the job
- **Type**: The type of the Scheduled Job (e.g., "Check system updates", "Import Feed", etc.). Cannot be changed after creation.
- **Scheduling**: Defines frequency of job runs using cron syntax. Press `i` to get the syntax. For example, `0 2 * * *` runs the job daily at 2:00 AM. The time used is system time.
- **Active**: Checkbox to enable or disable the job execution

You can also run the job immediately, regardless of the schedule, by pressing the `Execute Now` button. This creates a job scheduled for current time.

The jobs execution log displays all Jobs that have been created from this scheduled job configuration, showing their execution status, timing, and results.

## Scheduled Jobs types

Scheduled Jobs can provide different job types to execute:

### Check system updates

This job checks for available system updates on the schedule you set. It scans for available updates without actually installing them.

### Update system automatically

This job starts system update on the schedule you set. The settings set by update are taken from the `Modules` menu, so it is essentially pressing the `Update` button on the set time.

### Scan Storage
<!-- TODO: it's not actually true, but what is it doing for basic storage? -->
Available with [S3 Object Storage](../../../../03.integration/02.s3-object-storage/) module.

### Calculate Script Fields

Available with [Advanced Data Management](../../../../01.atrocore/03.administration/11.entity-management/06.advanced-data-management/) module.

### Import Feed

This job starts the selected import feed on the schedule you set. The settings set by the feed are taken from the feed menu, so it is essentially pressing the `Import` button on the set time.

<!-- TODO: what 'Maximum hours to look back' param stands for? -->
![Import feed Scheduled](_assets/scheduled-jobs-import-feed.png){.medium}

### Automatic PDF generation

Available with [PDF Generation](../../../../07.publishing/01.pdf-generator/) module.

This job starts generating PDFs as assets that are appropriate for selected PDF Feed.

> Note that this job may take a lot of time and server resources, so please schedule its execution when you do not need to work in the system.

![Connector PDF](_assets/scheduled-jobs-PDF.png){.medium}

### Refresh Cache for Dynamic Relations

Available with [Advanced Data Management](../../11.entity-management/06.advanced-data-management/) module.

This job is used when you use dynamic relations, such as dynamic categories. Refreshing cache is needed for relations to show up-to-date data.

### Calculate Data Quality

Available with [Data Quality](../../../15.data-quality/) module.

This job analyzes data quality metrics across the system and generates reports on data completeness, accuracy, and consistency.

### Export Feed

This job starts the selected export feed on the schedule you set. The settings set by the feed are taken from the feed menu, so it is essentially pressing the `Export` button on the set time.

<!-- TODO: what 'Maximum hours to look back' param stands for? -->
![Export feed Scheduled](_assets/scheduled-jobs-export-feed.png){.medium}

### Recalculate Product Price

Available with [Pricing](../../../../06.erp/01.pricing/) module.

This job is used when you use dynamically calculated prices. This job starts recalculating action for all products.

> Note that this job may take a lot of time and server resources, so please schedule its execution when you do not need to work in the system.

### Update currency exchange via ECB

This job inserts current exchange rates taken from ECB to AtroCore currencies. Only currencies and rates supported by ECB are affected by this job.

### Synchronization

Available with [Synchronization](../../../../02.data-exchange/03.synchronization/docs.md) module.

This job starts the selected synchronization on the schedule you set. The settings set by the feed are taken from the synchronization menu, so it is essentially pressing the `Execute` button on the set time.

![Synchronization Scheduled](_assets/scheduled-jobs-synchronization.png){.large}

### Failed jobs retrial

This job looks for the selected jobs done by import and export feeds and/or other scheduled jobs and then starts ones of them that had failed status in the last hours set in `Maximum hours to look back` field.

They will be shown in additional panel Retrials.

![Retrial Scheduled](_assets/scheduled-jobs-retrials.png){.medium}

### Action

Available in [Workflows](../../../../04.collaboration/01.workflows/) module.

This job type allows for custom actions to be executed on a schedule. It provides flexibility for executing specific business logic or custom operations.

### Clear deleted data

Add this job to regularly clean the database of deleted records, relations with deleted records or records that have not been modified for a long time. It will remove garbage from all entities that have required parameters set. 

![Clear deleted data](./_assets/clear-data.png){.large}

Use Scheduled Job `Clear deleted data` to clean up outdated Notifications, Action History, etc. on a regular basis.

The values of parameters `Permanent deletion period, days` and `Auto-delete period, in days` are set in the [configuration](../../11.entity-management/docs.md#configuration-fields) of each entity.

![Delete data parameters](_assets/delete-data-parameters.png){.medium}

With such settings, entity records will be [deleted](../../../08.record-management/docs.md#soft-delete) 21 days after the last modification and after another 35 days they will be [deleted permanently](../../../08.record-management/docs.md#permanent-delete) from the database.

### Find matches

This scheduled process automatically evaluates new and updated records against the defined [Matching Rules](../../../18.master-data-management/17.matching/docs.md#matching-rules).

![Find matches](_assets/Find-matches.png){.medium}

### Create clusters

This job processes matching results and manages the full lifecycle of [Clusters](../../../18.master-data-management/19.clusters/docs.md) – creating new clusters, updating existing ones, and automatically confirming single-item clusters.

On each run, the job:

- Creates clusters for entities that have a Matching configuration with active rules, based on the Matched Score data from the Matching entity.
- Adds new cluster items or reassigns existing ones if matching results have changed since the last run.
- Automatically confirms cluster items where applicable.
