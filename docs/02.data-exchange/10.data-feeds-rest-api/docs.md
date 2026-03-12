---
title: Import/Export Feeds REST API
taxonomy:
    category: docs
---

Import and Export Feeds can also be used as REST API endpoints with a flat data structure. So, if need need to use a custom REST API endpoint just configure a data feed for it.

## REST API in `Export Feeds` Module

To use this api, you need an active exportFeed with a valid code. Then you can do a `GET` request to `/api/v1/ExportFeed/action/EasyCatalog?code=ExportFeedCode&offset=0`.
This will return data configured in the ExportFeed.

![Export Rest Api Example](_assets/export-api-example.png){.large}

## REST API in `Import Feeds` Module

To use this api, you need an active importFeed with a valid code. Then you can do a `POST` request to `/api/v1/ImportFeed/action/EasyCatalog`.
In the body of the request we have 2 parameters.

- `code` – the importFeed code
- `json` – an array of object that contains values to update

![Import Rest Api Example](_assets/import-api-example.png){.large}

## Usage in EasyCatalog
If you have a [EasyCatalog Adapter](../../07.publishing/02.easycatalog-adapter) you can use the Import and Export Feeds to enable seemless data integration with InDesign.  These REST API endpoints are to be used for data sources configured in EasyCatalog for Adobe InDesign.
