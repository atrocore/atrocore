---
title: Incoming Webhooks
taxonomy:
    category: docs
---

## Overview

Incoming Webhooks allow external systems to trigger internal actions via HTTP requests. This feature is accessible via **Administration → Incoming Webhooks**. An Incoming Webhook is an entity that defines a secure HTTP endpoint. When triggered, it executes a predefined internal action. Webhooks are useful for integrating third-party services, automating workflows, or receiving external notifications.

## Entity Fields

| Field         | Description                                                                 |
|---------------|-----------------------------------------------------------------------------|
| `Name`        | Human-readable name of the webhook.                                         |
| `Active`      | Boolean flag to enable or disable the webhook.                              |
| `Code`        | Unique identifier used to construct the webhook URL.                        |
| `Action`      | Reference to an internal `Action` entity. This action is executed on call.  |
| `HTTP Method` | Supported HTTP method (`POST` or `GET`).                                    |
| `URL`         | Auto-generated endpoint URL for external access.                            |
| `IP White List` | Optional list of allowed IP addresses. If empty, all IPs are permitted.   |
| `Hash`        | Optional security hash. Can be static or dynamically generated via Twig.    |

## Security Considerations

To enhance security, you can restrict access using:

- **IP Whitelisting**: Define trusted IP addresses. Requests from other IPs will be rejected.
- **Hash Verification**: Include a hash parameter in the request to validate authenticity.

### Dynamic Hash Example

You can use Twig to generate a time-sensitive hash. For example:

```twig
{{ ('your-string-here' ~ 'now'|date('Y-m-d_H-i'))|md5 }}
```
This expression creates a hash that updates every minute, since it incorporates the current timestamp formatted as ```Y-m-d_H-i```. By appending this to a static string and applying the ```md5``` function, you generate a predictable yet time-bound hash. This technique is useful for:
- Preventing replay attacks
- Ensuring that only requests with a valid, time-matching hash are accepted
- Creating short-lived tokens without needing persistent storage
  Make sure your external system can generate the same hash format to authenticate requests correctly.


## Usage

Once an Incoming Webhook is created and activated, it can be used to receive HTTP requests from external systems. Follow these steps to ensure proper integration:

1. **Retrieve the Webhook URL**
   The system automatically generates a unique URL. This URL can be copied directly from the webhook record.

2. **Configure Your External System**
   Ensure that your external service sends requests using the specified `HTTP Method` (`POST` or `GET`). The method must match the one defined in the webhook configuration.

3. **Secure the Request**
    - If a `Hash` is defined, include it in the request.
    - If an `IP White List` is configured, make sure the request originates from one of the allowed IP addresses. Requests from other IPs will be rejected.

4. **Trigger the Action**
   When a valid request is received, AtroCore executes the linked `Action`. This action may involve data processing, record creation, notifications, or other custom logic.

> For import purposes, [Import Feeds](../../02.data-exchange/01.import-feeds/) provide dedicated import configuration options and are specifically designed for data import workflows
