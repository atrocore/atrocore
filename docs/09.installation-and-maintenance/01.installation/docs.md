---
title: Installation
---

This Installation Guide is prepared for **Ubuntu** Operating System. Of course, you can use any UNIX-based system, but in this case you need to adopt this manual by yourself.
Before your start make sure your have a **dedicated (virtual) server with root permission**. On a usual hosting the system will not work!

## 1. Linux Server Preparation
This section contains links to the guides that describe how to prepare your server for the installation of the AtroCore Application.

> Please note that you don't need to perform [step 2](#2-install-atrocore-atropim-application) of the current guide and [optional step](#optional-install-the-xattr-extension-for-php) with the Docker

* [Docker configuration](../01.installation/01.docker-configuration)
* [Apache web server preparation](../01.installation/02.apache-web-server) (RECOMMENDED)
* [Nginx web server preparation](../01.installation/03.nginx-web-server)

## 2. Install AtroCore (AtroPIM) Application
This section describes how to install AtroCore Application on the prepared web server.

### 1. Create your project directory (if not exists yet)
> If the directory already exists, remove everything inside the directory.

To create the directory, run the command:
```
mkdir /var/www/my-atrocore-project
```
> **my-atrocore-project** – project name

### 2. Go inside of your project directory
```
cd /var/www/my-atrocore-project
```

### 3. Download project files

> Git may be used for this step, so make sure that [git](https://git-scm.com/book/en/v2/Getting-Started-Installing-Git) is installed. Please note, it is still possible to install the application without having `git` (see 3.6).

> It is essential, that you use the composer version, which is embedded in our software, because this version contains some of our modifications needed for backup and restoring of the system files and the database. That is why `php composer.phar update` is used. Please **DO NOT** use composer, which is installed on your server as it does not contain the required modifications.

#### If you want to install AtroPIM with demo data
> Demo data can be installed only for MySQL database system.

run
```
sudo git clone https://gitlab.atrocore.com/atrocore/skeleton-pim.git . && sudo php composer.phar self-update && sudo php composer.phar update
```

#### If want to install the AtroPIM without demo data

run
```
sudo git clone https://gitlab.atrocore.com/atrocore/skeleton-pim-no-demo.git . && sudo php composer.phar self-update && sudo php composer.phar update
```

#### If you want to install AtroCore with demo data
run
```
sudo git clone https://gitlab.atrocore.com/atrocore/skeleton-atrocore.git . && sudo php composer.phar self-update && sudo php composer.phar update
```

#### If want to install the AtroCore without demo data

run
```
sudo git clone https://gitlab.atrocore.com/atrocore/skeleton-atrocore-no-demo.git . && sudo php composer.phar self-update && sudo php composer.phar update
```

#### Installation without `git`
If you have no git installed you may still copy the files to the project folder manually.

You can download the files from one of these repositories:
- https://gitlab.atrocore.com/atrocore/skeleton-pim
- https://gitlab.atrocore.com/atrocore/skeleton-pim-no-demo
- https://gitlab.atrocore.com/atrocore/skeleton-atrocore
- https://gitlab.atrocore.com/atrocore/skeleton-atrocore-no-demo

Then upload the files to your project folder and run
```
sudo php composer.phar self-update && sudo php composer.phar update
```

### 4. Change recursively the user and group ownership for your project files
```
sudo chown -R www-data:www-data /var/www/my-atrocore-project/
```
> Ubuntu and Debian use **www-data** as a standard user for the webserver. This can also be one of the following: www, apache2, psacln etc.

### 5. Change the permissions for project files
```
sudo find . -type d -exec chmod 755 {} + && sudo find . -type f -exec chmod 644 {} +;
```
```
sudo find data upload public -type d -exec chmod 775 {} + && sudo find data upload public -type f -exec chmod 664 {} +
```
### 6. Configure the crontab
   6.1. Open crontab for your webserver user, which is www-data in our case:
```
crontab -e -u www-data
```
   6.2. Add the following configuration:
```
* * * * * /usr/bin/php /var/www/my-atrocore-project/console.php cron
```
> Please consider that `/usr/bin/php` is the correct path to PHP in our case. You may have other path. "cron" is the required parameter and should be definitely included for appropriate functioning.

### 7. Create database and user

User must have all privileges for the database, which should be used for the AtroCore Application. You can create database and user with all privileges by executing next few commands:

> PostgreSQL is recommended to use.

#### 7.1. Create PostgreSQL database and user

```
-- Connect to PostgreSQL
sudo -u postgres psql

-- Create a new database
CREATE DATABASE your_database;

-- Create a new user
CREATE USER your_user WITH PASSWORD 'your_password';

-- Grant all privileges on the new database to the new user
GRANT ALL PRIVILEGES ON DATABASE your_database TO your_user;

-- Connect to the new database
\c your_database

-- Grant all privileges on the public schema to the new user
GRANT ALL ON SCHEMA public TO your_user;
```

#### 7.2. Create MySQL database and user

```
-- Connect to MySQL
sudo mysql

-- Create a new database
CREATE DATABASE your_database;

-- Create a new user
CREATE USER 'your_user'@'localhost' IDENTIFIED BY 'your_password';

-- Grant all privileges on the new database to the new user
GRANT ALL ON your_database.* TO your_user@localhost WITH GRANT OPTION;
```

### 8. Go to your Project URL to start the installation wizard

Start the installation wizard for your AtroCore Application in the web interface from your URL: http://YOUR_PROJECT/ . Follow the instructions in the wizard.

## Optional. Install the xattr extension for PHP

> **Note:** The extension must be installed for the system to be able to mark already indexed files in the file system. For a Docker environment extension is already installed.

Install xattr development files:
```
sudo apt install libattr1-dev
```

Install PECL and PHP Development Tools:
```
sudo apt install php-dev php-pear
```

Install xattr extension via PECL:
```
sudo pecl install xattr
```

Enable the extension:
```
extension=xattr.so
```
> **Note:** Depending on your setup, php.ini might be located in different places. Common locations include /etc/php/{PHP_VERSION}/apache2/php.ini for Apache or /etc/php/{PHP_VERSION}/cli/php.ini for command-line PHP. Replace {PHP_VERSION} with your PHP version (e.g., 7.4, 8.0, etc.).

Restart PHP-FPM/Apache:
```
sudo systemctl restart apache2
```
or
```
sudo systemctl restart php7.4-fpm
```
