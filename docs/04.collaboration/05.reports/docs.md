---
title: Reports
taxonomy:
    category: docs
---

The [Reports](https://store.atrocore.com/en/reports/20213) module lets you create tabular summaries of any entity's data. Records are grouped by one or more fields, and aggregation functions calculate statistics across each group. Completed reports can be pinned to the [Dashboard](../../01.atrocore/07.dashboards) as dashlets.

Clicking a value in an aggregation column redirects to a filtered list of the underlying records — for example, clicking a product count for a specific brand opens the product list pre-filtered to that brand.

## Report types

Two report types are available, selected at creation and fixed thereafter.

**Summary**

Groups records by any number of fields and supports multiple aggregations. Each unique combination of the group-by field values forms one row. All group-by fields and aggregation columns appear as column headers. A **Total** row is appended at the bottom.

**Crosstable**

Groups records by exactly two fields: the first becomes row headers, the second becomes column headers. Exactly one aggregation is required, and its values fill the cells. All possible combinations of row and column header values are shown in the table — including combinations that have no matching records — giving a complete overview of the data space. Charts can be generated from the resulting table.

> Crosstable does not support period-based aggregation.

## Creating a report

After installing the module, the **Reports** entity appears in the left navigation menu. If it is missing, add it under `Administration > User Interface`.

Open the Reports entity and click **Create**. Fill in the following fields:

**Name** - Display name for the report.
**Type** - `Summary` or `Crosstable` — cannot be changed after saving.
**Entity** - The entity whose records the report covers (e.g. Product) — cannot be changed after saving.
**Group By** - Fields used to group records; only List and Link field types are available. Summary accepts one or more fields; Crosstable requires exactly two.

Save the record to proceed to the detail view.

## Configuring a report

After creation, the detail view provides additional controls.

In the detail view you can set **Order By** to control how rows are sorted. Available values are the selected Group By fields and any defined aggregations, each with an `ASC` or `DESC` direction.

### Search filter (record scope)

Two filter buttons appear in the action bar above the Details panel:

- **Advanced Filter** — opens the standard search filter to restrict which entity records are included in the report. For example, limit a product report to active products only.
- **Field Value Filters** — lets you set a visibility rule for each field. Options are `Filled`, `Empty`, `Optional`, and `Required`. `Filled` and `Empty` are mutually exclusive, as are `Optional` and `Required`.

### Aggregations

Aggregations are configured in a dedicated panel below the Details panel. Each aggregation record has the following fields:

| Field | Description |
|---|---|
| **Name** | Label displayed as the column header |
| **Report** | The report this aggregation belongs to |
| **Function** | The aggregation function (see table below) |
| **Field** | The entity field to aggregate; not applicable for `COUNT` and `COUNT (%)` |
| **Sort Order** | Display order among aggregation columns |

Available functions:

| Function | Description |
|---|---|
| **COUNT** | Total number of records in the group |
| **COUNT (%)** | Share of the group's records relative to the total record count |
| **SUM** | Sum of the selected numeric field |
| **AVG** | Average of the selected numeric field |
| **MAX** | Maximum value of the selected numeric field |
| **MIN** | Minimum value of the selected numeric field |

A Summary report supports multiple aggregations. A Crosstable report supports exactly one.

### Period-based aggregation (Summary only)

Summary reports support period-based aggregation. You can define a periodicity (week, month, or year), select a date or datetime field as the period basis, and specify how many periods to include (1–12, default 6). This splits records into time buckets while still respecting the Group By fields.

For example: number of products created over the last six months, split by month, grouped by product status.

## Displaying a report on the Dashboard

Once a report is saved, add it to the Dashboard by creating a new dashlet of the **Report** type. Click **Options** on the dashlet to select which report to display and set the dashlet name.

See [Dashboards and Dashlets](../../01.atrocore/07.dashboards/docs.md#dashlets) for general dashlet management.
