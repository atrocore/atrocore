---
title: AppArmor Configuration
---

Ubuntu enables AppArmor by default. AppArmor assigns a security profile to individual executables and restricts what files they can access. Some tools used by AtroCore – most notably Ghostscript (`gs`) and LibreOffice – have their own AppArmor profiles that do not include your application directory by default. Without the overrides described here, PDF-related operations will silently fail.

> This guide is based on **Ubuntu 22.04/24.04**.

## 1. Verify That AppArmor Is Active

```bash
sudo aa-status
```

The output lists all loaded profiles and whether each executable is in `enforce` or `complain` mode. If you see `gs` listed, the override below is required.

```
apparmor module is loaded.
...
/usr/bin/gs (enforce)
...
```

## 2. Diagnose Blocked Operations

When AppArmor blocks a system call it writes a `DENIED` entry to the system log. If PDF generation or thumbnail processing returns an error, check:

```bash
sudo grep "apparmor.*DENIED" /var/log/syslog | tail -20
```

A typical blocked Ghostscript call looks like:

```
kernel: audit: type=1400 audit(...): apparmor="DENIED" operation="open"
  profile="/usr/bin/gs" name="/var/www/my-atrocore-project/public/upload/..." ...
```

The `profile` field shows which AppArmor profile blocked the call and `name` shows the file it could not access.

## 3. Allow Ghostscript to Access AtroCore Directories

AppArmor loads local rule overrides from `/etc/apparmor.d/local/`. Create or edit the override file for Ghostscript:

```bash
sudo nano /etc/apparmor.d/local/usr.bin.gs
```

Add the following lines, replacing `/var/www/my-atrocore-project` with the actual path to your installation:

```
# AtroCore – allow Ghostscript to read uploaded files and write temporary files
/var/www/my-atrocore-project/public/upload/** r,
/var/www/my-atrocore-project/data/upload/** r,
/var/www/my-atrocore-project/data/tmp/** rw,
```

> **Note:** If you store uploads outside the project directory (for example, on a mounted volume), add a corresponding `r,` rule for that path as well.

Reload the profile to apply the changes:

```bash
sudo apparmor_parser -r /etc/apparmor.d/usr.bin.gs
```

Verify that the override was loaded without errors:

```bash
sudo aa-status | grep gs
```

## 4. Verify the Fix

Trigger a PDF export or any operation that uses Ghostscript, then confirm no new `DENIED` entries appear:

```bash
sudo grep "apparmor.*DENIED.*gs" /var/log/syslog | tail -5
```

An empty result confirms that Ghostscript can now access the required files.

## 5. LibreOffice (PDF Generator Module Only)

If you use the "PDF Generator" module with ODT-to-PDF conversion, LibreOffice also runs as a subprocess and requires additional AppArmor rules.

Check whether LibreOffice has a profile:

```bash
sudo aa-status | grep -i "libreoffice\|soffice"
```

If listed, create a local override:

```bash
sudo nano /etc/apparmor.d/local/usr.lib.libreoffice.program.soffice.bin
```

```
# AtroCore – LibreOffice ODT/PDF conversion
/var/www/my-atrocore-project/data/tmp/** rw,
/tmp/atrocore-** rw,
```

Reload:

```bash
sudo apparmor_parser -r /etc/apparmor.d/usr.lib.libreoffice.program.soffice.bin
```

> LibreOffice also writes its user profile to a home directory. If `www-data` has no writable home, set the `HOME` environment variable in your PHP-FPM pool configuration (`env[HOME] = /tmp`) to avoid additional permission issues.

## 6. Putting AppArmor in Complain Mode (Troubleshooting Only)

If you are unsure which paths need to be allowed, temporarily switch the profile to `complain` mode. In this mode AppArmor logs denials without actually blocking operations, which lets the system run normally while you collect the full list of required paths.

```bash
sudo aa-complain /usr/bin/gs
```

Reproduce the operations that were failing, then inspect the log:

```bash
sudo grep "apparmor.*gs" /var/log/syslog
```

Each log line contains the `name` of a file that would have been denied. Add the relevant paths to the local override file, then switch back to enforce mode:

```bash
sudo aa-enforce /usr/bin/gs
sudo apparmor_parser -r /etc/apparmor.d/usr.bin.gs
```

!! Never leave a production server in complain mode. Complain mode disables the protection for the affected profile.
