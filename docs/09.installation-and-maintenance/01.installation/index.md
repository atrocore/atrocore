---
title: Installation
---

This Installation Guide is prepared for **Ubuntu** Operating System. Of course, you can use any UNIX-based system, but in this case you need to adopt this manual by yourself.
A **dedicated (virtual) server with root permissions** is recommended. However, the installation on a shared or managed hosting is also possible, as long as the environment is configured correctly — see [Installation on Shared / Managed Hosting](../01.installation/06.shared-hosting).

> **Supported PHP versions: 8.4 and 8.5.** PHP 8.5 is the version shipped with Ubuntu 26.04 LTS and is recommended for new installations.

## 1. Linux Server Preparation
This section contains links to the guides that describe how to prepare your server for the installation of the AtroCore Application.

> Please note that you don't need to perform [step 2](#2-install-atrocore-atropim-application) of the current guide and [optional step](#optional-install-the-xattr-extension-for-php) with the Docker

* [Docker configuration](../01.installation/01.docker-configuration)
* [Apache web server preparation](../01.installation/02.apache-web-server) (RECOMMENDED)
* [Nginx web server preparation](../01.installation/03.nginx-web-server)
* [Installation on Shared / Managed Hosting](../01.installation/06.shared-hosting)

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

> Git may be used for this step, so make sure that [git](https://git-scm.com/book/en/v2/Getting-Started-Installing-Git) is installed. Please note, it is still possible to install the application without having `git`.

#### If you want to install AtroPIM with demo data
> Demo data can be installed only for MySQL database system.

```
sudo git clone https://github.com/atrocore/skeleton-pim.git .
```

#### If you want to install AtroPIM without demo data
```
sudo git clone https://github.com/atrocore/skeleton-pim-no-demo.git .
```

#### If you want to install AtroCore with demo data
> Demo data can be installed only for MySQL database system.

```
sudo git clone https://github.com/atrocore/skeleton-atrocore.git .
```

#### If you want to install AtroCore without demo data
```
sudo git clone https://github.com/atrocore/skeleton-atrocore-no-demo.git .
```

#### Installation without `git`
If you have no git installed you may still copy the files to the project folder manually. You can download the files from one of these repositories:
- https://github.com/atrocore/skeleton-pim
- https://github.com/atrocore/skeleton-pim-no-demo
- https://github.com/atrocore/skeleton-atrocore
- https://github.com/atrocore/skeleton-atrocore-no-demo

### 4. Create database and user

User must have all privileges for the database, which should be used for the AtroCore Application. The installer creates the database itself if it does not exist and the user is allowed to create databases. Otherwise create the database and the user with all privileges by executing next few commands:

> PostgreSQL is recommended to use.

#### 4.1. Create PostgreSQL database and user

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

#### 4.2. Create MySQL database and user

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

### 5. Run the installer

The installation is done from the command line in the project directory:
```
sudo php atrocore-installer.phar self-update && sudo php atrocore-installer.phar install-atrocore
```

The installer installs the system files, checks the PHP of the command line and of the web server, prepares the database, creates the administrator and fills the system with its default data. A skeleton with demo data also gets its demo data loaded. It asks for the database access, the URL of the site, the administrator and the initial settings. When the installation is done, it shows the URL, the username and the password of the administrator. If no administrator password is given, a strong one is generated.

The installation can also be done by one command, without any question. Every parameter is given as an option or as an environment variable:
```
sudo php atrocore-installer.phar install-atrocore --no-interaction \
  --db-driver=pgsql --db-host=localhost --db-name=your_database --db-user=your_user --db-password=your_password \
  --site-url=https://pim.example.com --admin-username=admin
```

| Option | Environment variable | Default |
|---|---|---|
| `--db-driver` | `ATRO_DB_DRIVER` | `pgsql` (`pgsql` or `mysql`) |
| `--db-host` | `ATRO_DB_HOST` | `localhost` |
| `--db-port` | `ATRO_DB_PORT` | `5432` for PostgreSQL, `3306` for MySQL |
| `--db-name` | `ATRO_DB_NAME` | required |
| `--db-user` | `ATRO_DB_USER` | required |
| `--db-password` | `ATRO_DB_PASSWORD` | required |
| `--site-url` | `ATRO_SITE_URL` | required |
| `--admin-username` | `ATRO_ADMIN_USERNAME` | `admin` |
| `--admin-password` | `ATRO_ADMIN_PASSWORD` | generated |
| `--language` | `ATRO_LANGUAGE` | `en_US` |
| `--timezone` | `ATRO_TIMEZONE` | `UTC` |
| `--reporting` | `ATRO_REPORTING` | `no` — send anonymous error reports to AtroCore |

Pass the passwords as environment variables rather than as options: an option is visible in the list of processes and in the shell history.

With `--json` the result is printed to the standard output as JSON (`status`, `siteUrl`, `admin.username`, `admin.password`, `webUser`, `warnings`), everything else goes to the standard error output.

The installer checks the web server via the URL of the site: its PHP, by requesting a temporary script in `public/`, and the URL rewriting, by requesting a path under `/api/` that has to reach `index.php`. If the site is not reachable from the server yet, skip the check with `--skip-web-check`.

The exit code of the installer tells what happened:

| Code | Meaning |
|---|---|
| 0 | The system is installed. |
| 1 | The installation failed. |
| 2 | A parameter is missing or invalid. |
| 3 | The PHP of the command line or of the web server does not meet the requirements. |
| 4 | The database cannot be connected to or created. |
| 5 | The database is not empty: the system is already installed or the database belongs to something else. It is not touched. |
| 6 | The web server cannot be checked via the URL of the site, or the URL rewriting is not configured. |

### 6. Change recursively the user and group ownership for your project files

The installer ran as root, so make the web server user the owner of the project files:
```
sudo chown -R www-data:www-data /var/www/my-atrocore-project/
```
> Ubuntu and Debian use **www-data** as a standard user for the webserver. This can also be one of the following: www, apache2, psacln etc. The installer shows the web server user it detected.

### 7. Change the permissions for project files
```
sudo find . -type d -exec chmod 755 {} + && sudo find . -type f -exec chmod 644 {} +;
```
```
sudo find data upload public -type d -exec chmod 775 {} + && sudo find data upload public -type f -exec chmod 664 {} +
```

**AppArmor (Ubuntu 26.04+):** AppArmor is enabled by default and restricts what directories Ghostscript (`gs`) can access. If the `gs` profile is active, it must be granted read access to the uploaded files and read/write access to the temporary directories used while a PDF page is rendered. Check whether the profile is loaded:

```bash
sudo aa-status --filter.profiles=gs
```

Output can be similar to the following:

```
apparmor module is loaded.
185 profiles are loaded.
1 profiles are in enforce mode.
   gs
```

If `gs` is in the enforced mode, add the required path rules and reload the profile:

```bash
sudo cat >> /etc/apparmor.d/local/gs << 'EOF'
/var/www/<my-atrocore-project>/upload/files/** r,
/var/www/<my-atrocore-project>/upload/files/.img-from-pdf/** rw,
/var/www/<my-atrocore-project>/data/.pdf-thumbnail-tmp/** rw,
/var/www/<my-atrocore-project>/data/.local-storage-tmp/** rw,
/var/www/<my-atrocore-project>/data/.thumbnail-tmp/** r,
EOF
sudo apparmor_parser -r /etc/apparmor.d/gs
```

> Replace `my-atrocore-project` with the actual project name.

### 8. Configure the crontab
   8.1. Open crontab for your webserver user, which is www-data in our case:
```
crontab -e -u www-data
```
   8.2. Add the following configuration:
```
* * * * * /usr/bin/php /var/www/my-atrocore-project/console.php cron
```
> Please consider that `/usr/bin/php` is the correct path to PHP in our case. You may have other path. "cron" is the required parameter and should be definitely included for appropriate functioning.

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
> **Note:** Depending on your setup, php.ini might be located in different places. Common locations include /etc/php/{PHP_VERSION}/apache2/php.ini for Apache or /etc/php/{PHP_VERSION}/cli/php.ini for command-line PHP. Replace {PHP_VERSION} with your PHP version (e.g., 8.4, 8.5).

Restart PHP-FPM/Apache:
```
sudo systemctl restart apache2
```
or
```
sudo systemctl restart php7.4-fpm
```
