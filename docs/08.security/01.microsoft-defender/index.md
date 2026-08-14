---
title: Microsoft Defender for Endpoint
---

Microsoft Defender for Endpoint (MDE) runs on Linux and is a reasonable choice for a server hosting AtroCore. However, installing it with default settings on a PHP application server leads to one of two outcomes: either a measurable slowdown that gets blamed on the application, or an agent configured so loosely that it protects nothing. This guide describes the configuration that avoids both.

This guide applies to Ubuntu 24.04 LTS running `mdatp` 101.26062 or later, with PHP 8.4 under Apache `mod_php` and PostgreSQL, using standalone Defender for Endpoint Server licensing without Azure Arc.

## Why Run Defender on a Production Server ##

### It Does Not Replace a Web Application Firewall ###

The most common misconception is that Defender overlaps with a WAF. It does not. Defender watches the **file system and processes**; it never sees an HTTP request. A SQL injection attempt is invisible to it, just as cron-based persistence is invisible to ModSecurity.

| Layer | Tool | Catches |
| --- | --- | --- |
| HTTP requests | ModSecurity / WAF | SQL injection, XSS, path traversal, upload attempts |
| Files and processes | **Defender** | A web shell that already landed, persistence, a web process spawning `bash` |
| Code integrity | `composer.lock`, git | Supply chain tampering |
| Database | Roles, `pg_hba.conf` | Unauthorised access |

### The PIM-Specific Reason ###

A PIM distributes files rather than consuming them. Images, catalogues and price lists leave the system through channel exports, the API and partner downloads.

An infected file in a storage directory is consequently low risk to the host — storage sits outside the document root, and the server does not execute the document formats a PIM handles — but it reaches every recipient downstream. The exposure is contractual and reputational rather than operational, which is why storage content is scanned even though the host itself is not endangered by it.

### Files Do Not Only Arrive Through the Application ###

AtroCore validates the files it receives through its own upload pipeline, and the application code is developed with secure defaults and reviewed continuously. The security boundary of a production server is nevertheless wider than the application running on it.

A storage location in AtroCore is a configured path on disk, and nothing constrains how content reaches that path. One common and entirely legitimate pattern is to grant a supplier SFTP access to a directory, register that same directory as a Local Storage, and then scan its contents into the catalogue. Files delivered this way never pass through the application. The same applies to `rsync` jobs, mounted network shares, restored backups, and any other service running on the host.

Application-level validation cannot inspect what does not pass through the application. Scanning at the filesystem level is the only control that sees every file regardless of how it arrived, which is precisely the gap endpoint protection exists to close.

### What EDR Adds Beyond Signatures ###

The behavioural component has no equivalent in a WAF. When `www-data` launches `bash`, `curl` or a compiler, that is anomalous regardless of whether any file matches a known signature. The same applies to a new cron entry or a modified systemd unit. Such events generate alerts in the portal with the initiating process and account attached.

### Defence in Depth ###

Antivirus is the third line of defence, not the first. If a directory does not need to be writable, removing the write permission is more effective than monitoring it.

1. **File permissions** — the attack is impossible, the write fails with `EACCES`.
2. **Apache configuration** — the file exists but is never interpreted.
3. **Defender real-time protection** — the file would have run, but the signature was recognised.
4. **Defender EDR** — nothing was stopped, but you know about it and can respond.

## Before You Configure ##

