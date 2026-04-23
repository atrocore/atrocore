---
title: Apache Web Server Preparation
---


This guide describes how to prepare the Apache web server for the installation of AtroCore Applications.

> Installation guide is based on **Ubuntu 22.04**.

## 1. Installing Apache and Updating the Firewall
The Apache web server is among the most popular web servers in the world. It’s well documented, has an active community of users, and has been in wide use for much of the history of the web, which makes it a great default choice for hosting a web application.

Install Apache using Ubuntu’s package manager, ```apt```:
```
sudo apt update
sudo apt -y install apache2
```

Enable mod_rewrite:
```
sudo a2enmod rewrite
sudo systemctl restart apache2
```
If you have the ```ufw``` firewall enabled, you’ll need to adjust your firewall settings to allow HTTP traffic. UFW has different application profiles that you can leverage for accomplishing that. To list all currently available UFW application profiles, you can run:
```
sudo ufw app list
```
You’ll see output like this:
```
Available applications:
  Apache
  Apache Full
  Apache Secure
  OpenSSH
```
Here’s what each of these profiles mean:
* **Apache**: This profile opens only port 80 (normal, unencrypted web traffic).
* **Apache Full**: This profile opens both port 80 (normal, unencrypted web traffic) and port 443 (TLS/SSL encrypted traffic).
* **Apache Secure**: This profile opens only port 443 (TLS/SSL encrypted traffic).

So, to allow traffic on port 80 and 443, use the Apache profile:
```
sudo ufw allow in "Apache Full"
```

> **Note:** In case if you just enable firewall, don't forget to allow ssh connection, because it can be your last connection :)
```
sudo ufw allow in "OpenSSH"
```

You can verify the change with:
```
sudo ufw status
```
You’ll see output like this:
```
Status: active

To                         Action      From
--                         ------      ----
Apache Full                ALLOW       Anywhere
OpenSSH                    ALLOW       Anywhere
Apache Full (v6)           ALLOW       Anywhere (v6)
OpenSSH (v6)               ALLOW       Anywhere (v6)
```
Traffic on port 80 and 443 is now allowed through the firewall.
You can do a spot check right away to verify that everything went as planned by visiting your server’s public IP address in your web browser:
```
http://your_server_ip
```
You’ll see the default Ubuntu 22.04 Apache web page. It should look something like this:
![apache_default](./_assets/apache_default.png){.large}

## 2. Installing Database
Now, after you have the web server up and running, you need to install the database system to be able to store and manage data for your web application. Please, install PostgreSQL or MySQL. PostgreSQL is recommended.

### 2.1. Installing PostgreSQL
PostgreSQL is a popular database management system used within PHP environments.

Again, use ```apt``` to acquire and install this software:
```
sudo apt -y install postgresql postgresql-contrib
```

When you’re finished, test if you’re able to log in to the PosgreSQL console by typing:
```
sudo -u postgres psql
```
This will connect to the PosgreSQL server as the database user **postgres**. You should see output like this:
```
psql (14.11 (Ubuntu 14.11-0ubuntu0.22.04.1))
Type "help" for help.

postgres=#
```
To exit the PosgreSQL console, do:
```
Ctrl + D
```

### 2.2. Installing MySQL
MySQL is a popular database management system used within PHP environments.

Again, use ```apt``` to acquire and install this software:
```
sudo apt -y install mysql-server
```
When the installation is finished, it’s recommended that you run a security script that comes pre-installed with MySQL. This script will remove some insecure default settings and lock down access to your database system. Start the interactive script by running:
```
sudo mysql_secure_installation
```
This will ask if you want to configure the ```VALIDATE PASSWORD PLUGIN```.

> **Note:** Enabling this feature is something of a judgment call. If enabled, passwords which don’t match the specified criteria will be rejected by MySQL with an error. It is safe to leave validation disabled, but you should always use strong, unique passwords for database credentials.

Answer ```Y``` for yes, or anything else to continue without enabling.
```
VALIDATE PASSWORD PLUGIN can be used to test passwords
and improve security. It checks the strength of password
and allows the users to set only those passwords which are
secure enough. Would you like to setup VALIDATE PASSWORD plugin?

Press y|Y for Yes, any other key for No:
```
If you answer “yes”, you’ll be asked to select a level of password validation. Keep in mind that if you enter ```2``` for the strongest level, you will receive errors when attempting to set any password which does not contain numbers, upper and lowercase letters, and special characters, or which is based on common dictionary words.

Regardless of whether you chose to set up the ```VALIDATE PASSWORD PLUGIN```, your server will next ask you to select and confirm a password for the MySQL **root** user. This is not to be confused with the **system root**. The **database root** user is an administrative user with full privileges over the database system.

If you enabled password validation, you’ll be shown the password strength for the root password you just entered and your server will ask if you want to continue with that password. If you are happy with your current password, enter ```Y```. For the rest of the questions, press ```Y``` and hit the ENTER key at each prompt.

