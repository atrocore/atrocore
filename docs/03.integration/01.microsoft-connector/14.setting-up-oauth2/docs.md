---
title: Setting Up OAuth2
taxonomy:
    category: docs
---


In order to setup Mail with Microsoft OAuth2 SMTP, you must first register your application, to do this follow these steps:
1) Log in to the https://entra.microsoft.com/ page with administrator rights.
2) In the Applications section, select App registration and click New registration.

![Register an application](_assets/register-an-application.png){.large}

![Build the application](_assets/build-the-app.png){.large}

3) Secret key

![Secret key](_assets/secret-key.png){.large}

![Add a secret](_assets/add-a-secret.png){.large}

![Copy value](_assets/add-a-secret1.png){.large}

Copy the value (It is the Client Secret on PIM)

4) Permissions

![API permissions](_assets/permissions.png){.large}

![Add a permission](_assets/add-permission.png){.large}

![Add a permission](_assets/request-api-permissions.png){.large}

![Add a permission](_assets/request-api-permissions1.png){.large}

![Add a permission](_assets/add-permission1.png){.large}

![Add a permission](_assets/configure-permissions.png){.large}

5) Authentication

![Add a platform](_assets/add-platform.jfif){.large}

![Add a platform](_assets/configure-web.jfif){.large}

The Redirect Url must be in this format 'https://YOUR_PROJECT/?entryPoint=OauthSmtpCallback'

6) Configuration in PIM

![Microsoft OAUTH](_assets/microsoft-oauth.png){.large}

These are the values you need to set on PIM connection
Server: smtp.office365.com
Port: 587
Auth Type: OAuth
Username: your mail address
Client Id:  obtained in step 2, it is ‘Application (client) ID’
Client Secret: obtained in step 3, it is the ‘Secret Value’
Oauth Authorize URL : https://login.microsoftonline.com/{TenantID}/oauth2/v2.0/authorize
Oauth Token URL: https://login.microsoftonline.com/{TenantID}/oauth2/v2.0/token
TenantID is obtained in step 1, it is ‘Directory (Tenant) ID’
From Address: your mail address
From Name: your name
