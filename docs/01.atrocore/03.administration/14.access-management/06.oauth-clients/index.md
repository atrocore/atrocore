---
title: OAuth Clients
---

AtroCore is an OAuth 2.0 authorization server. Every application that calls the REST API – an integration, a web or mobile application, a tool like Postman – is registered as an OAuth client and receives access tokens for it. How applications request and use the tokens is described in [REST API Authentication](../../../../10.developer-guide/10.rest-api/index.md#authentication).

To manage the clients go to `Administration / Authentication / OAuth Clients`. Only administrators manage the clients: a client with a user acts with the permissions of that user, so its secret gives the access of that user.

<!-- To Do: add screenshot of the OAuth Client detail view -->

## Fields

- **Name**, **Description**: shown in the list of clients and, for **Consent Required**, to the user who allows the access.
- **Active**: an inactive client gets no tokens. Deactivating a client revokes all tokens issued to it.
- **Confidential**: the client authenticates with its client secret. Enable it for an integration or an application with a server; disable it for a browser or mobile application, which cannot keep a secret.
- **Client Credentials**: the client obtains access tokens on its own behalf, without a user signing in. Requires **Confidential**.
- **User**: the user a client with **Client Credentials** acts as. Its tokens have the permissions of this user in the AtroCore REST API, so give the user a role with only the permissions the integration needs. Leave it empty for a client that uses AtroCore only to authenticate to another application: its tokens are issued, but the AtroCore REST API rejects them.
- **Authorization Code**: the client obtains access tokens on behalf of a user who logs in on the AtroCore login form. PKCE with the `S256` method is required.
- **Refresh Token**: together with the access token the client gets a refresh token and renews the access token without a new login. Available with **Authorization Code**.
- **Redirect URIs**: the addresses AtroCore returns the user to after the login, required for **Authorization Code**. Each one is an `https` or `http` URI, or a custom scheme of a native application in reverse domain notation (e.g. `com.example.app:/callback`), without a fragment. Other schemes, such as `javascript:` or `data:`, are rejected. The `redirect_uri` of an authorization request has to match one of them exactly.
- **Consent Required**: the user confirms that the application gets access to the account. Disable it only for the applications of your own organization.
- **Access Token Lifetime, min**: how long an access token of the client is valid, from 1 to 1440 minutes (60 by default). An application that checks the tokens itself, without asking AtroCore, notices a revocation or a changed claim only when the token expires: keep the lifetime short for such clients.
- **Refresh Token Lifetime, min**: how long a refresh token of the client is valid, from 1 to 525600 minutes (4320, i.e. 3 days, by default). Available with **Refresh Token**. Every refresh issues a new refresh token, so the lifetime counts from the last refresh: for the AtroCore UI client it is how long a session can stay unused and still be continued.

A client needs at least one grant: **Client Credentials** or **Authorization Code**.

The **ID** of the client is its client ID.

## Client Secret

A confidential client needs a secret. Use the action **Generate Secret** in the list or on the detail view of the client and copy the secret: AtroCore stores only its hash and shows the secret only once. Generating a new secret makes the previous one invalid and revokes all tokens issued to the client.

## Custom Claims

The access tokens of AtroCore are JSON Web Tokens. The panel **Claims** of a client adds claims of your own to them: every access token issued to the client carries exactly the claims configured there. An application that receives the token reads them without asking AtroCore, for example the role a partner application grants to the client.

A claim has a **Name** and a **Value**, both text. A name is unique per client. The claims that AtroCore sets itself – `iss`, `sub`, `aud`, `exp`, `nbf`, `iat`, `jti` and `scopes` – cannot be used.

A change of the claims applies to the tokens issued after it; the tokens already issued keep their claims until they expire. To apply a change at once, generate a new secret: it revokes the issued tokens.

## Deleting a Client

Deleting a client revokes all tokens issued to it: the application loses access immediately.

## AtroCore UI Client

The AtroCore user interface logs in by the OAuth client **AtroCore UI**, marked with **UI Client**. It is created on the installation. The server acts as this client: it requests and keeps the tokens, the browser has only a session cookie (see [Session Cookie](../../../../09.installation-and-maintenance/02.security-guidance/index.md#session-cookie)).

Only the name, the description, **Active** and the token lifetimes of this client can be changed, and it cannot be deleted. Deactivating it turns off the login to the user interface for all users. To turn it on again, run:

```
php console.php restore ui client
```

The command also recreates the client if it is missing.
