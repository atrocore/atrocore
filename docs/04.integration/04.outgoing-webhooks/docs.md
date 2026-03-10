---
title: Outgoing Webhooks
taxonomy:
    category: docs
---

## Overview

AtroCore does not provide a dedicated entity called "Outgoing Webhook" — and for good reason. Outgoing HTTP requests are handled more flexibly and powerfully through the built-in **Export: HTTP Request** mechanism.

> For full documentation, see: [Export Feeds: HTTP Request](https://help.atrocore.com/data-exchange/export-feeds-http-request)

## Concept

Instead of managing a separate Outgoing Webhook entity, AtroCore leverages the **Export Feed** system. By creating an Export Feed of type `HTTP Request`, you can configure outbound HTTP calls with full control over:

- Target URL
- HTTP method (`POST`, `PUT`, `GET`, etc.)
- Headers and payload structure
- Authentication

This approach provides greater flexibility and aligns with AtroCore's modular architecture.

> While you can also use an Action of type `Webhook` for simpler outbound HTTP calls, this approach offers fewer configuration options and less flexibility compared to Export Feeds.

## Setup Guide

### 1. Create an Export Feed

Create a new feed with type `HTTP Request` according to your needs.

### 2. Create an Action

Go to **Administration → Actions** and create a new Action of type `Export Feed`. In the configuration, select the Export Feed you just created.

This Action will execute the outbound HTTP request whenever triggered. For details, see [Actions](../../02.atrocore/03.administration/06.actions/).

### 3. Triggering the Action

You have multiple options to trigger the Action:

- **Cron Job**: Schedule the Action to run periodically.
- **Manual Button**: Add the Action as a button in the UI for manual execution.
- **Workflow Module**: If the Workflows module is installed, you can bind the Action to specific events (e.g., record creation, status change).

## Benefits

- No need for a separate Outgoing Webhook entity
- Full control over request structure and timing
- Seamless integration with AtroCore’s Actions and Workflows
- Reusable and modular configuration

---

By using Export Feeds with HTTP Request type, AtroCore offers a robust and developer-friendly way to handle outbound communication with external systems.
