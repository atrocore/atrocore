---
title: UI Actions
---

## Overview

AtroCore allows you to expose backend actions directly in the UI — on list views, detail pages, and relationship panels — without writing any frontend JavaScript. You define the action entirely in the entity's `clientDefs` metadata file and a corresponding PHP controller method. The frontend picks it up automatically.

This is useful for triggering business logic (approve, reject, export, sync, etc.) with a button or menu item directly tied to a record.

---

## How It Works

Each action entry in `clientDefs` maps an action name to:
- a **backend URL** that receives a POST request when the action is triggered
- optional **UI behavior** (confirmation dialog, icon, display order, etc.)

The action label and confirmation/success messages come from the entity's i18n translation files.

Action visibility per record is controlled by the backend via the `_meta.permissions` map returned with each record. If `_meta.permissions.myAction` is not truthy, the action is hidden for that record.

---

## Action Placement

### `listActions` — row actions in list view

Adds items to the per-record action menu (the three-dot menu on each row in the list).

```json
// app/Atro/Resources/metadata/clientDefs/MyEntity.json
{
  "listActions": {
    "approve": {
      "url": "MyEntity/action/approve",
      "confirm": true,
      "refresh": true,
      "iconClass": "ph ph-check",
      "sortOrder": 10
    }
  }
}
```

---

### `detailActions` — actions on the detail page

Adds items to the action menu (or as a standalone button) on the record detail page.

```json
{
  "detailActions": {
    "publish": {
      "url": "MyEntity/action/publish",
      "confirm": false,
      "refresh": true,
      "sortOrder": 10
    },
    "archive": {
      "url": "MyEntity/action/archive",
      "confirm": true,
      "singleButton": true,
      "style": "primary",
      "sortOrder": 20
    }
  }
}
```

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
          "url": "OrderLine/action/ship",
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

### `listActions` as `massActions`

Setting `"massAction": true` on a `listAction` automatically makes it available as a mass action in the list view. The same URL is called, but the payload contains either an `idList` array (for selected records) or a `where` clause (when "select all" is used).

```json
{
  "listActions": {
    "reject": {
      "url": "MyEntity/action/reject",
      "massAction": true,
      "refresh": true,
      "iconClass": "ph ph-x",
      "sortOrder": 20
    }
  }
}
```

When used as a mass action, the frontend automatically shows a confirmation before calling the endpoint, and displays a success message afterward. Both messages are sourced from the entity's i18n file (see [Translations](#translations) below).

---

## Action Properties Reference

| Property | Type | Applies to | Description |
|---|---|---|---|
| `url` | string | all | Backend endpoint. Called via POST. Format: `EntityName/action/methodName`. |
| `confirm` | bool | all | Show a confirmation dialog before executing. Default: `false`. |
| `refresh` | bool | all | Refresh the record/collection after the action completes. Default: `false`. |
| `disabled` | bool | all | Hide the action from the UI entirely. Useful to suppress built-in actions. |
| `iconClass` | string | listActions, relationshipPanels | Phosphor icon class shown next to the label. Example: `"ph ph-check"`. |
| `sortOrder` | int | all | Controls position in the menu. Lower = higher up. Built-in actions start at 110. |
| `massAction` | bool | listActions only | Also expose this action as a mass action. |
| `singleButton` | bool | detailActions only | Render as a standalone button rather than a dropdown item. |
| `style` | string | detailActions only | Button style when `singleButton` is true. Values: `"primary"`, `"default"`, `"secondary"`. |

---

## Backend Endpoint

The action calls `POST` to the specified `url`. Implement it as a method on the entity's controller using the signature `actionXxx($params, $data, $request)`, where `$data` is a `stdClass` object containing the POST body.

### Single record action

When triggered from a row or detail page, `$data` contains:
- `$data->id` — the ID of the record

```php
// app/Atro/Controllers/MyEntity.php

public function actionApprove($params, $data, $request)
{
    if (!$request->isPost()) {
        throw new \Atro\Core\Exceptions\BadRequest();
    }

    if (!property_exists($data, 'id') || empty($data->id)) {
        throw new \Atro\Core\Exceptions\BadRequest('ID is required.');
    }

    if (!$this->getAcl()->check('MyEntity', 'edit')) {
        throw new \Atro\Core\Exceptions\Forbidden();
    }

    $entity = $this->getRecordService()->getEntity((string)$data->id);
    if (empty($entity)) {
        throw new \Atro\Core\Exceptions\NotFound();
    }

    return $this->getRecordService()->approve($entity);
}
```

### Action supporting both single record and mass action

When `massAction: true` is set on a `listAction`, the same endpoint is called for both single-record and bulk execution. The `$data` object will contain either:
- `$data->id` — single record (triggered from row action)
- `$data->idList` — array of selected record IDs (mass action with specific selection)
- `$data->where` — filter clause (mass action with "select all" across pages)

Handle all three cases in one method:

```php
public function actionReject($params, $data, $request)
{
    if (!$request->isPost()) {
        throw new \Atro\Core\Exceptions\BadRequest();
    }

    if (!$this->getAcl()->check('MyEntity', 'edit')) {
        throw new \Atro\Core\Exceptions\Forbidden();
    }

    $actionParams = [];

    if (property_exists($data, 'where')) {
        $actionParams['where'] = json_decode(json_encode($data->where), true);
    }

    if (property_exists($data, 'idList')) {
        $actionParams['ids'] = $data->idList;
    }

    if (empty($actionParams) && !empty($data->id)) {
        $actionParams['ids'] = [$data->id];
    }

    if (empty($actionParams)) {
        throw new \Atro\Core\Exceptions\BadRequest('Provide an id, idList, or where filter.');
    }

    return $this->getRecordService()->reject($actionParams);
}
```