When you’re finished, test if you’re able to log in to the MySQL console by typing:
```
sudo mysql
```
This will connect to the MySQL server as the administrative database user **root**, which is inferred by the use of sudo when running this command. You should see output like this:
```
Welcome to the MySQL monitor.  Commands end with ; or \g.
Your MySQL connection id is 9
Server version: 8.0.25-0ubuntu0.20.04.1 (Ubuntu)

Copyright (c) 2000, 2021, Oracle and/or its affiliates.

Oracle is a registered trademark of Oracle Corporation and/or its
affiliates. Other names may be trademarks of their respective
owners.

Type 'help;' or '\h' for help. Type '\c' to clear the current input statement.

mysql>
```
To exit the MySQL console, do:
```
Ctrl + D
```

## 3. Installing PHP
You have Apache installed to serve your content and MySQL installed to store and manage your data. PHP is the component of our setup that will process code to display dynamic content to the final user. In addition to the ```php``` package, you’ll need ```php-mysql```, a PHP module that allows PHP to communicate with databases. You’ll also need ```libapache2-mod-php``` to enable Apache to handle PHP files and others required modules for AtroCore Application.

To install these packages, run:
```
sudo apt -y install php libapache2-mod-php php-curl php-gd php-mbstring php-xml php-zip php-imagick
```
also, in case of postgresql, run:
```
sudo apt -y install php-pgsql
```
or, in case of mysql, run:
```
sudo apt -y install php-mysql
```

Once the installation is finished, you can run the following command to confirm your PHP version:
```
php -v
```
You should see output like this:
```
PHP 7.4.3 (cli) (built: Oct  6 2020 15:47:56) ( NTS )
Copyright (c) The PHP Group
Zend Engine v3.4.0, Copyright (c) Zend Technologies
    with Zend OPcache v7.4.3, Copyright (c), by Zend Technologies
```

## 4. Configuring PHP (Webserver Only)
Apply the following settings only for the Apache webserver PHP configuration:
```
sudo printf "post_max_size = 20M\nupload_max_filesize = 20M\nmax_execution_time = 180\nmax_input_time = 180\nmemory_limit = 256M" >> /etc/php/7.4/apache2/php.ini
sudo service apache2 restart
```

> * If you are using a another version of PHP, provide the correct path to **php.ini**.
> * These limits are required for web requests (file uploads, script execution time, memory usage).
> * Do not apply these changes to PHP CLI.
> * CLI must keep: ```memory_limit = -1```. This ensures long-running console commands and migrations are not restricted by memory limits.

## 5. Creating a Virtual Host for your Application
When using the Apache web server, you can create virtual hosts to encapsulate configuration details and host more than one domain from a single server. In this guide, we’ll set up a domain called **your_domain**, but you should **replace this with your own domain name**.

Apache on Ubuntu 20.04 has one server block enabled by default that is configured to serve documents from the ```/var/www/html``` directory. While this works well for a single site, it can become unwieldy if you are hosting multiple sites. Instead of modifying ```/var/www/html```, we’ll create a directory structure within ```/var/www``` for the ***your_domain*** site, leaving ```/var/www/html``` in place as the default directory to be served if a client request doesn’t match any other sites.

Create the directory for **your_domain** as follows:
```
sudo mkdir /var/www/your_domain
```

Create an index.html file in that location so that we can test that the virtual host works as expected:
```
nano /var/www/your_domain/index.html
```
Include the following content in this file:
```
<html>
  <head>
    <title>Your website</title>
  </head>
  <body>
    <h1>Hello World!</h1>
    <p>This is the landing page of <strong>your_domain</strong>.</p>
  </body>
</html>
```
Save and close the file when you’re done. If you’re using ```nano```, you can do that by pressing ```CTRL+X```, then ```Y``` and ```ENTER```.

Next, assign ownership of the directory:
```
sudo chown -R www-data:www-data /var/www/your_domain
```
> Ubuntu and Debian use www-data as a standard user for the webserver. This can also be one of the following: www, apache2, psacln etc

Then, open a new configuration file in Apache’s ```sites-available``` directory using your preferred command-line editor. Here, we’ll use ```nano```:
```
sudo nano /etc/apache2/sites-available/your_domain.conf
```
This will create a new blank file. Paste in the following bare-bones configuration:
```
<VirtualHost *:80>
    ServerName your_domain
    ServerAlias www.your_domain
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/your_domain/public
    <Directory var/www/your_domain/public/>
    AllowOverride All
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

Now use ```a2ensite``` to enable the new virtual host:
```
sudo a2ensite your_domain
```

You might want to disable the default website that comes installed with Apache. This is required if you’re not using a custom domain name, because in this case Apache’s default configuration would overwrite your virtual host. To disable Apache’s default website, type:
```
sudo a2dissite 000-default
```

To make sure your configuration file doesn’t contain syntax errors, run:
```
sudo apache2ctl configtest
```

Finally, reload Apache so these changes take effect:
```
sudo systemctl reload apache2
```

Now go to your browser and access your server’s domain name or IP address once again:
```
http://your_domain
```

> **Note:** Make sure that you configured your domain name

You’ll see a page like this:

![hello-world](./_assets/hello-world.png){.large}

If you see this page, it means your Apache virtual host is working as expected.


## 6. Install AtroCore Application
Now your server is prepared for the installation of the AtroCore Application.
