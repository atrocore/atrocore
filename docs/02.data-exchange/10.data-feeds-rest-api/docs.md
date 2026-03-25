---
title: Import/Export Feeds REST API
taxonomy:
    category: docs
---

[Import](../01.import-feeds/docs.md) and [Export](../02.export-feeds/docs.md) Feeds expose REST API endpoints for flat data exchange. Use these endpoints to integrate AtroCore with external tools, or to set up a custom REST API endpoint by configuring a data feed.

## Authentication

All requests require an `Authorization-Token` header. To obtain a token, send a `GET` request to `/api/v1/App/user` with HTTP Basic authentication:

```http
Authorization: Basic <base64(username:password)>
```

The response contains `authorizationToken`. Include this value as a header in all subsequent requests:

```http
Authorization-Token: <authorizationToken>
```

## Export Feed REST API

You need an active Export Feed with a valid **Code**.

Send a `GET` request:

```http
GET /api/v1/ExportFeed/action/EasyCatalog?code=<ExportFeedCode>&offset=<offset>
Authorization-Token: <authorizationToken>
```

**Query parameters:**

- `code` – Export Feed code
- `offset` – Starting record index (default: `0`)

**Response format:**

```json
{
  "records": [...],
  "urlColumns": [...],
  "total": 100
}
```

- `records` – array of flat record objects
- `urlColumns` – list of field names that contain asset URLs
- `total` – total number of available records

> Paginate by incrementing `offset` by the number of records returned in each response, until `offset >= total`.

![Export Rest Api Example](_assets/export-api-example.png){.large}

## Import Feed REST API

You need an active Import Feed with a valid **Code**.

Send a `POST` request to `/api/v1/ImportFeed/action/EasyCatalog` with `Content-Type: application/json`.

**Request body:**

```json
{
  "code": "<ImportFeedCode>",
  "json": [
    { "ID": "record-id-1", "fieldName": "value" },
    { "ID": "record-id-2", "fieldName": "value" }
  ]
}
```

- `code` – Import Feed code
- `json` – array of objects containing field values to update

![Import Rest Api Example](_assets/import-api-example.png){.large}

## Usage in EasyCatalog

The [EasyCatalog Adapter](https://store.atrocore.com/en/indesign-pim-adapter-for-easycatalog/20157) uses these endpoints to exchange product data between AtroPIM and Adobe InDesign. Configure an Export Feed and an Import Feed, provide their codes in the EasyCatalog data source settings, and the adapter handles authentication and data transfer automatically.
