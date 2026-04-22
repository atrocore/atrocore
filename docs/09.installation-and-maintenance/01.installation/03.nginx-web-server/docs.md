---
title: Nginx Web Server Preparation
---

This guide describes how to prepare the Nginx web server for the installation of AtroCore Applications.

> Installation guide is based on **Ubuntu 20.04**.

## 1. Install the Nginx Web Server
In order to display web pages to our site visitors, we are going to employ Nginx, a high-performance web server.

Install Nginx using Ubuntu’s package manager, ```apt```:
```
sudo apt update
sudo apt -y install nginx
```

If you have the ```ufw``` firewall enabled, as recommended in our initial server setup guide, you will need to allow connections to Nginx. Nginx registers a few different UFW application profiles upon installation. To check which UFW profiles are available, run:
```
sudo ufw app list
```
You’ll see output like this:
```
Available applications:
  Nginx Full
  Nginx HTTP
  Nginx HTTPS
  OpenSSH
```
Here’s what each of these profiles mean:
* **Nginx HTTP**: This profile opens only port 80 (normal, unencrypted web traffic).
* **Nginx Full**: This profile opens both port 80 (normal, unencrypted web traffic) and port 443 (TLS/SSL encrypted traffic).
* **Nginx HTTPS**: This profile opens only port 443 (TLS/SSL encrypted traffic).

So, to allow traffic on port 80 and 443, use the Nginx profile:
```
sudo ufw allow in "Nginx Full"
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
Nginx Full                 ALLOW       Anywhere
OpenSSH                    ALLOW       Anywhere
Nginx Full (v6)            ALLOW       Anywhere (v6)
OpenSSH (v6)               ALLOW       Anywhere (v6)
```
Traffic on port 80 and 443 is now allowed through the firewall.
You can do a spot check right away to verify that everything went as planned by visiting your server’s public IP address in your web browser:
```
http://your_server_ip
```
You’ll see the default Ubuntu 22.04 Nginx web page. It should look something like this:
![nginx_default](./_assets/nginx_default.png){.large}

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

## 3. Install PHP
You have Nginx installed to serve your content and MySQL installed to store and manage your data. PHP is the component of our setup that will process code to display dynamic content to the final user. In addition to the ```php-fpm``` package, you’ll need ```php-mysql```, a PHP module that allows PHP to communicate with databases. You’ll also need enable required modules for AtroCore Application.

To install these packages, run:
```
sudo apt -y install php-fpm php-curl php-gd php-mbstring php-xml php-zip php-imagick
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
Apply the following settings only for the webserver PHP configuration:
```
sudo printf "post_max_size = 20M\nupload_max_filesize = 20M\nmax_execution_time = 180\nmax_input_time = 180\nmemory_limit = 256M" >> /etc/php/7.4/fpm/php.ini
sudo systemctl restart php7.4-fpm
```

> * If you are using a another version of PHP, provide the correct path to **php.ini**.
> * These limits are required for web requests (file uploads, script execution time, memory usage).
> * Do not apply these changes to PHP CLI.
> * CLI must keep: ```memory_limit = -1```. This ensures long-running console commands and migrations are not restricted by memory limits.

## 5. Configuring Nginx to Use the PHP Processor
When using the Nginx web server, we can create server blocks to encapsulate configuration details and host more than one domain on a single server. In this guide, we’ll use **your_domain** as an example domain name.

On Ubuntu 22.04, Nginx has one server block enabled by default and is configured to serve documents out of a directory at ```/var/www/html```. While this works well for a single site, it can become difficult to manage if you are hosting multiple sites. Instead of modifying ```/var/www/html```, we’ll create a directory structure within ```/var/www``` for the **your_domain** website, leaving ```/var/www/html``` in place as the default directory to be served if a client request doesn’t match any other sites.

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

Then, open a new configuration file in Nginx’s ```sites-available``` directory using your preferred command-line editor. Here, we’ll use ```nano```:
```
sudo nano /etc/nginx/sites-available/your_domain
```
This will create a new blank file. Paste in the following bare-bones configuration:
```
server {
  listen 80;
  server_name your_domain;
  root /var/www/your_domain/public;

  index index.php index.html;

  client_max_body_size 50M;

  location ~ ((.*)\.sql|composer\.json)$ {
    deny all;
  }

  location ~ /\.ht {
    deny all;
  }

  location / {
    try_files $uri $uri/ @router;
    index index.html index.php;
    error_page 403 = @router;
    error_page 404 = @router;
  }

  location @router {
    rewrite ^/(.*)$ /index.php?treoq=$1;
  }

  location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
  }

}
```

Activate your configuration by linking to the config file from Nginx’s ```sites-enabled``` directory:
```
sudo ln -s /etc/nginx/sites-available/your_domain /etc/nginx/sites-enabled/
```

Then, unlink the default configuration file from the ```/sites-enabled/``` directory:
```
sudo unlink /etc/nginx/sites-enabled/default
```

This will tell Nginx to use the configuration next time it is reloaded. You can test your configuration for syntax errors by typing:
```
sudo nginx -t
```

When you are ready, reload Nginx to apply the changes:
```
sudo systemctl reload nginx
```

Now go to your browser and access your server’s domain name or IP address once again:
```
http://your_domain
```

> **Note:** Make sure that you configured your domain name

You’ll see a page like this:

![hello-world](./_assets/hello-world.png){.large}

If you see this page, it means your Nginx server block is working as expected.


## 6. Install AtroCore Application
Now your server is prepared for the installation of the AtroCore Application.
