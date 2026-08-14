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

On a general purpose web server the argument for antivirus is protecting the host. On a PIM the stronger argument is different.

**A PIM exists to distribute files.** Product images, PDF catalogues and XLSX price lists are imported, then exported to channels, handed to partners through the API and downloaded by clients. Storage directories are located outside the document root, so a web shell placed there cannot be executed over HTTP. The host is not the target — the recipients are:

```
infected XLSX enters a storage directory
  -> the Linux server never executes it        # host unaffected
  -> the PIM exports it to a channel
  -> a partner opens it on Windows             # the macro fires here
```

Scanning storage content is therefore not a question of server integrity. It is a question of not becoming a distribution vector for your own customers, which is a contractual and reputational exposure long before it is a technical one.

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

## Prerequisites ##

Real-time protection on Linux relies on `fanotify`. Both kernel options must be present, otherwise the agent can only log events instead of blocking them:

```
grep -E "CONFIG_FANOTIFY(_ACCESS_PERMISSIONS)?=" /boot/config-$(uname -r)
```

`fanotify` is a limited resource, so competing agents degrade each other. Verify that no other antivirus, EDR or file integrity monitoring service is running:

```
systemctl list-units --state=running | grep -iE "clam|falco|wazuh|ossec"
```

The agent requires roughly 1 GB of RAM and 2 GB of free disk space under `/opt`.

## Installation ##

The `mdatp` package is only available from the Microsoft repository:

```
curl -sSL https://packages.microsoft.com/keys/microsoft.asc \
  | gpg --dearmor | sudo tee /usr/share/keyrings/microsoft-prod.gpg > /dev/null

curl -sSL https://packages.microsoft.com/config/ubuntu/24.04/prod.list \
  | sudo tee /etc/apt/sources.list.d/microsoft-prod.list

sudo apt update
sudo apt install -y mdatp
```

Onboarding requires a package downloaded from the Defender portal under *Settings → Endpoints → Device management → Onboarding*, selecting `Linux Server` as the operating system, `Streamlined` connectivity and `Local script` as the deployment method. The archive contains a Python script that generates the tenant configuration:

```
unzip WindowsDefenderATPOnboardingPackage.zip
sudo python3 MicrosoftDefenderATPOnboardingLinuxServer.py
```

There is no licence key to enter. The onboarding package carries a tenant certificate, and entitlement is verified cloud-side on every connection. A licence is consumed automatically when a device onboards and is returned only through offboarding — removing the package is not sufficient.

Confirm the result:

```
mdatp health --field healthy       # true
mdatp health --field licensed      # true
mdatp health --field health_issues # []
mdatp connectivity test            # all endpoints must report [OK]
```

Note that `mdatp connectivity test` cannot be reproduced before onboarding, because the endpoint host names are derived from the tenant. Attempts to pre-flight them with `curl` produce misleading failures.

## Recommended Configuration ##

Configure exclusions **before** enabling real-time protection. A freshly onboarded agent defaults to `passive_mode_enabled = true`, meaning EDR telemetry flows and alerts are generated, but file operations are not intercepted. That default is deliberate and useful: it lets you onboard a production server without any performance impact, and gives you time to apply the configuration below before scanning becomes active.

Enabling real-time protection first, on an unconfigured agent, is what produces the familiar complaint that installing an antivirus made the application slow.

Run one on-demand scan of the application directory before switching to real-time mode. AtroCore application code and its Composer dependencies do not trigger antivirus signatures, so no false-positive exclusions are needed for code directories — but confirming this on your own installation takes a single command, and any third-party module you have installed is worth checking before scanning becomes enforcing.

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
| `vendor/` | Changes only during `composer update`, but is read on every request and by every background worker. Highest cost, lowest yield. Covered by scheduled scans instead. |
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

In a typical deployment the entire tree is owned by `www-data`, because the module manager updates code and rebuilds frontend assets. That makes `public/client/` both writable by the web process and excluded from antivirus, which is the one genuine gap in this configuration. The gap is narrow, since writes there come through the module manager rather than user input. Close it by restricting permissions outside deployment windows, or by the custom detection rule described below.

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

Scheduled scanning is often treated as optional. With the exclusion list above it is closer to mandatory, because `vendor/` is excluded from real-time protection and a compromised Composer package is therefore no longer intercepted on access. A periodic scan is the only remaining control covering supply chain risk.

There is also a platform difference that surprises administrators coming from Windows: **Defender on Linux ships no scan scheduler**. Nothing runs periodically unless you arrange it, so a freshly onboarded Linux server is never scanned on a schedule until cron is configured.

Detections are reported to the portal regardless of what triggers a scan, so cron is sufficient and no integration work is required for alerting.

Create `/etc/cron.d/mdatp-scan`:

```
# Microsoft Defender for Endpoint - scheduled scans
# MDE on Linux has no built-in scan scheduler, so scans are driven by cron.
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

# Daily 04:00 - scan the application directory
0 4 * * * root /usr/bin/mdatp scan custom --path /var/www/atrocore >> /var/log/mdatp-scan.log 2>&1

# Weekly, Sunday 03:00 - full system scan
0 3 * * 0 root /usr/bin/mdatp scan full >> /var/log/mdatp-scan.log 2>&1
```

The two tiers answer different questions. The daily project scan covers freshly imported content and recently updated dependencies, while the weekly full scan covers everything the exclusion list omits, such as temporary directories, home directories and anything dropped outside the application tree.

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

Note that `/etc/cron.d/` and `crontab -e` are two different mechanisms. Entries placed in `/etc/cron.d/` are not shown by `crontab -l`, so verify them with `cat /etc/cron.d/mdatp-scan` instead. Files under `/etc/cron.d/` also require an additional field naming the user after the schedule; moving a line between the two formats without adjusting that field results in a job that cron silently ignores.

## Verifying Protection ##

A healthy agent proves that the configuration was accepted, not that blocking works. Test with EICAR, a harmless standard test string, written into a storage directory — never excluded, and therefore the right place to confirm interception:

```
printf 'X5O!P%%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*' \
  > /var/www/atrocore/upload/eicar_test.txt

mdatp threat list
mdatp threat quarantine list
```

With `enforcementLevel: real_time` the write is intercepted and the file quarantined, an alert appears in the portal, and an email arrives if notifications are configured. If nothing is detected, real-time protection is not intercepting writes, which is a more serious problem than any tuning described here.

Repeat the same test in each configured storage location. This verifies that no storage path has been placed inside an excluded directory — the most likely configuration error, and one that produces no visible symptom until it matters. Running the test inside an excluded directory instead shows exactly how much coverage that exclusion costs.

### Email Notifications ###

An agent can run correctly for months, generate alerts and have nobody read them. This is the most common operational failure, so configure notifications explicitly.

There are two separate places in the portal with different granularity: endpoint alert notifications under *Settings → Endpoints*, and XDR incident notifications under *Settings → Microsoft Defender XDR*. Prefer incident notifications as the primary channel, because one attack then produces a single email rather than fifteen. Exclude the Informational severity, enable notification on the first update only, and note that the recipient address requires an explicit *Add* before saving — a rule with no recipients looks identical to a working one.

### Detecting Unexpected PHP Files ###

Since `public/` should contain exactly one PHP file, anything else appearing there is worth an alert. This cannot be expressed as an antivirus rule, but it works as a custom detection rule built on an Advanced Hunting query. Because it runs on EDR telemetry, it fires even for directories excluded from antivirus:

```
DeviceFileEvents
| where FolderPath startswith "/var/www/atrocore/public/"
| where FileName endswith ".php"
| where FileName != "index.php"
| where ActionType in ("FileCreated", "FileModified", "FileRenamed")
| project Timestamp, DeviceName, FolderPath, FileName,
          InitiatingProcessFileName, InitiatingProcessAccountName, SHA256
```

Combine it with an Apache level control that makes such a file inert in the first place, by disabling PHP execution across the document root and re-enabling it only for the single entry point:

```
<Directory /var/www/atrocore/public>
    AllowOverride All
    Require all granted

    php_admin_flag engine Off
    <FilesMatch "\.php$">
        Require all denied
    </FilesMatch>

    <Files "index.php">
        php_admin_flag engine On
        Require all granted
    </Files>
</Directory>
```

`php_admin_flag` is a `mod_php` directive. Under PHP-FPM it has no effect and must be replaced with a `SetHandler` based construct, which is a routine trap when migrating between the two.

## Differences from Windows Defender ##

|  | Windows | Linux |
| --- | --- | --- |
| Interface | Windows Security UI, tray, notifications | Command line only, via `mdatp` |
| Integration | Part of the operating system | Separate package, updated through `apt` |
| Interception | Kernel mini-filter driver | `fanotify`, with a heavier I/O impact |
| Policy | Group Policy or Intune, hundreds of settings | Managed JSON, far fewer settings |
| Scheduled scans | Built in | Must be configured through cron |
| Updates | Windows Update | `apt upgrade mdatp`, which must be planned |

Features with no Linux equivalent include Attack Surface Reduction rules (only a small subset is available), Controlled Folder Access, Exploit Guard and firewall management from the portal. Windows-specific detections such as LSASS dumping are replaced by Linux-relevant ones covering web shells, cron persistence and suspicious activity originating from a web process.

A more accurate mental model than "Windows Defender for Linux" is an EDR agent that shares the same cloud backend, with a narrower feature set and a requirement for manual tuning.

In real-time mode with the exclusions above, the agent uses roughly 270 MB of RSS across two processes and around 440 MB of disk under `/opt/microsoft/mdatp`, with CPU near idle at rest. The EDR subsystem selects `eBPF` rather than `auditd`, which is lighter and lets `auditd` remain disabled.