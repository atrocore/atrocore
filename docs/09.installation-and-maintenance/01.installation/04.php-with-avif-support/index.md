---
title: Installing PHP with AVIF image format support
---

This guide describes how to compile and install PHP with AVIF image format support on Ubuntu/Debian systems.

> Installation guide is based on **Ubuntu 22.04/24.04** and **Debian 11/12**.

## Why Compile PHP with AVIF Support?

AtroCore applications require robust image processing capabilities to handle user-uploaded content, generate thumbnails, and optimize media delivery. AVIF (AV1 Image File Format) is a modern image format that provides superior compression and quality compared to traditional formats like JPEG and PNG.

### Benefits of AVIF Support

- **Significantly smaller file sizes** - up to 50% smaller than JPEG at the same quality level
- **Better image quality** at lower file sizes, improving visual presentation
- **Support for HDR and wide color gamut** for modern displays
- **Faster page loading times** due to reduced bandwidth requirements

### Why Standard PHP Packages Are Insufficient

Most PHP packages available through Ubuntu/Debian repositories (`apt install php`) do not include AVIF support in the GD extension. This limitation prevents AtroCore applications from:

- **Processing and transforming AVIF files** for display optimization
- **Generating thumbnails** from AVIF source images
- **Converting between formats** (AVIF, JPEG, PNG, WebP) as needed by the application

### The Impact on Your AtroCore Installation

Modern mobile devices and cameras increasingly save images in AVIF format by default. Without AVIF support in PHP:

- Thumbnail generation for different sizes is not available for AVIF files
- The system cannot leverage modern image optimization techniques

**Compiling PHP from source with the `--with-avif` flag is required** to enable these critical image processing capabilities for your AtroCore installation.

## 1. Installing Build Dependencies

Before compiling PHP, you need to install all required build tools and development libraries.

Update your package list and install the dependencies:

```bash
sudo apt-get update && sudo apt-get install -y \
  build-essential \
  autoconf \
  libtool \
  bison \
  re2c \
  pkg-config \
  libxml2-dev \
  libsqlite3-dev \
  libcurl4-openssl-dev \
  libssl-dev \
  libpq-dev \
  libzip-dev \
  zlib1g-dev \
  libsodium-dev \
  libfreetype-dev \
  libjpeg-dev \
  libpng-dev \
  libwebp-dev \
  libavif-dev \
  libmagickwand-dev \
  libonig-dev \
  apache2-dev
```

> **Note:** The `apache2-dev` package provides `apxs2`, which is required for building the Apache PHP module.

## 2. Downloading PHP Source Code

Clone the PHP source code from the official repository. This example uses PHP 8.4.14:

```bash
git clone https://github.com/php/php-src.git --branch=php-8.4.14
cd php-src
```

> **Note:** You can replace `php-8.4.14` with any other PHP version branch you need.

## 3. Building PHP Configuration Script

Generate the configuration script:

```bash
./buildconf --force
```

## 4. Configuring PHP

Configure PHP with the required extensions and Apache support. The `--with-apxs2` option enables Apache module compilation:

```bash
./configure \
  --with-apxs2=/usr/bin/apxs2 \
  --enable-mbstring \
  --enable-exif \
  --enable-ftp \
  --enable-gd \
  --with-freetype \
  --with-jpeg \
  --with-avif \
  --with-webp \
  --with-curl \
  --with-pgsql \
  --with-pdo-pgsql \
  --with-zip \
  --with-zlib \
  --with-sodium \
  --with-openssl
```

> **Note:** If `apxs2` is located elsewhere on your system, find it using `which apxs2` or `which apxs` and adjust the path accordingly.

The configuration script will check your system for all required dependencies. If any are missing, it will report an error.

## 5. Compiling PHP

Once configuration is complete, compile PHP:

```bash
make -j$(nproc)
```

> **Note:** The `-j$(nproc)` flag uses all available CPU cores to speed up compilation.

This process may take several minutes depending on your system.

## 6. Installing PHP

Install the compiled PHP binaries and Apache module:

```bash
sudo make install
```

This will:
- Install PHP CLI to `/usr/local/bin/php`
- Install the Apache PHP module (`libphp.so`)
- Copy configuration files

Copy the recommended PHP configuration file:

```bash
sudo cp php.ini-production /usr/local/lib/php.ini
```

> **Note:** Use `php.ini-development` instead if you're setting up a development environment.

## 7. Configuring Apache for PHP

After installation, you need to configure Apache to use the newly compiled PHP module.

### 7.1. Verify Apache Module Installation

The `make install` command should have automatically added the PHP module to Apache. Verify by checking:

```bash
apache2ctl -M | grep php
```

You should see output like:
```
php_module (shared)
```

### 7.2. Configure PHP Handler

Create or edit the PHP configuration file for Apache:

```bash
sudo nano /etc/apache2/mods-available/php.conf
```

Add the following content:

```apache
<FilesMatch ".+\.ph(ar|p|tml)$">
    SetHandler application/x-httpd-php
</FilesMatch>
<FilesMatch ".+\.phps$">
    SetHandler application/x-httpd-php-source
</FilesMatch>

<IfModule mod_userdir.c>
    <Directory /home/*/public_html>
        php_admin_flag engine Off
    </Directory>
</IfModule>
```

Enable the PHP module:

```bash
sudo a2enmod php
```

### 7.3. Update PHP Configuration

Edit the PHP configuration file:

```bash
sudo nano /usr/local/lib/php.ini
```

Add or update the following settings for optimal performance:

```ini
post_max_size = 20M
upload_max_filesize = 20M
max_execution_time = 180
max_input_time = 180
memory_limit = 256M
```

### 7.4. Restart Apache

Apply the changes by restarting Apache:

```bash
sudo systemctl restart apache2
```

## 8. Verify AVIF Support

Check that AVIF support is enabled in the GD extension:

```bash
php -r "print_r(gd_info());"
```

Look for the following in the output:

```
Array
(
    ...
    [AVIF Support] => 1
    [WebP Support] => 1
    ...
)
```

## Conclusion

You now have PHP compiled with AVIF support and integrated with Apache. This allows your web applications to serve and process modern AVIF image formats for better compression and quality.
