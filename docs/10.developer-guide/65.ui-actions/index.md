---
title: UI Actions
---

## Overview

AtroCore lets you expose backend actions directly in the UI — on list views, detail pages, and relationship panels — without writing any frontend JavaScript. You define the action entirely in the entity's `clientDefs` metadata file and a corresponding PHP handler. The frontend picks it up automatically.

This is useful for triggering business logic (approve, reject, export, sync, publish, etc.) with a button or menu item directly tied to a record.

---

## How It Works

Each action entry in `clientDefs` maps an action name to:
- a **backend URL** that receives a request when the action is triggered
- optional **UI behavior** (HTTP method, confirmation dialog, icon, display order, etc.)

The action label and confirmation messages come from the entity's i18n translation files.

Action visibility per record is controlled by the backend via the `_meta.permissions` map returned with each record. If `_meta.permissions.myAction` is not truthy, the action is hidden for that record.

---

## Action Placement

### `listActions` — row actions in list view

Adds items to the per-record action menu (the three-dot menu on each row in the list).

Use `{{fieldName}}` placeholders in the URL — they are replaced with the record's field values at execution time.

```json
// app/Atro/Resources/metadata/clientDefs/MyEntity.json
{
  "listActions": {
    "publish": {
      "url": "MyEntity/{{id}}/publish",
      "method": "POST",
      "confirm": true,
      "refresh": true,
      "iconClass": "ph ph-paper-plane-tilt",
      "acl": "edit",
      "sortOrder": 10
    }
  }
}
```

The request body always contains `{ "action": "publish", "scope": "MyEntity", "id": "<recordId>" }`.

---

### `detailActions` — actions on the detail page

Adds items to the action menu (or as a standalone button) on the record detail page.

The `exit` parameter navigates the user back to the list page after the action succeeds — useful for destructive actions like archive or delete that render the current detail page meaningless.

```json
{
  "detailActions": {
    "publish": {
      "url": "MyEntity/{{id}}/publish",
      "method": "POST",
      "confirm": true,
      "refresh": true,
      "iconClass": "ph ph-paper-plane-tilt",
      "sortOrder": 10
    },
    "archive": {
      "url": "MyEntity/{{id}}/archive",
      "method": "POST",
      "confirm": true,
      "exit": true,
      "singleButton": true,
      "style": "danger",
      "sortOrder": 20
    }
  }
}
```

When `exit: true` is set, the user is redirected to the list view after the action completes. When `refresh: true` is set instead, the record is re-fetched in place. Only one of the two should be used.

The request body always contains `{ "action": "publish", "scope": "MyEntity", "id": "<recordId>" }`.

---

### `relationshipPanels.[relationName].actions` — row actions inside a relationship panel

Adds per-record actions inside a relationship panel on the detail page. Also supports disabling built-in actions (e.g. `quickEdit`, `quickView`) for a specific panel.

```json
{
  "relationshipPanels": {
    "orderLines": {
      "actions": {
        "quickEdit": {
          "disabled": true
        },
        "ship": {
          "url": "OrderLine/{{id}}/ship",
          "method": "POST",
          "confirm": true,
          "refresh": true,
          "iconClass": "ph ph-truck",
          "sortOrder": 10
        }
      }
    }
  }
}
```

---

### `massActions` — toolbar mass actions

Mass actions are declared under their own top-level `massActions` key. Each entry points to its own bulk endpoint. The confirmation dialog is shown automatically when a `massActionConfirmMessages` translation key exists for the action name — no explicit `confirm: true` is needed.

```json
{
  "massActions": {
    "reject": {
      "url": "MyEntity/massReject",
      "method": "POST",
      "refresh": true,
      "iconClass": "ph ph-x",
      "sortOrder": 20
    },
    "move": {
      "url": "MyEntity/massMove",
      "method": "PATCH",
      "refresh": true,
      "iconClass": "ph ph-arrow-right",
      "sortOrder": 40,
      "modalSelectEntity": "Folder",
      "modalSelectResultParam": "targetFolderId"
    },
    "update":         { "disabled": true },
    "addRelation":    { "disabled": true },
    "removeRelation": { "disabled": true }
  }
}
```

