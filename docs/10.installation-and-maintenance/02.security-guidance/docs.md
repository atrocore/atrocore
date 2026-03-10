---
title: Security Guidance
taxonomy:
    category: docs
---

AtroCore aims to ship with secure defaults that do not need to get modified by administrators. However, in some cases some additional security hardening can be applied in scenarios were the administrator has complete control over the Instance.

## Server Security ##
* Keep the server up to date with security patches (```sudo apt update && sudo apt upgrade```).
* Disable root login and use SSH keys for authentication.
* Configure a firewall (UFW) to allow only necessary ports.
* Use Fail2Ban to prevent brute-force attacks.
* Restrict file and directory permissions.
* Disable directory listing in Apache (Options -Indexes).

## PHP Security ##
Enable error logging but disable displaying errors (display_errors = Off).

## Database Security ##
Disable remote root access to the database.

## Use HTTPS ##
Using AtroCore without using an encrypted HTTPS connection opens up your server to a man-in-the-middle (MITM) attack, and risks the interception of user data and passwords. It is a best practice, and highly recommended, to always use HTTPS on production servers, and to never allow unencrypted HTTP.

## Redirect all unencrypted traffic to HTTPS ##
To redirect all HTTP traffic to HTTPS administrators are encouraged to issue a permanent redirect using the 301 status code. When using Apache this can be achieved by a setting such as the following in the Apache VirtualHosts configuration:
```
<VirtualHost *:80>
   ServerName pim.your-domain.com
   Redirect permanent / https://pim.your-domain.com/
</VirtualHost>
```

## Don't use admin user ##
Don't use an admin user for everyday work. Use a regular user instead.

## Password Security ##
To ensure the security of your accounts and sensitive data, follow these best practices when creating a password:

1. *Length*: Use a password that is at least 12-16 characters long. Longer passwords are significantly harder to crack.

2. *Complexity*: Include a mix of:
    * Uppercase letters (A-Z)
    * Lowercase letters (a-z)
    * Numbers (0-9)
    * Special characters (!@#$%^&*()-_+=<>?/[]{}|)

3. *Avoid Common Words*: Do not use easily guessable words, phrases, or patterns such as:
    * "password," "123456," or "qwerty"
    * Your name, username, or birthdate
    * Repeated or sequential characters (e.g., "aaaaaa," "1234," or "abcd")

4. *Use Passphrases*: A random combination of unrelated words (e.g., "BlueTiger$Mountain99") is both strong and easier to remember.

5. *Unique Passwords*: Do not reuse passwords across multiple accounts. If one is compromised, others remain safe.

6. *Password Managers*: Consider using a password manager to generate and store complex passwords securely.

By following these guidelines, you can significantly improve the security of your online accounts and protect your personal information.

## Auth token expiration ##
Consider decreasing [Auth Token Max Idle Time](../../02.atrocore/03.administration/14.access-management/05.authentication/). Additionally, you can also specify Auth Token Lifetime.