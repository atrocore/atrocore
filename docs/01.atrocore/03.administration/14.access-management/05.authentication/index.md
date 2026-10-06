---
title: Authentication
--- 

The Authentication page allows you to configure security settings related to user authentication tokens, session management and password reset.
To set them go to `Administration > Authentication`.

![authentication](./_assets/authentication.png){.medium}

- **Only one auth token per user**: when enabled, users won't be able to be logged in on multiple devices simultaneously.
- **Auth Token Lifetime (hours)**: defines the lifetime period for authentication tokens. Set to 0 (no expiration) by default.
- **Auth Token Max Idle Time (hours)**: defines how long since the last access tokens can exist. Set to 120 hours (5 days) by default.

For LDAP authentication, see [LDAP authentication](https://store.atrocore.com/en/ldap/20164).

## Password Reset

The *Password Reset* panel controls the password reset links that users request with **Forgot Password?** on the login page and that administrators send with the [Reset Password](../01.users/index.md#password-operations) action.

<!-- To Do: add screenshot of the Password Reset panel -->
![password-reset-settings](./_assets/password-reset-settings.png){.medium}

- **Password Reset Resend Interval (minutes)**: defines how long a user has to wait before requesting another password reset link. Set to 1 minute by default.
- **Password Reset Link Lifetime (minutes)**: defines how long the password reset link sent by email stays valid. Set to 15 minutes by default.

The minimum value for both settings is 1 minute.

If a user requests a new link before the resend interval has passed, the request is rejected with a message telling the user how many minutes to wait. The resend interval does not apply to the **Reset Password** action of administrators.

Only the most recent password reset link of a user is valid – each new link replaces the previous one. A link also stops working once the password has been changed with it. A change of the link lifetime applies to links that have already been sent.

! Password reset works only if the **Connection for E-Mail Notifications** is set in the [system settings](../../01.system-settings/index.md#notifications). Otherwise, the **Forgot Password?** link is not shown on the login page.