The request body contains `{ "where": [...], "entityType": "MyEntity", "idList": [...] }`. If a modal selection was made, the chosen value is merged in under `modalSelectResultParam`.

Key points:

- Built-in mass actions (`update`, `addRelation`, `removeRelation`, etc.) can be turned off by declaring them with `"disabled": true`.
- Actions that need a target picked at execution time use `modalSelectEntity` (scope of the selection dialog) together with `modalSelectResultParam` (the key under which the chosen ID is posted).
- The confirmation and success messages come from the entity's i18n file (see [Translations](#translations)).

---

## Action Properties Reference

| Property | Type | Applies to | Description |
|---|---|---|---|
| `url` | string | all | Backend endpoint URL. Use `{{fieldName}}` placeholders — they are replaced with the record's field values. |
| `method` | string | all | HTTP verb. Default: `POST`. Use `DELETE`, `PATCH`, etc. for REST-aligned actions. |
| `confirm` | bool | listActions, detailActions, relationshipPanels | Show a confirmation dialog before executing. The message comes from the `actionConfirms` i18n group. Default: `false`. For `massActions`, confirmation is triggered automatically by the presence of a `massActionConfirmMessages` entry. |
| `refresh` | bool | all | Re-fetch the record or collection after the action completes. Default: `false`. |
| `exit` | bool | detailActions only | Navigate back to the list page after the action completes. Use for actions that make the current record inaccessible (archive, delete). Takes precedence over `refresh`. |
| `disabled` | bool | all | Hide the action from the UI entirely. Used to suppress built-in actions — e.g. `"update": { "disabled": true }` under `massActions` removes built-in Mass Update. |
| `iconClass` | string | listActions, massActions, detailActions, relationshipPanels | Phosphor icon class shown next to the label. Example: `"ph ph-check"`. |
| `acl` | string | listActions, massActions | ACL permission required to show the action (`"edit"`, `"delete"`). |
| `sortOrder` | int | all | Controls position in the menu. Lower = higher up. Built-in actions start at 110. |
| `singleButton` | bool | detailActions only | Render as a standalone button rather than a dropdown item. |
| `style` | string | detailActions only | Button style when `singleButton` is true. Values: `"primary"`, `"danger"`, `"default"`. |
| `modalSelectEntity` | string | massActions, listActions | Entity scope for a selection modal opened before the request is sent. |
| `modalSelectResultParam` | string | massActions, listActions | Name of the field under which the chosen record's ID is sent. Pairs with `modalSelectEntity`. |
| `modalSelectWhere` | array | massActions, listActions | Additional `where` filters pre-applied to the selection modal. Supports `{{fieldName}}` placeholders. |
| `modalSelectMultiple` | bool | massActions, listActions | Allow selecting multiple records in the modal. The value is posted as an array. |

---

## Backend Handler

Register a handler for the action URL. See the [Handlers guide](../12.handlers/index.md) for the full pattern.

### Single-record action (listActions / detailActions)

The `{{id}}` in the URL is resolved to the record ID before the request is sent. The path parameter `{id}` is available via `$request->getAttribute('id')`.

```php
// app/Atro/Handlers/MyEntity/MyEntityPublishHandler.php

#[Route(
    path: '/MyEntity/{id}/publish',
    methods: ['POST'],
    summary: 'Publish a MyEntity record',
    tag: 'MyEntity',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'ID of the record to publish.',
            'schema'      => ['type' => 'string'],
        ],
    ],
    responses: [
        200 => [
            'description' => 'true if published successfully.',
            'content'     => ['application/json' => ['schema' => ['type' => 'boolean']]],
        ],
        403 => ['description' => 'Insufficient permissions.'],
        404 => ['description' => 'Record not found.'],
    ],
)]
class MyEntityPublishHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id            = (string)$request->getAttribute('id');
        $recordService = $this->getRecordService('MyEntity');

        $entity = $recordService->getEntity($id);
        if (empty($entity)) {
            throw new NotFound();
        }

        return new BoolResponse($recordService->publish($entity));
    }
}
```