Return `['count' => $n]` from the service method so the success message can use the `{count}` placeholder.

### Service method

The controller delegates to a service method that uses `executeMassAction()` — a built-in helper in `Atro\Services\Record` that decides whether to run synchronously or dispatch background jobs based on record count.

```php
// app/Atro/Services/MyEntity.php

public function reject(array $params): array
{
    $params['action']             = 'reject'; // must match the listAction key in clientDefs
    $params['maxCountWithoutJob'] = $this->getConfig()->get('massUpdateMaxCountWithoutJob', 200);
    $params['maxChunkSize']       = $this->getConfig()->get('massUpdateMaxChunkSize', 3000);
    $params['minChunkSize']       = $this->getConfig()->get('massUpdateMinChunkSize', 400);
    $params['singleActionMethod'] = 'rejectItem'; // service method called by background jobs

    [$count, $errors, $sync] = $this->executeMassAction($params, function (string $id) {
        $this->rejectItem($id); // used for synchronous execution
    });

    return ['count' => $count, 'sync' => $sync, 'errors' => $errors];
}

public function rejectItem(string $id): bool
{
    $entity = $this->getEntity($id);
    if (empty($entity)) {
        throw new \Atro\Core\Exceptions\NotFound();
    }

    // ... your per-record business logic ...

    $this->getEntityManager()->saveEntity($entity);
    return true;
}
```

There are **two execution paths** — both must be covered:

**Synchronous** (total ≤ `maxCountWithoutJob`): `executeMassAction` runs the closure inline, one ID at a time.

**Asynchronous** (total > `maxCountWithoutJob`): `executeMassAction` creates a `MassActionCreator` job that splits the IDs into chunks and dispatches one `UniversalMassAction` background job per chunk. That job instantiates the entity service and calls `$service->{singleActionMethod}($id)` for each ID in its chunk.

This is why **both** `$params['singleActionMethod']` and the closure are required — they serve different paths but both call the same per-record method. The closure handles sync; `singleActionMethod` is the string the background job uses to reach back into the service.

`executeMassAction` resolves `$params['ids']` or `$params['where']` automatically — the service does not need to handle that distinction.

---

## Controlling Visibility per Record

Actions are only shown for a record if the backend sets the action name to `true` in `_meta.permissions`. The correct way to do this is to override `putAclMeta()` in the entity's **service** class and call `$entity->setMetaPermission()`. This method is called automatically by the framework for every record returned by the API.

```php
// app/Atro/Services/MyEntity.php

public function putAclMeta(\Espo\ORM\Entity $entity): void
{
    parent::putAclMeta($entity); // always call parent — sets edit/delete/stream permissions

    $isPending = $entity->get('status') === 'pending';

    $entity->setMetaPermission('approve', $isPending && $this->getAcl()->check($entity, 'edit'));
    $entity->setMetaPermission('reject', $isPending && $this->getAcl()->check($entity, 'edit'));
}
```

For relationship panel actions (called via `putAclMetaForLink`), override that method instead:

```php
public function putAclMetaForLink(\Espo\ORM\Entity $entityFrom, string $link, \Espo\ORM\Entity $entity): void
{
    parent::putAclMetaForLink($entityFrom, $link, $entity);

    $entity->setMetaPermission('ship', $this->getAcl()->check($entity, 'edit'));
}
```

The `ClusterItem` service is a real-world reference showing both patterns — it overrides `putAclMeta()` for list/detail actions (`confirm`, `reject`, `unmerge`) and `putAclMetaForLink()` for relationship panel actions (`unreject`, `unlink`).

---

## Translations

Add labels and messages to the entity's i18n file for each locale.

```json
// app/Atro/Resources/i18n/en_US/MyEntity.json
{
  "actions": {
    "approve": "Approve",
    "reject": "Reject"
  },
  "massActions": {
    "reject": "Reject Selected"
  },
  "massActionConfirmMessages": {
    "reject": "Are you sure you want to reject the selected records?"
  },
  "massActionSuccessMessages": {
    "reject": "{count} record(s) rejected successfully."
  }
}
```

The `actions` key provides the label shown in the UI for both list/detail and mass actions. The `massActionConfirmMessages` key is shown in the confirmation dialog before a mass action runs. The `massActionSuccessMessages` key is shown after success, and supports the `{count}` placeholder if the backend returns a `count` value.

---

## Real-World Example

The `ClusterItem` entity uses `listActions` with mass action support:

```json
// app/Atro/Resources/metadata/clientDefs/ClusterItem.json
{
  "listActions": {
    "quickEdit": { "disabled": true },
    "confirm": {
      "url": "ClusterItem/action/confirm",
      "refresh": true,
      "iconClass": "ph ph-check",
      "sortOrder": 10
    },
    "reject": {
      "url": "ClusterItem/action/reject",
      "massAction": true,
      "refresh": true,
      "iconClass": "ph ph-x",
      "sortOrder": 20
    },
    "unmerge": {
      "url": "ClusterItem/action/unmerge",
      "massAction": true,
      "refresh": true,
      "iconClass": "ph ph-arrows-split",
      "sortOrder": 30
    }
  }
}
```

This configuration:
- Disables the built-in `quickEdit` row action
- Adds three custom actions to each row: **Confirm**, **Reject**, and **Unmerge**
- Makes **Reject** and **Unmerge** available as mass actions in the list toolbar
