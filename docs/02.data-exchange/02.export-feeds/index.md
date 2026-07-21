---
title: Export Feeds
---

The **Export Feeds** module enables structured, configurable data export from AtroCore. An export feed is a reusable template that defines which entity data to export, in what format, and with what field mapping.

With the help of the **Export Feeds** module, data export from the AtroCore system is performed in accordance with the export templates that can be further configured and customized, as well as reused at different time intervals.

Data export can be performed:

- **Manually** – by running configured export feeds directly
- **Automatically** – via [Scheduled Jobs](../../01.atrocore/03.administration/05.system-jobs/01.scheduled-jobs/index.md#export-feed) or [Workflows](https://store.atrocore.com/en/workflows/20194)

The base module supports file-based exports (CSV, Excel, JSON, XML, SQL). Additional modules extend export feed capabilities:

- [Export: HTTP Request](../09.export-feeds-http-request/index.md) – exports data via HTTP request.
- [Export: Remote File](https://store.atrocore.com/en/export-remote-file/20144) – exports files to FTP, sFTP, or SSH servers.
- [Export: Database](https://store.atrocore.com/en/export-database/20138) – exports data to MSSQL, MySQL, PostgreSQL, Oracle, or HANA databases.
- [Synchronization](https://store.atrocore.com/en/synchronization/20124) – orchestrates multiple import and export feeds for complex data exchange.

## Administrator Functions

> Users can work with export feeds according to their assigned role permissions after administrator configuration.

After installation, two entities are created: `Export Feeds` and `Export Executions`. These can be enabled or disabled in the [navigation menu](../../01.atrocore/03.administration/13.user-interface/01.navigation/) and [favorites](../../01.atrocore/05.toolbar/02.favorites/), with [access rights](../../01.atrocore/03.administration/14.access-management/) configured as for other entities. Layout configuration is not available for these entities.

!! Users must have the following permissions configured via [Roles](../../01.atrocore/03.administration/14.access-management/03.roles/index.md) (Scopes panel): `Export Feeds`, `Export Execution` and `Files`. Without these, feed export execution will be denied. In  [Access Control List](../../01.atrocore/03.administration/14.access-management/index.md#acl-strict-mode) strict mode, these permissions must be granted explicitly — they are not given by default.

## Export Feed Creation

Navigate to **Export Feeds** and click `Create`. Enter a name, select a type, and define the owner. The default type is **File**. To export to other destinations, install the corresponding module.

![Create Export Feed](_assets/export-feeds-new.png){.medium}

### Details Panel

![Details Panel](_assets/export-feed-details-panel.png){.medium}

- **Name** – export feed identifier.
- **Active** – enables or disables the export feed.
- **Type** – set at creation; cannot be changed afterward. Available types depend on installed modules.
- **Code** – unique export feed code.
- **Description** – optional usage notes and reminders.
- **Maximum Number of Records per Iteration** – maximum number of rows exported per iteration.
- **Separate Job per Iteration** – when enabled, each iteration runs as an independent export job.
- **Maximum Number of Workers** – number of workers that can execute this feed in parallel; if not set, all available workers are used.
- **Replace Existing File** – when enabled, the previously exported file is replaced on each run.

Available with the [Synchronization](https://store.atrocore.com/en/synchronization/20124) module fields:

- **Priority** – execution priority: `Low`, `Normal`, or `High`.
- **Scheduled Job** – links the feed to a [Scheduled Job](../../01.atrocore/03.administration/05.system-jobs/01.scheduled-jobs/index.md) for automated execution.

### Export Data Settings

![Export Data Settings Panel](_assets/export-data-settings.png){.medium}

> The fields in this panel vary depending on the selected `Type`.

For type-specific configuration, see:

- [Export: Database](https://store.atrocore.com/en/export-database/20138)
- [Export: HTTP Request](../09.export-feeds-http-request/index.md)
- [Export: Remote File](https://store.atrocore.com/en/export-remote-file/20144)

! It is recommended to create a dedicated folder for each export feed to keep files organized.

**General fields:**

- **Format** – output file format: **CSV**, **Excel**, **JSON** or **XML**. The **SQL** format is intended for use with the [Export Database](https://store.atrocore.com/en/export-database/20138) module.
- **Folder** – [folder](../../01.atrocore/03.administration/15.file-management/index.md#folder-management) where exported files will be stored. Required.
- **File Name Mask** – filename template using [Twig syntax](../../10.developer-guide/80.twig-tutorial/index.md)

> Configuration fields vary by selected format.

**CSV and Excel:**

- **Header Row** – when enabled, column names are included in the first row. Enabled by default for Excel.
- **Has Multiple Sheets** – enables multi-sheet Excel export. Requires the [Synchronization](https://store.atrocore.com/en/synchronization/20124) module.

CSV-specific:

- **Field Delimiter** – field separator: `;`, `,`, or `\t`.
- **Text Qualifier** – value enclosure: single or double quotes.
- **Use Quotes for All Values** – when enabled, all values are quoted; otherwise only multi-word text values are quoted.

**XML and JSON:**

- **Template** – output structure defined using [Twig syntax](../../10.developer-guide/80.twig-tutorial/index.md).
- **Template Name** – select a predefined Twig template.

**XML schema validation:**

When the exported XML, the system automatically extracts the schema URL, downloads the XSD file, and validates the output against it. Errors and fatal errors additionally set the execution state to `Failed`. If no `schemaLocation` is present in the XML, validation is skipped silently.

### Feed Settings

![Feed Settings](_assets/export-feed-settings.png){.medium}

- **Entity** – entity whose records will be exported.
- **Sort Order (Field)** – field used to sort exported records.
- **Sort Order (Direction)** – `ASC` (smallest first) or `DESC` (largest first).
For CSV and Excel formats, two language-related settings are available:

- **Locale** – determines the language used for **column headers** (field and attribute names) and number/date formatting (decimal mark, thousand separator). All locales defined in the system are available for selection. Configured via [Locale](../../01.atrocore/03.administration/02.locales/index.md).
- **Language** – determines the [language](../../01.atrocore/03.administration/03.languages/index.md) of the exported **cell values**. When set, multilingual field values and attribute values are exported in that language only.

**CSV and Excel:**

- **Marker for Empty Value** – symbol interpreted as an empty value in addition to empty cells.
- **Marker for Null Value** – symbol interpreted as NULL.
- **Marker for No Relation** – label used when a relation is not linked.
- **Marker for Unlinked Attribute** – label used when a channel-specific attribute value is not set.
- **Convert Collection to String** – converts multi-enum, array, and one-to-many relation fields to string.
- **Convert Relations to String** – converts related entity fields to string.
- **List Value Separator** – delimiter for values within list fields.
- **Field Delimiter for Relation** – separator for fields within a relation string.
- **Maximum Depth of Relations to Show** – maximum depth of related entity fields to include.

!! All marker and separator symbols must be different.

Click `Save` to complete the creation.

> To configure the feed, open it and click `Edit`. Inline editing is also supported via the pencil icon next to each editable field.

## Configurator

![Configurator](_assets/export-configurator.png){.medium}

> Accessible from the [detail view](../../01.atrocore/04.understanding-ui/index.md#detail-view) of the export feed after the feed is saved.

The `Configurator` panel defines which fields and attributes are included in the export and in what order. Use the panel menu to add items:

- **Select Field(s)** – opens the `Entity Fields` window to select entity [fields](../../01.atrocore/03.administration/11.entity-management/03.fields-and-attributes/index.md). The window provides two confirmation buttons: **Select** adds only the main-language item, while **Select (All languages)** adds one configurator item per available language variant in addition to the main-language item.
- **Select Attribute(s)** – opens the `Attributes` window to select entity [attributes](../../01.atrocore/03.administration/12.attribute-management/01.attributes/index.md) (available for entities that support attributes). The same two buttons apply: **Select** adds only the main-language item, **Select (All languages)** adds one item per language variant.
- **Add All Attributes** – adds all attributes linked to the entity at once.
- **Add Fixed Value** – adds a constant value column to the export.
- **Add Script** – adds a computed column using [Twig syntax](../../10.developer-guide/80.twig-tutorial/index.md).
- **Remove All** – removes all items from the configurator.

### Field Configuration

Each configurator item has a **Column** setting, which defines the column header name in the output file. Additional settings depend on the field type.

Added items are displayed as a list with `Field`, `Column Name`, and `Remove` columns. Use the single record actions menu on each item to edit its configuration.

> Fields order in the export file is controlled via drag-and-drop in `Configurator`.

### Relation Fields

For fields that reference multiple related records (e.g., *Categories*, *Classifications*), additional settings control which related records are exported and how:

![Limit and Offset](_assets/limit-offset.png){.medium}

- **Related Entity Fields** – field(s) of the related entity to export.
- **Separate** – exports each related record into its own column instead of combining them into one value.
- **Offset** – number of related records to skip before exporting.
- **Limit** – maximum number of related records to export (default: `20`).
- **Sort Order (Field)** – field used to sort related records before applying `Offset` and `Limit`.
- **Sort Order (Direction)** – `ASC` (smallest first) or `DESC` (largest first).

!! On MySQL/MariaDB, keep `Limit` at or below 25 for fields with a large number of related records. Higher values may exceed the database's `group_concat_max_len` setting, causing related records to be silently dropped from the exported column. This restriction does not apply to PostgreSQL.

### Attributes

Attributes can be added to the export in two ways:

- **Select Attribute(s)** – opens the `Attributes` window to pick one or more specific attributes.
- **Add All Attributes** – adds all attributes linked to the entity at once, which can then be filtered by [channel](../../05.pim/06.channels/index.md).

**Channel** – filters which attribute values are included based on channel assignment:

  - *No channel selected* – exports only attribute values not assigned to any channel; channel-specific values are excluded.
  - *Single channel selected* – exports values without a channel and values assigned to the selected channel.
  - *Multiple channels selected* – exports values without a channel and values assigned to any of the selected channels.

![Item Configuration](_assets/item-configuration.png){.medium}

### Script Type in Configurator

![Script in configurator](_assets/export-script-in-configurator.png){.large}

The Script type allows exporting computed values using [Twig syntax](../../10.developer-guide/80.twig-tutorial/index.md). Two variables are available inside the script:

- **`record`** – a flat associative array of the exported entity's field values for the current row. Keys correspond to field names (e.g., `record.name`, `record.sku`, `record.isActive`).
- **`configuration`** – the configurator item settings array (column name, type, exportBy, channels, etc.).

Example — exporting a computed value from a standard entity:

```twig
{{ record.name }} ({{ record.sku }})
```

Example — exporting the `From` value for Range attributes and the option code for List attributes (when the exported entity is `ProductAttributeValue`):

```twig
{% if record.attributeType == 'rangeInt' or record.attributeType == 'rangeFloat' %}
  {{ record.valueFrom }}
{% elseif record.attributeType == 'extensibleEnum' %}
  {{ record.valueOptionData.code }}
{% endif %}
```

If you need to access multiple attribute values and do not require channel- or language-specific filtering, use the `putAttributesToEntity` filter. It loads all attribute values directly onto the record, making them accessible by attribute code:

```twig
{% set product = record | putAttributesToEntity %}
{{ product.your_attribute_code }}
```

### Units in Configurator

For fields or attributes that contain unit values, the following export options are available:

- **Script** – exports the full value (numeric + unit name).
- **Value (Numerical)** – exports only the numeric part.
- **Value Unit** – exports only the unit (name, code, or other unit identifier).

### Exporting Files/Images

![Config Images](_assets/image-view.png){.large}

For files fields, three URL types are available:

- **Download URL (Shared)** – publicly accessible download link; no authorization required.
- **Download URL** – download link that requires authorization.
- **View URL (Shared)** – publicly accessible URL for viewing the image directly in a browser.

![Config Images](_assets/view-url.png){.large}

### Export Files to ZIP Archive

When exporting entities that contain Files fields (e.g., Product, Brand), files can be bundled into a ZIP archive alongside the data file. The archive contains the exported data file and one folder per file field, named after that field.

To enable this, check **Export Files to a ZIP Archive** on the relevant configurator item. This checkbox appears only when the selected field contains files.

![Export to Archive](_assets/export-files-to-archive.png){.medium}

The names of the files in the archive correspond to the names of the assets by default. You can change naming using the **File Name Template** field.

![File name template](_assets/file-name-template.png){.large}

The **File Name Template** field accepts [Twig syntax](../../10.developer-guide/80.twig-tutorial/index.md) and supports the following variables:

- `{{ fileName }}` – original filename of the asset (default)
- `{{ currentNumber }}` – serial number of the asset within the exported entity
- `{{ entity }}` – the exported entity object; access its fields with dot notation (e.g., `{{ entity.name }}`, `{{ entity.sku }}`)
- `{{ entityId }}` – the ID of the exported entity record

By default, `{{ fileName }}` is set, preserving the original asset name. To append a sequence number:

```twig
{{ fileName }}_{{ currentNumber }}
```

To include the entity's name field in the filename:

```twig
{{ entity.name }}_{{ fileName }}_{{ currentNumber }}
```

Then when exporting from two products that have two assets each, the naming will be as follows:

![File naming](_assets/file-naming.png){.large}

To add an attribute value to the filename, use `findRecord()`:

```twig
{% set pav = findRecord('ProductAttributeValue', {
    'productId': entity.id,
    'attributeId': 'h64e5a3eeaf1a728f',
    'channelId': '',
    'language': 'main'
}) %}
{{ pav.value }}_{{ fileName }}_{{ currentNumber }}
```

We have attribute value 67 for Product1 and 4435 for Product2 so the file names will look like this:

![File naming](_assets/file-naming1.png)

## Filter Result

![Filter Results](_assets/filter-results.png){.medium}

The `Filter` panel defines which records are included in the export. Filtering follows the same logic as the entity list view. To learn more, see [Search and Filtering](../../01.atrocore/11.search-and-filtering/index.md).

![Configure Filter Results](_assets/configure-filter.png){.medium}
To configure the filter, click `Edit` in the top menu of the export feed, then set the conditions in the `Filter` panel. The `Filter Result` panel displays all records matching the defined criteria and updates automatically when the filter changes.

### Exporting Only Modified Records

To export only records changed since the last feed run, apply the **Unexported** boolean filter. This selects records whose `Modified At` date is greater than or equal to the feed's `Last Run` value.

![Unexported Filter](_assets/unexported-filter.png){.medium}

The `Last Run` value can be adjusted manually to re-export a specific time range. By default, `Modified At` is updated when entity fields or attributes change. Additional relationships that affect the modification date can be configured in the [entity settings](../../01.atrocore/03.administration/11.entity-management/index.md#configuration-fields) under `Relations Effecting Modification Date/Time`.

![Last Run](_assets/last-run.png){.large}

## Running an Export Feed

Click the `Export` button on the export feed detail view to start the export immediately.

![Run Export Option](_assets/export-button.png){.large}

The job appears in the [Job Manager](../../01.atrocore/05.toolbar/03.job-manager/) with its current status. Errors, if any, are also displayed there.

Executions are added to the `Export Executions` panel with `Pending` status, changing to `Success` upon completion.

View the last execution time and status on the export feed detail and list views.

After a successful export, a notification appears in the `Notifications` panel, from which the exported file can be downloaded directly.

![Export Notification](_assets/export-notification.png){.medium}

Files generated by an export are linked to the corresponding `Export Execution` record via the `Exported File` field, which provides access to the stored file and enables tracing its origin in the [File](../../01.atrocore/13.file-operations/index.md) entity.

> You can create an export feed [Action](../../01.atrocore/03.administration/06.actions/index.md#export-feed) to operate in [Scheduled jobs](../../01.atrocore/03.administration/05.system-jobs/01.scheduled-jobs/index.md#export-feed) or [Workflows](https://store.atrocore.com/en/workflows/20194).

### Exporting from Entity

You can export selected data directly from different entities.

To do so, navigate to the target entity [list view](../../01.atrocore/04.understanding-ui/index.md#list-view) or [plate view](../../01.atrocore/04.understanding-ui/index.md#plate-view) records. Select which ones you want to export.

![Export From Entity Action](_assets/export-from-entity-action.png){.medium}

To export them, after selecting, press `Actions` dropdown and select `Export`. There you will see export popup menu.

![Export From Entity Popup Meny](_assets/export-from-entity-popup.png){.medium}

You can select an existing Export Feed associated with the current entity and click `Export`.

![Export From Entity With Feeds](_assets/export-from-entity-feeds.png){.medium}

> For the purposes of this execution all the configuration will be taken from selected `Export feed` except filters. Only the records explicitly selected in the view will be exported.

When using an existing export feed, you can also override the feed's `Content Language` and `Locale` for this run only. This is useful when the feed is configured with language-specific columns ("All languages" items) and you want to export content in a single specific language without modifying the feed itself.

Also, you can configure a one-time export directly in the same popup menu. This option supports only CSV and XLSX (Excel) formats and allows you to manually select the entity fields and attributes to be included in the export

## Export Executions

Export execution results are displayed in two locations:

**Export Executions panel** shows executions related to the selected export feed. Use the `Refresh` action next to an active execution to update the displayed counters and status information. Also, you can select `Show List` to open a filtered list of error records associated with the specific execution.

![Export Executions Panel](_assets/export-executions-panel.png){.medium}

**Export Executions list view** displays all Export executions across the system.
To access it, select Export Executions from the main navigation.

![Export Executions List](_assets/export-executions-list.png){.medium}

> You can use [single record actions](../../01.atrocore/04.understanding-ui/index.md#single-record-actions) to remove executions.

Execution details include:

- **Name** – auto-generated execution name (click to open [detail view](../../01.atrocore/04.understanding-ui/index.md#detail-view)).
- **Export Feed** – source export feed name.
- **Exported File** – output file name (click to download).
- **State** – current execution status.
- **Count** – number of exported records.
- **Started At / Finished At** – execution timestamps.

Execution States:

- **Running** – currently executing. Available actions: **Cancel**, **Delete**.
- **Pending** – queued for execution. Available actions: **Cancel**, **Delete**.
- **Success** – completed (may contain errors). Only **Delete** is available action.
- **Failed** – technical failure. Available actions: **Delete** and **Export Again**.
- **Canceled** – user-stopped. Available actions: **Delete**, **Export Again**.

### Retry Actions for Failed or Canceled Executions

For `Failed` or `Canceled` executions, the **Export Again** action is available from the row actions menu in the `Export Executions` panel. It re-runs the full export — data is re-fetched, re-serialized, and re-sent.

> For [Export: HTTP Request](../09.export-feeds-http-request/index.md) executions, an additional **Resend Request** action is available.

## Export Feed Actions

Standard [record management actions](../../01.atrocore/08.record-management/index.md#single-record-actions) and [mass actions](../../01.atrocore/08.record-management/index.md#mass-actions) are available for export feeds.

![Export Feed Actions](_assets/export-feed-actions.png){.medium}

- **Export** – executes the feed immediately.
- **Duplicate** – opens the creation page pre-filled with all field values and configurator mapping rules from the current feed.
- **Delete** – removes the feed record.
- **Duplicate as Import** – creates a new [import feed](../01.import-feeds/index.md#creating-import-feed-from-export-feed) with matching entity, format, and mapping rules.
- **Copy Configuration** – copies the feed configuration as JSON for API-based recreation. See [Copying Feed Configurations](../11.copying-feed-configurations/index.md).

> **Duplicate as Import** and **Copy Configuration** actions are available exclusively in the [detail view](../../01.atrocore/04.understanding-ui/index.md#detail-view) of the export feed.