### Mass action endpoint

Each `massActions` entry points at its own bulk endpoint. The request body contains a `where` clause describing the selection.

```php
#[Route(
    path: '/MyEntity/massReject',
    methods: ['POST'],
    summary: 'Mass-reject MyEntity records',
    tag: 'MyEntity',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'where' => [
                            'type'        => 'array',
                            'description' => 'Filter criteria selecting the records to reject.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Rejection result.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'count'  => ['type' => 'integer', 'description' => 'Number of records rejected.'],
                            'sync'   => ['type' => 'boolean', 'description' => 'true if run synchronously.'],
                            'errors' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                    ],
                ],
            ],
        ],
        403 => ['description' => 'Insufficient permissions.'],
    ],
)]
class MyEntityMassRejectHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('MyEntity', 'edit')) {
            throw new Forbidden();
        }

        $data   = $this->getRequestBody($request);
        $params = [];

        if (!empty($data->where) && is_array($data->where)) {
            $params['where'] = $data->where;
        } elseif (!empty($data->idList) && is_array($data->idList)) {
            $params['ids'] = $data->idList;
        } else {
            throw new BadRequest('A where filter or idList is required.');
        }

        return new JsonResponse($this->getRecordService('MyEntity')->reject($params));
    }
}
```

### Service method for mass actions

The handler delegates to a service method that calls `executeMassAction()` — a helper in `Atro\Services\Record` that runs synchronously for small sets and dispatches background jobs for large ones.

```php
// app/Atro/Services/MyEntity.php

public function reject(array $params): array
{
    $params['action']             = 'reject';
    $params['maxCountWithoutJob'] = $this->getConfig()->get('massUpdateMaxCountWithoutJob', 200);
    $params['maxChunkSize']       = $this->getConfig()->get('massUpdateMaxChunkSize', 3000);
    $params['minChunkSize']       = $this->getConfig()->get('massUpdateMinChunkSize', 400);
    $params['singleActionMethod'] = 'rejectItem';

    [$count, $errors, $sync] = $this->executeMassAction($params, function (string $id) {
        $this->rejectItem($id);
    });

    return ['count' => $count, 'sync' => $sync, 'errors' => $errors];
}

public function rejectItem(string $id): bool
{
    $entity = $this->getEntity($id);
    if (empty($entity)) {
        return false;
    }

    // ... per-record business logic ...

    $this->getEntityManager()->saveEntity($entity);
    return true;
}
```

There are two execution paths — both must be covered:

**Synchronous** (total ≤ `maxCountWithoutJob`): `executeMassAction` runs the closure inline, one ID at a time.

**Asynchronous** (total > `maxCountWithoutJob`): `executeMassAction` creates a `MassActionCreator` job that splits the IDs into chunks and dispatches one `UniversalMassAction` background job per chunk. That job calls `$service->{singleActionMethod}($id)` for each ID in its chunk.

Both the closure and `singleActionMethod` are required — they serve different execution paths but ultimately call the same per-record method.

Return `['count' => $n]` so the success message can use the `{count}` placeholder.

---

## Controlling Visibility per Record

Actions are shown only when the backend sets the action name to `true` in `_meta.permissions`. Override `putAclMeta()` in the entity's **service** class:

```php
// app/Atro/Services/MyEntity.php

public function putAclMeta(\Espo\ORM\Entity $entity): void
{
    parent::putAclMeta($entity); // always call parent

    $isPending = $entity->get('status') === 'pending';

    $entity->setMetaPermission('publish', $isPending && $this->getAcl()->check($entity, 'edit'));
    $entity->setMetaPermission('reject',  $isPending && $this->getAcl()->check($entity, 'edit'));
}
```

For relationship panel actions, override `putAclMetaForLink()` instead:

```php
public function putAclMetaForLink(\Espo\ORM\Entity $entityFrom, string $link, \Espo\ORM\Entity $entity): void
{
    parent::putAclMetaForLink($entityFrom, $link, $entity);

    $entity->setMetaPermission('ship', $this->getAcl()->check($entity, 'edit'));
}
```

---

## Translations

```json
// app/Atro/Resources/i18n/en_US/MyEntity.json
{
  "actions": {
    "publish": "Publish",
    "reject": "Reject",
    "archive": "Archive"
  },
  "actionConfirms": {
    "publish": "Are you sure you want to publish this record?",
    "archive": "Are you sure you want to archive this record? It will no longer be visible in the list."
  },
  "massActions": {
    "reject": "Reject Selected"
  },
  "massActionConfirmMessages": {
    "reject": "Are you sure you want to reject the selected records? This action cannot be undone."
  },
  "massActionSuccessMessages": {
    "reject": "{count} record(s) rejected successfully."
  }
}
```

| Key group | Used for | Trigger |
|---|---|---|
| `actions` | Label shown in the UI for list, detail, and relationship panel actions | Always |
| `actionConfirms` | Confirmation dialog message for `listActions`, `detailActions`, and relationship panel actions | `"confirm": true` in the action definition |
| `massActions` | Label shown in the toolbar mass actions menu | Always |
| `massActionConfirmMessages` | Confirmation dialog message for mass actions | Shown automatically when the key is present |
| `massActionSuccessMessages` | Success notification after a mass action completes. Supports `{count}` placeholder | Shown automatically when the key is present |

---

## Real-World Example

The `ClusterItem` entity:

```json
// app/Atro/Resources/metadata/clientDefs/ClusterItem.json
{
  "listActions": {
    "quickEdit": { "disabled": true },
    "confirm": {
      "url": "ClusterItem/{{id}}/confirm",
      "method": "POST",
      "confirm": false,
      "refresh": true,
      "iconClass": "ph ph-check",
      "sortOrder": 10
    },
    "reject": {
      "url": "ClusterItem/{{id}}/reject",
      "method": "POST",
      "refresh": true,
      "iconClass": "ph ph-x",
      "sortOrder": 20
    },
    "unmerge": {
      "url": "ClusterItem/{{id}}/unmerge",
      "method": "POST",
      "refresh": true,
      "iconClass": "ph ph-arrows-split",
      "sortOrder": 30
    },
    "move": {
      "url": "ClusterItem/{{id}}/move",
      "method": "PATCH",
      "refresh": true,
      "iconClass": "ph ph-arrow-right",
      "sortOrder": 40,
      "modalSelectEntity": "Cluster",
      "modalSelectResultParam": "targetClusterId"
    }
  },

  "massActions": {
    "reject": {
      "url": "ClusterItem/massReject",
      "method": "POST",
      "refresh": true,
      "iconClass": "ph ph-x",
      "sortOrder": 20
    },
    "unmerge": {
      "url": "ClusterItem/massUnmerge",
      "method": "POST",
      "refresh": true,
      "iconClass": "ph ph-arrows-split",
      "sortOrder": 30
    },
    "move": {
      "url": "ClusterItem/massMove",
      "method": "POST",
      "refresh": true,
      "iconClass": "ph ph-arrow-right",
      "sortOrder": 40,
      "modalSelectEntity": "Cluster",
      "modalSelectResultParam": "targetClusterId"
    },

    "update":         { "disabled": true },
    "addRelation":    { "disabled": true },
    "removeRelation": { "disabled": true }
  }
}
```

This configuration:

- Disables the built-in `quickEdit` row action.
- Adds four custom row actions — **Confirm**, **Reject**, **Unmerge**, and **Move** — each using `{{id}}` in the URL. **Move** opens a `Cluster` picker first and posts the chosen ID under `targetClusterId`.
- Declares three toolbar mass actions pointing at dedicated bulk endpoints.
- Suppresses the built-in **Mass Update**, **Add Relation**, and **Remove Relation** entries.
