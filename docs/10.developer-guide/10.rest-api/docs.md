---
title: REST API Overview
taxonomy:
    category: docs
---

AtroCore is an **API-centric** application where the frontend communicates with the backend exclusively via REST API requests. Our REST API is built on **OpenAPI (Swagger)**, allowing you to [automatically generate a client](https://openapi-generator.tech/docs/generators/) in the programming language of your choice.

Every action you perform in the UI can be replicated through an API request. A good way to learn how the API works is to monitor the **network tab** in your browser's developer console (`F12`).

All API requests must include the header `Content-Type: application/json`. The base path for all API requests is `/api/`.

!!! **Best Practice**: We recommend creating a separate API user with a specific role and limited permissions for all API calls.

## Video Tutorials
For those who prefer video content, these tutorials provide a quick overview of key API concepts:

* **How to Authorize:** [https://youtu.be/GWfNRvCswXg](https://youtu.be/GWfNRvCswXg)
* **How to Select Specific Fields:** [https://youtu.be/i7o0aENuyuY](https://youtu.be/i7o0aENuyuY)
* **How to Filter Data Records:** [https://youtu.be/irgWkN4wlkM](https://youtu.be/irgWkN4wlkM)

## API Documentation

The REST API documentation is automatically generated for each AtroCore project based on its configurations and installed modules. You can access it at `https://ATROCORE_INSTANCE_URL/apidocs/`.

For example, the documentation for our public demo instance is available at: [Demo REST API](https://demo.atropim.com/apidocs). You can use the **login `admin`** and **password `admin`** in the basic authentication of the [Swagger](https://demo.atropim.com/apidocs/) to explore the routes.

## Authentication

To use the AtroCore API, you must first obtain an access token. Use the `/api/App/user` endpoint with **Basic Authentication** to get your token.

**Step 1: Encode Basic Token**

First, create a Base64-encoded string of your username and password in the format `{username}:{password}`.

For example in `Javascript`:

```js
let basicToken = btoa('admin:admin');
// result => YWRtaW46YWRtaW4=
```

**Step 2: Get the Authorization Token**

Use the encoded token to make a GET request to the `/App/user` endpoint with the `Authorization-Token-Only` header set to `true`.

```http request
GET /api/App/user HTTP/1.1
Host: demo.atropim.com
Authorization: Basic YWRtaW46YWRtaW4=
Accept: application/json
Authorization-Token-Only: true
```

The response will contain your authorization token:

```json
{
    "authorizationToken": "*******************"
}
```

Now, include the header `Authorization-Token: *******************` in all subsequent requests to authorize your calls.

For example, to get the instance [generated metadata](../02.understanding-atrocore/02.metadata/docs.md):

```http request
GET /api/Metadata HTTP/1.1
Host: demo.atropim.com
Authorization-Token: *******************
Accept: application/json
```
## Retrieving records list
When retrieving a list of records from the API, you can control the data returned using several query parameters. These parameters are combined in the URL to build complex queries for selecting, paginating, and filtering data.

-----

###  Selecting Specific Fields

To retrieve only the fields you need, use the `select` parameter with a comma-separated list of field names. This helps reduce the payload size and improves performance.

```http request
GET /api/Product?select=id,name,isActive HTTP/1.1
Host: demo.atropim.com
Accept: application/json
Authorization-Token: ***************
```

> **Note:** The API may still return some additional fields, even if they aren't included in your `select` list. These are typically mandatory fields required by the backend or automatically included virtual fields.

-----

###  Pagination

You can paginate through large record sets using the `offset` and `maxSize` parameters.

* **`offset`**: Specifies the number of records to skip from the beginning of the list.
* **`maxSize`**: Defines the maximum number of records to return in a single response.

<!-- end list -->

```http request
GET /api/Product?select=id,name,isActive&offset=0&maxSize=2 HTTP/1.1
Host: demo.atropim.com
Accept: application/json
Authorization-Token: ***************
```

-----

###  Ordering

To sort the records, use the `sortBy` and `asc` parameters.

* **`sortBy`**: The name of the field to sort by.
* **`asc`**: A boolean value (`true` or `false`) to specify the sort order. `true` for ascending, `false` for descending.

<!-- end list -->

```http
GET /api/Product?select=id,name,isActive&offset=0&maxSize=2&sortBy=name&asc=false HTTP/1.1
Host: demo.atropim.com
Accept: application/json
Authorization-Token: ***************
```

-----

###  Filtering with the `where` Parameter

To filter records, use the **`where`** query parameter. This parameter uses an **array notation** to define a set of conditions. Each condition is an object with the following keys:

* **`attribute`**: The ID of the field or attribute you want to query.
* **`type`**: The operator to use for the comparison (e.g., `equals`, `like`, `isNull`).
* **`value`**: The value to compare against. This is omitted for operators that do not require a value (e.g., `isNull`).
* **`isAttribute`**: (Optional) Set to `true` if you are querying an attribute instead of a standard field.

**Example Request:**

```http
GET /api/Product?where[0][attribute]=name&where[0][type]=like&where[0][value]=%test%&where[0][isAttribute]=false HTTP/1.1
Host: demo.atropim.com
Authorization-Token: ****************
```

This request filters the `Product` records to find those where the `name` field contains the string `test`.

#### Backend Representation

This array notation is a web-friendly way of representing a structured backend array. For example, the query above corresponds to the following PHP array:

```php
$where = [
    [
        "attribute" => 'name',
        "type" => 'like',
        "value" => '%test%',
        "isAttribute" => false
    ]
];
```

> For more complex queries and a full list of supported operators, consult the [Advanced data querying](../02.understanding-atrocore/10.select-manager/docs.md) section in the documentation.
> The array structures detailed there must be converted into the URL query string array notation for use in API requests.

## Advanced API Features

This section provides details on non-standard API requests for common tasks like bulk operations and file uploads.

-----

### Bulk Create and Update

To perform bulk create and bulk update operations, use the `upsert` action on the `MassActions` endpoint. This action is **idempotent**: it attempts to find existing entities by their ID or unique fields. If an entity is found, it's updated; otherwise, a new one is created.

**Endpoint:**

```
POST https://ATROCORE_INSTANCE_URL/api/MassActions/action/upsert
```

**Payload Example:**

The request body should be a JSON array of objects. Each object must specify the `entity` type and the `payload` containing the data to be processed.

```json
[
  {
    "entity": "Product",
    "payload": {
      "name": "Apple iPhone 15",
      "sku": "iphone15"
    }
  },
  {
    "entity": "Product",
    "payload": {
      "id": "2348924928743",
      "name": "Apple iPhone 15 Pro Max"
    }
  }
]
```
You can make some tests in demo API [here](https://demo.atropim.com/apidocs/?atroq=apidocs#/MassActions/3a8459ebdfb2cf077b11238793b42151)

### Creating Linked Entity Records During Create/Update

The system supports simplified creation and linking of related entities during `create` and `update` operations of a main entity.

Instead of providing a foreign key (`<entity>Id`), you may provide:

-   A single object (for single-link fields)
-   An array of objects (for multi-link fields)

The system will:

1.  Attempt to find an existing related record using provided field
    values.
2.  If found --- link it.
3.  If not found --- create it.
4.  Link the resulting record to the main entity.

> It is a first-level "find-or-create and link" mechanism.

------------------------------------------------------------------------

#### Standard Behavior (Using ID)

``` http
POST /api/Product
```

``` json
{
  "name": "Iphone",
  "brandId": "some-id"
}
```

-   The related record must already exist.
-   If the ID does not exist --- the request fails.

------------------------------------------------------------------------

#### New Behavior (Using Object)

##### Single Link Example

``` http
POST /api/Product
```

``` json
{
  "name": "Iphone",
  "brand": {
    "name": "Apple"
  }
}
```

##### Processing Logic

For each provided linked object:

1.  The system searches using the provided fields.
2.  If found --- links existing record.
3.  If not found --- creates a new record.
4.  Links the record to the main entity.

------------------------------------------------------------------------

#### Multiple Link Support

The feature supports:

-   Multiple independent link fields
-   Collection (multi-value) link fields

##### Example

``` http
POST /api/Product
```

``` json
{
  "name": "Iphone",
  "brand": {
    "name": "Apple"
  },
  "color": {
    "name": "green"
  },
  "colors": [
    { "name": "red" },
    { "name": "green" },
    { "name": "pink" }
  ]
}
```

##### Behavior

-   `brand` → single link
-   `color` → another single link
-   `colors` → multi-link (array)

For array fields:

-   Each object is processed independently
-   Each record is searched
-   Created if not found
-   Then linked

------------------------------------------------------------------------

#### Special Case: List Options (`ExtensibleEnumOption`)

If a link field references a `ExtensibleEnumOption` entity, the system
automatically resolves the correct list during creation.

##### How It Works

When creating or linking an option:

1.  The system determines the correct list associated with the field.
2.  It searches for the option inside that specific list.
3.  If found --- links the existing option.
4.  If not found --- creates a new option inside that list.
5.  The option is linked to the main entity.

###### Example

``` json
{
  "status": {
    "name": "In Progress"
  }
}
```

If `status` is linked to `ExtensibleEnumOption`:

-   The system automatically determines which list belongs to `status`.
-   `listId` is not required.
-   The option is created in the correct list if missing.

> Option lookup is always restricted to the list configured for the field.

------------------------------------------------------------------------

#### Matching Rules

The provided object fields are used for:

-   Searching existing records
-   Creating new records (if not found)

Supported **only simple** field types inside linked objects like:

-   `string` (varchar)
-   `integer`
-   `boolean`
- etc.

------------------------------------------------------------------------

#### Limitations

##### 1. First-Level Linking Only

Nested linking is not supported.

Supported:

``` json
{
  "brand": {
    "name": "Apple"
  }
}
```

Not Supported:

``` json
{
  "brand": {
    "name": "Apple",
    "country": {
      "name": "USA"
    }
  }
}
```

Only one level of link resolution is allowed.

------------------------------------------------------------------------


###  File Upload

You can upload files to AtroCore through the `File` entity using one of three methods, each requiring a different input format. In all cases, you must create a `File` entity.

#### Upload by Base64 Content

This method is suitable for smaller files. The file's content is encoded in Base64 and sent directly in the request body.

**Endpoint:** `POST /api/File`

**Payload:**

* **`id`**: (Optional) The unique file ID. If not provided, one will be generated.
* **`name`**: (Required) The name of the file, including its extension (e.g., `"Test.txt"`).
* **`fileContents`**: (Required) The Base64-encoded file data. The format must be `data:{{mimeType}};base64,{{base64EncodedContent}}`.

**Example:**
```http request
POST /api/File HTTP/1.1
Host: demo.atropim.com
Authorization-Token: ****************************
Content-Type: application/json
:
{
  "id": "a99060ec2fc0ddad2",
  "name": "Test.txt",
  "fileContents": "data:text/plain;base64,MTEx"
}
```

#### Upload by URL

This method is useful for uploading files that are already hosted elsewhere. The system will download the file from the provided URL.

**Endpoint:** `POST /api/File`

**Payload:**

* **`id`**: (Optional) The unique file ID.
* **`name`**: (Required) The file name.
* **`url`**: (Required) The public URL of the file to be uploaded.

**Example:**
```http request
POST /api/File HTTP/1.1
Host: demo.atropim.com
Authorization-Token: ****************************
Content-Type: application/json
:
{
  "id": "a62860ec2fc0ddete",
  "name": "picsum.txt",
  "url": "https://picsum.photos/200/300"
}
```

#### Upload by Chunks (for Large Files)

For very large files, you must split the file into smaller pieces (chunks) and upload them individually. This process can be done asynchronously.

**Endpoint:** `POST /api/File`

**Payload:**

* **`id`**: (Required) The file's unique ID. This ID must be consistent for all chunks of the same file.
* **`name`**: (Required) The file name.
* **`fileUniqueHash`**: (Required) A unique identifier for the entire file, distinct from the File entity ID.
* **`start`**: (Required) The starting byte position of the current chunk.
* **`piece`**: (Required) The Base64-encoded chunk data. The format must be `data:application/octet-stream;base64,{{base64EncodedContent}}`.
* **`piecesCount`**: (Required) The total number of chunks for the file.

**Example:**
```http request
POST /api/File HTTP/1.1
Host: demo.atropim.com
Authorization-Token: *******************
Content-Type: application/json
:
{
  "id": "a99060ec2fc0dda33",
  "name": "some.pdf",
  "fileUniqueHash": "22551854",
  "start": 0,
  "piece": "data:application/octet-stream;base64,I4hYADzRed08...",
  "piecesCount": 2
}
```

**Response Handling:**

* **Partial Upload:** If the response contains a `chunks` array, it means the chunk was successfully uploaded but the file is not yet complete.
  ```json
  {
    "chunks": [
      "0",
      "2097152",
      "4194304",
      "6291456"
    ]
  }
  ```
* **Complete Upload:** When the last chunk is successfully uploaded, the response will include and `id` and `name`, confirming the complete file has been created.
  ```json
  {
    "id": "acd7de2808e90041c",
    "name": "test.pdf",
    "chunks": [
      "0",
      "2097152",
      "4194304",
      "6291456"
    ]
  }
  ```

**JavaScript Example for Chunking:**

The following JavaScript code demonstrates how to split a file into 2 MB chunks and prepare them for upload.

```js
function slice(file, start, end) {
    const sliceFn = file.slice || file.mozSlice || file.webkitSlice;
    return sliceFn.call(file, start, end);
}

function createFilePieces(file, chunkSize, pieces) {
    const piecesCount = Math.ceil(file.size / chunkSize);
    let start = 0;
    for (let i = 0; i < piecesCount; i++) {
        const end = Math.min(start + chunkSize, file.size);
        pieces.push({ start: start, piece: slice(file, start, end), piecesCount });
        start = end;
    }
}

function upload() {
    const files = document.getElementById('fileInput').files;
    if (!files[0]) {
        alert('Please select a file.');
        return;
    }

    const inputFile = files[0];
    const chunkSize = 2 * 1024 * 1024; // 2 MB

    const pieces = [];
    createFilePieces(inputFile, chunkSize, pieces);

    if (pieces.length < 2) {
        alert('File is too small for chunking.');
        return;
    }

    // Generate a unique ID for the file and a hash for all chunks
    const fileId = "a99060ec2fc0dda33";
    const fileHash = "22551854";

    pieces.forEach(item => {
        const reader = new FileReader();
        reader.readAsDataURL(item.piece);
        reader.onloadend = () => {
            const uploadPayload = {
                id: fileId,
                name: inputFile.name,
                fileUniqueHash: fileHash,
                start: item.start,
                piece: reader.result,
                piecesCount: item.piecesCount
            };

            // Use fetch to send the payload to the API
            fetch('https://demo.atropim.com/api/File', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization-Token': '***************************' // Replace with your actual token
                },
                body: JSON.stringify(uploadPayload)
            })
                .then(response => response.json())
                .then(data => {
                    console.log('Chunk uploaded successfully:', data);
                })
                .catch(error => {
                    console.error('Error uploading chunk:', error);
                });
        };
    });
}
```
### Creating Public URLs for Files

To create a publicly accessible URL for a file, you must create a **Sharing** entity. This entity acts as a key that links a specific file to a public download or view URL.

-----

#### Step-by-Step Guide

1.  **Identify the file**: You need the unique identifier (`fileId`) of the file you wish to share.

2.  **Create a Sharing entity**: Make a `POST` request to the `/api/Sharing` endpoint. The request body must be in JSON format and contain at least the `fileId` of the file. You can also provide a `name` to help identify the sharing record.

    **Example Request:**

    ```http
    POST /api/Sharing HTTP/1.1
    Host: your-instance.com
    Authorization-Token: ***************
    Content-Type: application/json
    Content-Length: 47

    {
        "name": "My public version",
        "fileId": "a62860ec2fc0ddete"
    }
    ```

3.  **Process the response**: If the request is successful, the API will return a JSON object containing details about the newly created **Sharing** entity. The publicly accessible URL is provided in the **`link`** property of this response.

    **Example Response:**

    ```json
    {
        "id": "a01k5c597f5ef1s75edb6gkcbh0",
        "name": "My public version",
        "deleted": false,
        "active": true,
        "available": true,
        "link": "https://your-instance.com/sharings/a01k5c597f5ef1s75edb6gkcbh0.txt",
        "viewLink": "https://your-instance.com/sharings/a01k5c597f5ef1s75edb6gkcbh0.txt?view=1",
        "fileId": "a62860ec2fc0ddete",
        "fileName": "Test1.txt",
        "filePathsData": {
            "download": "https://your-instance.com/downloads/a62860ec2fc0ddete.",
            "thumbnails": {
                "small": null,
                "medium": null,
                "large": null
            }
        },
        "createdAt": "2025-09-17 15:39:24",
        "modifiedAt": "2025-09-17 15:39:24",
        "createdById": "1",
        "modifiedById": "1",
        "modifiedByName": "Admin"
    }
    ```

The URL returned in the **`link`** field is now public. You can share it for direct downloads or use it to embed files on external websites.

For more advanced options and parameters, such as controlling link expiry or setting a password, please refer to the [Sharing API documentation](https://demo.atropim.com/apidocs/?atroq=apidocs#/Sharing/createSharingItem).

### Working with Attributes

In our system, **attributes** are optional virtual fields that extend core entity fields. Attributes can be enabled or disabled per entity type via the `Has Attributes` setting.

There are two ways to work with attributes via the REST API:

1. **Simple endpoints** (recommended) — dedicated endpoints per entity record, fully described in the OpenAPI schema
2. **Flattened format** (advanced) — attributes embedded directly into the entity record payload

By default, attributes are **not included** in entity responses. You must either use the simple endpoints or the `Flatten-Attributes` header to access them.

---

#### Simple Endpoints (Recommended)

The recommended way to work with attributes is via dedicated endpoints. These are available for any entity with attributes enabled and are fully described in the [OpenAPI schema](https://demo.atropim.com/apidocs).

##### Get Attribute Values

Returns all attribute values assigned to a record.

```http
GET /api/Product/{id}/attributeValues HTTP/1.1
Host: demo.atropim.com
Authorization-Token: ***************
```

Response:
```json
[
  {
    "attributeId": "a01...",
    "type": "text",
    "value": "Some text",
    "required": false,
    "visible": true,
    "readOnly": false,
    "protected": false
  },
  {
    "attributeId": "a02...",
    "type": "float",
    "value": 100,
    "valueUnitId": "usd",
    "valueUnitName": "USD",
    "required": true,
    "visible": true,
    "readOnly": false,
    "protected": false
  }
]
```

##### Add Attributes

Assigns one or more attributes to a record without setting values.

```http
POST /api/Product/{id}/addAttributes HTTP/1.1
Host: demo.atropim.com
Authorization-Token: ***************
Content-Type: application/json

{
  "attributeIds": ["a01...", "a02..."]
}
```

Response:
```json
true
```

##### Upsert Attribute Values

Creates or updates attribute values for a record. Only the attributes specified are affected; all others remain unchanged.

```http
POST /api/Product/{id}/upsertAttributeValues HTTP/1.1
Host: demo.atropim.com
Authorization-Token: ***************
Content-Type: application/json

[
  {
    "attributeId": "a01...",
    "value": "Updated text"
  },
  {
    "attributeId": "a02...",
    "value": 150,
    "valueUnitId": "usd"
  }
]
```

Response:
```json
true
```

##### Delete Attribute Values

Removes one or more attribute assignments from a record.

```http
DELETE /api/Product/{id}/attributeValues HTTP/1.1
Host: demo.atropim.com
Authorization-Token: ***************
Content-Type: application/json

{
  "attributeIds": ["a01...", "a02..."]
}
```

Response:
```json
{
  "count": 2,
  "errors": []
}
```

---

#### Flattened Format (Advanced)

For advanced use cases, attributes can be retrieved and updated as part of the entity record payload using the `Flatten-Attributes: true` header. This approach is more complex but offers greater flexibility for bulk operations and integrations that require full entity payloads.

> This approach is not reflected in the OpenAPI schema. Use the simple endpoints above unless you have a specific reason to use this format.

##### Attribute Basics

Each attribute is stored with a unique internal ID and may optionally have a **code**. When a code is defined, it is used as the key name for the virtual field in API responses. If no code is provided, the attribute ID is used instead.

> Define a `code` for each attribute to produce more readable, developer-friendly API responses.

An attribute may generate **multiple virtual fields** depending on its type. For example, a price attribute with a unit might result in a float field (e.g., `priceAmount`), a unit ID field (e.g., `priceAmountUnitId`), a unit name field, and additional supporting fields.

##### Reading with Flattened Format

Use the `Flatten-Attributes: true` header to flatten all attribute values directly into the entity object as virtual fields.

```http
GET /api/Product/a01jz56xg5xe09abkmfg4dr0kvj HTTP/1.1
Host: demo.atropim.com
Authorization-Token: ***************
Flatten-Attributes: true
```

Response:
```json
{
  "id": "a01jz56xg5xe09abkmfg4dr0kvj",
  "name": "Example Product",
  "headlineText": "Some text",
  "priceAttr": 100,
  "priceAttrUnitId": "usd",
  "priceAttrUnitName": "USD",
  "attributesDefs": {
    "headlineText": {
      "attributeId": "a01...",
      "label": "Main headline text",
      "type": "text"
    },
    "priceAttr": {
      "attributeId": "a02...",
      "type": "float",
      "measureId": "currency"
    }
  }
}
```

##### `attributesDefs` Field

When using the flattened format, the response includes an `attributesDefs` object describing each virtual field. Keys correspond to virtual field names; values provide metadata such as `type`, `attributeId`, `label`, and more. In collection responses the metadata is minimized for performance; in single-record responses full metadata is returned.

##### Querying Attributes in Collections

Use `allAttributes=true` to include all attributes for each item:

```http
GET /api/Product?select=name&maxSize=1&allAttributes=true HTTP/1.1
Host: demo.atropim.com
Authorization-Token: ***************
Flatten-Attributes: true
```

Use the `attributes` parameter to load only specific attributes by ID:

```http
GET /api/Product?select=name&attributes=a01jzmpz4cze5da1gs5gq2shr4j,a01jz83qe81ebwsq2rfhpfvc2sp HTTP/1.1
Host: demo.atropim.com
Authorization-Token: ***************
Flatten-Attributes: true
```

##### Updating with Flattened Format

Update attributes like regular fields in a PATCH request:

```http
PATCH /api/Product/a01jz56xg5xe09abkmfg4dr0kvj HTTP/1.1
Host: demo.atropim.com
Authorization-Token: ***************
Flatten-Attributes: true

{
  "headlineText": "Updated text",
  "priceAttr": 150
}
```

##### Adding Attributes with Flattened Format

Use `__attributes` with an array of attribute IDs to assign attributes to a record:

```http
PATCH /api/Product/a01jz56xg5xe09abkmfg4dr0kvj HTTP/1.1
Host: demo.atropim.com
Authorization-Token: ***************
Flatten-Attributes: true

{
  "__attributes": ["a01...", "a02..."]
}
```

##### Removing Attributes with Flattened Format

Use `__attributesToRemove` with an array of attribute IDs:

```http
PATCH /api/Product/a01jz56xg5xe09abkmfg4dr0kvj HTTP/1.1
Host: demo.atropim.com
Authorization-Token: ***************
Flatten-Attributes: true

{
  "headlineText": "Updated text",
  "__attributesToRemove": ["a02...", "a03..."]
}
```

---

#### Approach Comparison

| Action | Simple Endpoints | Flattened Format |
|---|---|---|
| Read attributes | `GET /{id}/attributeValues` | `Flatten-Attributes: true` on GET |
| Add attributes | `POST /{id}/addAttributes` | Add IDs to `__attributes` in PATCH |
| Update attribute | `POST /{id}/upsertAttributeValues` | Include field with new value in PATCH |
| Remove attribute | `DELETE /{id}/attributeValues` | Add code/ID to `__attributesToRemove` in PATCH |
| Visible in OpenAPI | ✅ Yes | ❌ No |

---

#### Summary

- Attributes are optional virtual fields, enabled per entity via the `Has Attributes` setting.
- By default, attributes are **not included** in entity responses.
- Use the **simple endpoints** (`/attributeValues`, `/addAttributes`, `/upsertAttributeValues`) for the recommended integration approach — these are fully described in the OpenAPI schema.
- Use the **flattened format** only for advanced use cases — this approach is not in the OpenAPI schema and is documented here only.
- One attribute may produce multiple virtual fields (value, unit, etc.).
- Use attribute codes for clean, readable keys in your payloads.
- Attributes not mentioned in an update request remain unchanged.
- To add attributes in flattened format, use `__attributes` with an array of attribute IDs.
- To remove attributes in flattened format, use `__attributesToRemove` with an array of attribute IDs.


## The `With-Meta` Header

The `With-Meta` request header instructs the API to enrich every entity in the response with a `_meta` object. This object provides contextual information that would otherwise require additional API calls — such as the current user's permissions on the record, full option data for enum fields, and audit delegation details.

The header is accepted on the following endpoints:

- `GET /{Entity}` — list of records
- `GET /{Entity}/{id}` — single record
- `PUT /{Entity}/{id}` — update (in the response)
- `GET /{Entity}/{id}/{link}` — linked records

**Accepted values:** `true` or `1`.

```http
GET /api/Product HTTP/1.1
Host: demo.atropim.com
Authorization-Token: ***************
With-Meta: true
```

---

### The `_meta` Object Structure

When `With-Meta: true` is sent, each entity in the response includes a `_meta` property. The object is organized into named categories.

> **Note:** The categories described below reflect the current state of the system. As AtroCore evolves, new categories may be added to `_meta` by the core or by installed modules. Treat this as a living structure — do not assume the list below is exhaustive, and design your client to gracefully ignore unknown categories.

```json
{
  "id": "a01jz56xg5xe09abkmfg4dr0kvj",
  "name": "Example Product",
  "_meta": {
    "permissions": {
      "edit": true,
      "delete": false,
      "stream": true
    },
    "options": {
      "status": {
        "id": "a01...",
        "code": "active",
        "name": "Active",
        "color": "#3bbb5f"
      }
    }
  }
}
```

---

### `_meta.permissions`

Contains ACL flags for the **current authenticated user** on this specific record. Clients can use these flags to conditionally show or hide action buttons without making additional permission-check requests.

| Key | Type | Description                                                                                                                      |
|-----|------|----------------------------------------------------------------------------------------------------------------------------------|
| `edit` | boolean | Whether the current user can edit this record                                                                                    |
| `delete` | boolean | Whether the current user can delete this record                                                                                  |
| `stream` | boolean | Whether the current user can read/edit the stream                                                                                |
| `unlink` | boolean | Whether the current user can unlink this record *(only in linked-record responses)*                                              |

**Example — list response with permissions:**

```http
GET /api/Product?select=id,name&maxSize=2 HTTP/1.1
Host: demo.atropim.com
Authorization-Token: ***************
With-Meta: true
```

```json
{
  "total": 60,
  "list": [
    {
      "id": "a01...",
      "name": "Product A",
      "_meta": {
        "permissions": {
          "edit": true,
          "delete": true,
          "stream": true
        }
      }
    },
    {
      "id": "a02...",
      "name": "Product B",
      "_meta": {
        "permissions": {
          "edit": false,
          "delete": false,
          "stream": true
        }
      }
    }
  ]
}
```

---

### `_meta.options`

Populated for `Link` and `Multiple link` fields whose related entity is **`List Option`**. Instead of returning only the ID(s), the full option object(s) are embedded in `_meta.options`, keyed by field name.

This eliminates the need to fetch option data separately.

```json
{
  "id": "a01...",
  "name": "Example Product",
  "_meta": {
    "options": {
      "status": {
        "id": "a01k5...",
        "code": "active",
        "name": "Active",
        "color": "#3bbb5f",
        "sortOrder": 1
      },
      "tags": [
        { "id": "b01...", "code": "new", "name": "New", "color": "#ff9900" },
        { "id": "b02...", "code": "sale", "name": "Sale", "color": "#e53935" }
      ]
    }
  }
}
```

- For a **single-link** field, the value is a single option object.
- For a **multi-link** field, the value is an array of option objects.

---

### `_meta.audit`

Populated only when the user who created or last modified the record was acting under a **delegated session** (i.e., a user was acting on behalf of another). In standard cases this category is absent.

When present, it provides the real actor and delegator for `createdBy` and/or `modifiedBy`:

```json
{
  "id": "a01...",
  "name": "Example Product",
  "_meta": {
    "audit": {
      "modifiedBy": {
        "actor": {
          "id": "usr_001",
          "name": "Alice",
          "isSystem": false
        },
        "delegator": {
          "id": "usr_002",
          "name": "Bob",
          "isSystem": false
        }
      }
    }
  }
}
```

> In this example, Bob initiated the action and Alice performed it on his behalf.

---

### Summary

The table below lists the currently known `_meta` categories. This list will grow as the system evolves — modules and future core updates may introduce additional categories.

| Category | When populated                                                            | Purpose |
|----------|---------------------------------------------------------------------------|---------|
| `permissions` | Always (when header is sent)                                              | ACL flags for edit, delete, stream, unlink, etc. |
| `options` | When record has `Link` / `Multiple link` fields pointing to `List Option` | Full option objects (id, code, name, color) |
| `audit` | When a delegated session was involved                                     | Real actor and delegator for createdBy/modifiedBy |
| *(future)* | Varies                                                                    | Additional categories may be added by modules or core updates |

---

## The `language` Header

When multilingual support is enabled on an AtroCore instance, the API can return data in a specific language using the `language` request header. This header is available on the following endpoints:

- `GET /{Entity}` — list of records
- `GET /{Entity}/{id}` — single record
- `GET /{Entity}/{id}/{link}` — linked records
- `GET /{Entity}/{id}/attributeValues` — attribute values

**Accepted values:** Any language code configured in the instance (e.g., `de_DE`, `fr_FR`, `es_ES`). The available values are listed in the OpenAPI schema as an enum for this parameter.

> This header is only present in the OpenAPI schema when multilingual support is active on the instance.

### Behavior

When the `language` header is present:

1. All **language-specific variant fields** are removed from the response (e.g., `nameDeDE`, `nameFrFR`, `descriptionEsEs`).
2. The **main field** (e.g., `name`, `description`) contains the value for the requested language instead of the default language value.

This simplifies client-side handling — instead of selecting a specific language field from the response, the client always reads the main field and controls the language via the header.

**Example — request data in German:**

```http
GET /api/Product/a01jz56xg5xe09abkmfg4dr0kvj HTTP/1.1
Host: demo.atropim.com
Authorization-Token: ***************
language: de_DE
```

**Without the header**, a multilingual response might look like:

```json
{
  "id": "a01jz56xg5xe09abkmfg4dr0kvj",
  "name": "Example Product",
  "nameDeDE": "Beispielprodukt",
  "nameFrFR": "Exemple de produit"
}
```

**With `language: de_DE`**, the response becomes:

```json
{
  "id": "a01jz56xg5xe09abkmfg4dr0kvj",
  "name": "Beispielprodukt"
}
```

The same behavior applies to multilingual **attributes** — language-specific value variants are removed and the main `value` field contains the value for the requested language.

---

## Undocumented or Partially Documented Endpoints

While we strive to provide comprehensive and up-to-date documentation for all available REST API endpoints, there may still be actions or endpoints that are either undocumented or documented only partially. This is an active area of improvement, and we continuously work to close these gaps and ensure complete coverage.

Our application is fully API-centric—every interaction within the system is ultimately driven by API calls. Therefore, if you notice that a particular action available through the user interface is not clearly reflected in the API documentation, you may be able to identify the underlying request yourself.

To investigate:
1. Open your browser's Developer Tools (`F12` or `Ctrl+Shift+I`).
2. Navigate to the **Network** tab.
3. Perform the desired action within the UI.
4. Observe which HTTP request(s) are triggered by the frontend.
5. Analyze the endpoint, method (GET, POST, etc.), payload, and response to understand the API interaction.

> The system sends a high volume of requests, including background polling and realtime communication. For example:
- `/data/publicData.json` is a regularly triggered request essential for frontend-backend synchronization.
- `/listening/entity/Product/{id}.json` facilitates realtime updates.

Because of this ongoing activity, the Network tab may include many unrelated requests. It’s important to correctly identify the one you're interested in.

Use the filtering tools in Developer Tools to narrow your focus:
- Filter by HTTP method (e.g., only POST requests)
- **Specifically, we recommend filtering by `/api/` to isolate requests that interact with the REST API**—this helps exclude internal or non-REST traffic like configuration files and realtime listeners.
- Sort by timestamp or response status to locate requests triggered by direct UI actions

This technique enables developers to reverse-engineer undocumented API interactions and gain a deeper understanding of system behavior. If you discover helpful endpoints through this process, we encourage you to share them—your feedback helps improve our documentation and benefits the entire developer community.

Thank you for supporting a transparent and developer-first ecosystem.