Install and onboard the agent as described in the [Microsoft Defender for Endpoint on Linux documentation](https://learn.microsoft.com/en-us/defender-endpoint/microsoft-defender-endpoint-linux). System requirements, supported distributions and the onboarding procedure are covered there and require nothing specific to AtroCore.

The rest of this article assumes an onboarded agent in its default state:

```
mdatp health --field healthy    # true
mdatp health --field licensed   # true
```

A newly onboarded agent runs in passive mode, which means alerts are generated but file operations are not intercepted. Keep it that way until the exclusions below are in place.

## Recommended Configuration ##

Apply the exclusions first and switch the enforcement level afterwards. Enabling real-time protection on an unconfigured agent is what produces the familiar complaint that installing an antivirus made the application slow.

Run one on-demand scan of the application directory before switching. AtroCore application code and its Composer dependencies do not trigger antivirus signatures, so no false-positive exclusions are needed for code directories — but any third-party module you have installed is worth checking before scanning becomes enforcing.

```
mdatp scan custom --path /var/www/atrocore
mdatp threat list
```

### The Exclusion Principle ###

Exclusions are a **closed list**. Every path not named in the configuration stays under real-time protection, and that default is what keeps the configuration correct as an installation evolves. Exclude only directories whose contents you can account for — framework-generated files, built assets, dependencies, database storage. Never build the list the other way around, by enumerating what should be scanned.

This distinction is not academic. Storage locations in AtroCore are configurable, an installation may define several of them, and their paths are chosen by the administrator rather than fixed by the application. A list of directories to scan would be incomplete the moment a new Local Storage is added. A list of directories to exclude remains correct, because anything new is protected by default.

Justify each exclusion by who is able to write to the directory and whether its contents are predictable — not by a wish for better performance.

The following list applies to an AtroCore installation located at `/var/www/atrocore`.

| Path to exclude | Why it is safe |
| --- | --- |
| `vendor/` | Changes only during dependency updates, but is read on every request and by every background worker. Highest cost, lowest yield for real-time interception. Covered by the daily scheduled scan instead. |
| `data/cache/` | Generated PHP metadata. Clearing the cache writes thousands of small files in one burst, which is the worst case for `fanotify`. |
| `data/logs/` | Append-only, high frequency, no detection value. |
| `data/migrations/`, `data/module-manager-events/`, `data/reference-data/` | Framework-managed, rewritten during updates, not reachable by user input. |
| `public/client/` | Built frontend assets, tens of megabytes of JavaScript and CSS, rewritten by the module manager. See the caveat below. |
| `public/apidocs/`, `public/docs/`, `public/data/`, `public/listening/` | Static generated output, provided these directories are not writable by the web process. |
| `backups/` | Large archives, read rarely, written by scheduled jobs. Scanning them on access is expensive and duplicated by the weekly full scan. |
| `/var/lib/postgresql/` | Database files. Microsoft recommends excluding database storage, and scanning it damages I/O behaviour. |

Two categories must remain outside the exclusion list:

**Storage directories.** Whatever paths are registered as Local Storages, including the default `upload/`, carry content that originates outside the application — see the section on how files arrive. They are the primary reason for installing endpoint protection on this host, and excluding them defeats the purpose. Review the configured storage paths in AtroCore before finalising the exclusion list, and confirm that none of them falls inside an excluded directory.

**The document root.** `public/` should contain exactly one PHP file, `index.php`. Anything else appearing there warrants blocking, and the subdirectories listed above are excluded individually rather than by excluding `public/` as a whole.

In a typical deployment the entire tree is owned by `www-data`, because the module manager updates code and rebuilds frontend assets. That makes `public/client/` both writable by the web process and excluded from real-time protection. The exposure is limited: writes there come through the module manager rather than user input, EDR telemetry still covers the directory, and the daily scheduled scan inspects it. Restrict permissions outside deployment windows if you want to close it entirely.

### Antivirus Exclusions Do Not Blind EDR ###

This distinction is important and easy to get wrong. Note the `Scope` field when listing exclusions:

```
$ mdatp exclusion list
Excluded folder
Path: "/var/www/atrocore/vendor"
Scope: ["epp"]
```

`epp` stands for Endpoint Protection Platform, meaning the antivirus engine only. EDR maintains its own separate exclusion mechanism (`mdatp edr exclusion`), which remains untouched. Consequently, for every excluded directory the antivirus no longer scans files on access, but EDR still records `FileCreated` and `FileModified` events, custom detection rules still fire, and behavioural alerts on execution still fire.

Exclusions cost you **blocking**, not **visibility**. This is what makes a fairly generous exclusion list defensible.

### Managed Configuration File ###

Configuration should be declared in a file rather than accumulated through CLI commands. A managed file is kept in version control, deploys identically to every server, survives agent reinstallation and can be reviewed like any other change. As a side effect it also makes settings read-only through the CLI.

Create `/etc/opt/microsoft/mdatp/managed/mdatp_managed.json`. Replace `/var/www/atrocore` with your own installation root throughout, and remove any entry that does not exist on your server:

```json
{
  "antivirusEngine": {
    "enforcementLevel": "real_time",
    "behaviorMonitoring": "enabled",
    "scanAfterDefinitionUpdate": true,
    "scanArchives": true,
    "maximumOnDemandScanThreads": 2,
    "exclusionsMergePolicy": "merge",
    "exclusions": [
      {
        "$type": "excludedPath",
        "isDirectory": true,
        "path": "/var/www/atrocore/vendor"
      },
      {
        "$type": "excludedPath",
        "isDirectory": true,
        "path": "/var/www/atrocore/backups"
      },
      {
        "$type": "excludedPath",
        "isDirectory": true,
        "path": "/var/www/atrocore/data/cache"
      },
      {
        "$type": "excludedPath",
        "isDirectory": true,
        "path": "/var/www/atrocore/data/logs"
      },
      {
        "$type": "excludedPath",
        "isDirectory": true,
        "path": "/var/www/atrocore/data/migrations"
      },
      {
        "$type": "excludedPath",
        "isDirectory": true,
        "path": "/var/www/atrocore/data/module-manager-events"
      },
      {
        "$type": "excludedPath",
        "isDirectory": true,
        "path": "/var/www/atrocore/data/reference-data"
      },
      {
        "$type": "excludedPath",
        "isDirectory": true,
        "path": "/var/www/atrocore/public/client"
      },
      {
        "$type": "excludedPath",
        "isDirectory": true,
        "path": "/var/www/atrocore/public/apidocs"
      },
      {
        "$type": "excludedPath",
        "isDirectory": true,
        "path": "/var/www/atrocore/public/docs"
      },
      {
        "$type": "excludedPath",
        "isDirectory": true,
        "path": "/var/www/atrocore/public/data"
      },
      {
        "$type": "excludedPath",
        "isDirectory": true,
        "path": "/var/www/atrocore/public/listening"
      },
      {
        "$type": "excludedPath",
        "isDirectory": true,
        "path": "/var/lib/postgresql"
      }
    ],
    "threatTypeSettings": [
      { "key": "potentially_unwanted_application", "value": "block" }
    ],
    "threatTypeSettingsMergePolicy": "merge"
  },
  "cloudService": {
    "enabled": true,
    "diagnosticLevel": "optional",
    "automaticSampleSubmission": false,
    "automaticDefinitionUpdateEnabled": true
  }
}
```

Note that no storage location appears in this list, and none should be added. If your installation keeps a Local Storage under a path that falls inside one of the excluded directories, move the storage rather than removing the exclusion.

For MySQL or MariaDB, replace `/var/lib/postgresql` with `/var/lib/mysql`.

`automaticSampleSubmission` controls whether suspicious files are uploaded to Microsoft for analysis. On a PIM those files may contain customer commercial data, so this setting deserves a deliberate decision rather than a copied default.

> **Important:** in the managed configuration the exclusion type is `excludedPath` with `isDirectory: true`. The value `folder` belongs to the CLI syntax and is invalid here, and Defender does not report the error. It silently discards the **entire file** and falls back to defaults, which makes unrelated settings appear to change on their own.

Never treat a successful file copy as a successful configuration. Always verify against the agent state:

```
python3 -m json.tool /etc/opt/microsoft/mdatp/managed/mdatp_managed.json > /dev/null
sudo systemctl restart mdatp

mdatp exclusion list                                 # must list your paths
mdatp health --field real_time_protection_enabled    # true
mdatp health --field passive_mode_enabled            # false
mdatp health --field behavior_monitoring             # "enabled"
mdatp health --field health_issues                   # []
```

If `exclusion list` returns `No exclusions` while the file is present, the schema was rejected.

## Scheduled Scans ##

Scheduled scanning completes the configuration. Real-time protection covers everything outside the exclusion list; scheduled scans cover the excluded directories as well, so nothing on the server is left permanently unexamined. Excluding a directory then becomes a decision about *when* it is inspected rather than *whether* it is inspected at all.

There is also a platform difference that surprises administrators coming from Windows: **Defender on Linux ships no scan scheduler**. Nothing runs periodically unless you arrange it, so a freshly onboarded Linux server is never scanned on a schedule until cron is configured.

Detections are reported to the portal regardless of what triggers a scan, so cron is sufficient and no integration work is required for alerting.

> **Exclusions apply to scans as well.** By default `mdatp scan` skips every excluded path — a scan of an excluded directory reports `0 file(s) scanned`. The `--ignore-exclusions` flag overrides this and is what makes the excluded directories reachable by scheduled scanning. Without it, an excluded path is never inspected by any mechanism.

Create `/etc/cron.d/mdatp-scan`:

```
# Microsoft Defender for Endpoint - scheduled scans
# MDE on Linux has no built-in scan scheduler, so scans are driven by cron.
# --ignore-exclusions is required: without it a scan skips every excluded path.
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

# Daily 04:00 - application directory, including paths excluded from real-time protection
0 4 * * * root /usr/bin/mdatp scan custom --path /var/www/atrocore --ignore-exclusions >> /var/log/mdatp-scan.log 2>&1

# Weekly, Sunday 03:00 - full system scan
0 3 * * 0 root /usr/bin/mdatp scan full >> /var/log/mdatp-scan.log 2>&1
```

The two tiers answer different questions. The daily scan covers the application tree in full, including `vendor/`, `backups/` and every other excluded directory inside it, which is what closes the supply chain gap created by excluding dependencies from real-time protection. The weekly full scan covers the rest of the host — temporary directories, home directories and anything dropped outside the application tree.

Note that `mdatp scan full` does not accept `--ignore-exclusions`; it ignores unrecognised arguments and starts scanning regardless. The weekly scan therefore honours exclusions, and coverage of excluded paths comes from the daily `scan custom` run. If an excluded path lies outside the application tree, add a separate cron entry for it. Database storage is the deliberate exception: scanning live database files is not advisable, so `/var/lib/postgresql` stays excluded from both mechanisms.

Add log rotation, because the log grows indefinitely otherwise. Create `/etc/logrotate.d/mdatp-scan`:

```
/var/log/mdatp-scan.log {
    monthly
    rotate 6
    compress
    missingok
    notifempty
}
```
