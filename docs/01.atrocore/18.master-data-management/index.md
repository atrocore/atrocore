---
title: Master Data Management 
---

AtroСore can be used as a Master Data Management (MDM) system. This means that users can import data into the platform in any convenient way. Inside AtroСore, this data can then be improved, structured, and enriched if necessary, and finally exported to external systems such as marketplaces or other platforms.

Imported data can be modified during the import process. However, in some cases it is important to preserve the original data exactly as it was received and not modify it, while at the same time creating new records in other entities based on this data. This is especially relevant when data comes from multiple source systems that may differ in their field sets, data formats, or structures.

To manage such scenarios, AtroСore provides the concept of Master Data Management (MDM). With this approach, the data structure consists of three main layers: Source Entities, Contributor Entity, and Master Entity.

## Source Entity

A Source Entity is the initial entity (or multiple entities) into which users import unmodified or minimally modified data from one or more external systems.

- A source entity can have an arbitrary set of fields, depending on the structure of the incoming data.
- It reflects the raw or near-raw data as provided by external systems.
- Multiple source entities can exist if data is coming from different systems with different schemas.

## Contributor Entity (Derivative Entity)

The Contributor Entity (a Derivative entity with the `Contributor` role) is used as an intermediate layer between source data and master data.

It is a full copy of the Master Entity and reproduces its data model exactly, with only one important difference – it does not contain any mandatory or unique fields.

- The contributor entity is always a single entity per master entity.
- It is linked to the master entity with a one-to-one relationship.

### Create Contributor entity

To create a contributor entity, go to `Administration / Entities` and create a new entity. In the Derivation panel, set the `Primary Entity` field to the master entity and select `Contributor` in the `Role` field.

Please note:

- The role cannot be changed after the derivative entity is created.
- Only one derivative entity with the same role can exist per master entity.
- A derivative entity cannot be created from another derivative entity.
- Derivative entities cannot be deleted directly.

All derivatives of a master entity are listed in its `Derivatives` panel.

The contributor entity contains a Master Record field, which can be used to link the contributor record to the corresponding record in the master entity.

The contributor entity fully inherits the fields, attributes, and layouts of the master entity. These settings cannot be modified manually for the contributor entity, as they are always inherited automatically from the master entity.

### Navigation between Contributor and Master entities

For convenient navigation between Contributor and Master entities, the buttons "Open Contributor Entity" and "Open Master Entity" are available in the top-right corner of the list view.

These buttons allow users to quickly switch from a Contributor entity to its corresponding Master entity and vice versa.

Please note that the button is displayed only if the user has permission to view the corresponding entity. If the user does not have the required access rights, the button will not be visible.

### Data Pipelines

To define how source data is transferred to the contributor entity, configure one or more **Data Pipelines**. Each pipeline connects one source entity to a target entity and defines the transformation logic.

Pipelines are managed under `Administration / Data Pipelines` in the Master Data Management section.

Each pipeline record contains the following fields:

- **Source Entity** – the source entity this pipeline reads from.
- **Target Entity** – the target entity this pipeline writes to (e.g. the contributor entity).
- **Merging Script** – a Twig script that defines how source record data is transformed and mapped to the target record.

Both the Source Entity and Target Entity fields are locked after the pipeline is created and cannot be changed. Only one pipeline can exist per pair of entities.

#### Merging Script

The merging script is a Twig template that must return a JSON object with the key `targetRecordData`, containing the field values to write to the target record:

```twig
{
  "targetRecordData": {
    "name": "{{ sourceRecord.name }}"
  }
}
```

> The default value of the field is wrapped in a Twig comment (`{# ... #}`) and serves only as an example of the expected format – a script wrapped in comment markers produces no output and is ignored. Remove the comment markers and adjust the script to activate the pipeline.

Two variables are available in the script:

- `sourceRecord` – the source record that triggered the sync.
- `targetRecord` – the existing target record, or `null` when the target record does not yet exist.

#### Automatic synchronization

Once a pipeline is configured, the system automatically:

- **Creates** a new target record when a source record is saved and has no linked target record yet. The created record is linked to the source record via its Target Record field.
- **Updates** the target record when the source record is saved and is already linked.
- **Re-applies** all pipelines for the target entity when the Master Record link of the target (contributor) record is changed – data from all linked source records is pushed to the target record again.

All synchronization operations are performed on behalf of the system user, regardless of who triggered the save.


### Data Unification and Deduplication

At the contributor level, data is prepared for consolidation into the master entity. Two key processes take place here: data unification and duplicate detection.

Data unification means bringing data into a consistent and standardized format. This may include, for example, representing phone numbers in a single unified format, normalizing country codes, aligning date formats, or standardizing naming conventions. Such transformations ensure that data coming from different source systems becomes comparable and consistent.

Duplicate detection is performed using the [Matching](./17.matching/index.md) mechanism in PIM. In this mechanism, you can define matching rules that determine how potential duplicates are identified. These rules may be based on one or multiple fields (for example, name, email, phone number, external ID, or combinations of these) and can include exact or fuzzy matching logic.

After unification and duplicate detection, unified and validated data is transferred to the Master Entity, where it forms a single, consolidated, and reliable version of each record.

## Consolidation

The consolidation of contributor records into master records is configured via **Consolidation** records, managed under `Administration / Consolidations` in the Master Data Management section. One Consolidation record exists per master entity and contains the following settings:

- **Entity** – the master entity this configuration applies to. Only master entities that already have a derivative with the Contributor role can be selected.
- **Consolidation Script** – a Twig script that defines how contributor record data is transformed, unified, and mapped to the master record. See [below](#consolidation-script).
- **Execute Merge As** – the user account that will be used to execute the Consolidation Script: `System` or `Same User`.
- **Update Master Automatically** – when enabled, any update to a contributor record automatically triggers an update of the linked master record according to the Consolidation Script.
- **Confirm Automatically** – when enabled, cluster items are confirmed automatically by the `Create Clusters` scheduled job. When checked, the **Minimum Matching Score** field becomes required and defines the confirmation threshold.
- **Delete Invalid Masters Automatically** – when enabled, excess master records in invalid clusters are deleted automatically.

See [Clusters](./19.clusters/index.md) for details on how these settings are applied during the clustering and confirmation workflow.

### Consolidation Script

The consolidation script is a Twig template that must return a JSON object with the key `masterRecordData`, containing the field values to write to the master record:

```twig
{
  "skipped": false,
  "masterRecordData": {
    "name": "{{ contributorRecord.name }}"
  }
}
```

> The default value of the field is wrapped in a Twig comment (`{# ... #}`) and serves only as an example of the expected format – a script wrapped in comment markers produces no output. Remove the comment markers and adjust the script to activate the consolidation.

Three variables are available in the script:

- `contributorRecord` – the contributor record being consolidated.
- `contributorRecords` – all contributor records linked to the master record.
- `masterRecord` – the existing master record, or `null` when the master record does not yet exist.

If the returned object contains `"skipped": true`, the operation is skipped and the master record is neither created nor updated.
