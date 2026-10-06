---
title: Rate Limiting
---

Rate limiting slows down automated abuse of the endpoints that anyone can reach without being logged in: guessing passwords and requesting password resets. A client that sends too many requests receives `429 Too Many Requests` and has to wait before trying again.

## What Is Limited ##

| Endpoint | Limit |
| --- | --- |
| `GET /api/userSession` (login and session check) | per IP address, and per user name |
| `POST /api/User/passwordChangeRequest` (password reset request) | per IP address |

Everything else, including all regular authenticated API calls, is **not** rate limited.

### Per IP Address ###

One IP address may send at most **3 requests per second** to each limited endpoint. Every request counts, whether the login succeeded or not. A rejected request is not counted, so a client that backs off is accepted again as soon as its earlier requests are older than the period.

### Per User Name (Login Only) ###

The login endpoint is also limited per user name: once requests for the same user name came from **more than 5 different IP addresses within 60 seconds**, further requests for that user name are rejected until older ones fall out of the window.

The limit counts *different addresses*, not attempts. A person who mistypes a password a few times from a laptop and a phone is never affected, while an attack on one account spread over many addresses, which a plain per-IP limit cannot see, is stopped.

> **Note:** Opening the application also calls the login endpoint once (the browser checks its session), so that request counts like any other. The default is meant for normal use, but many users behind one shared address (for example an office network) who open the application at the very same moment can be rejected; raise **Rate Limit: Requests** in that case.

## Settings ##

The values are set in `Administration > Authentication`, panel *Rate Limit* (see [Authentication](../../01.atrocore/03.administration/14.access-management/05.authentication/index.md#rate-limit)).

| Setting | Default | Description |
| --- | --- | --- |
| Rate Limit: Requests | `3` | Requests one IP address may send to a limited endpoint within the period. |
| Rate Limit: Period (seconds) | `1` | Length of the period. Fractions are allowed, e.g. `0.5`. |
| Max Source IPs Per User | `5` | Different IP addresses allowed for the same user name on the login endpoint within the user period. |

The window of the per-user rule is 60 seconds. It is not shown in the interface; to change it, add `rateLimitUserPeriod` (seconds) to `data/config.php`.

## What the Client Sees ##

A rejected request is answered with status `429`, the message *Too many requests. Please try again later.* and the header `Retry-After` (seconds until a retry can succeed). Every response of a limited endpoint also carries the headers `RateLimit-Limit`, `RateLimit-Remaining` and `RateLimit-Reset`, so integrations can pace themselves.

## Behind a Reverse Proxy ##

AtroCore identifies a client by the address that connects to it. When AtroCore runs behind a reverse proxy, load balancer or Docker port mapping that does not pass the client address through, **all users share the address of the proxy** and therefore share one limit. In that case also limit requests at the proxy, where the real client address is known.

## Limiting at the Web Server (Recommended in Addition) ##

The built-in limit knows about user names and runs inside the application. A limit in the web server complements it: it rejects floods before they reach PHP and the database, and it can cover paths AtroCore does not limit itself. The example below is for Nginx, using the standard `limit_req` module; add the zone to the `http` block and the `limit_req` lines to the `location` that serves the API, and adjust the numbers to your traffic.

```
# http { ... }
limit_req_zone $binary_remote_addr zone=atrocore_api:10m rate=20r/s;

# server { ... location ... }
limit_req zone=atrocore_api burst=40 nodelay;
limit_req_status 429;
```

For Apache, `mod_evasive` or `mod_security` offer similar controls.

## Data and Maintenance ##

Accepted requests to limited endpoints are kept for one day and then deleted by the scheduled job **Clear deleted data**, so make sure [cron](../../09.installation-and-maintenance/01.installation/index.md) is running.

If the rate limiter cannot work, for example because an update was not completed, requests are processed normally and the error is written to the log.

## For Developers ##

Any endpoint can opt in with the `rateLimit` option of the `#[Route]` attribute; see [Rate Limiting](../../10.developer-guide/02.understanding-atrocore/12.handlers#rate-limiting) in the handler guide.
