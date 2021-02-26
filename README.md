![logo](_assets/AtroCore_logo_color_248.png)

## What Is AtroCore?

AtroCore is an open-source software ecosystem distributed under GPLv3 License for free. AtroCore is designed for rapid development of web-based responsive business applications of any kind (ERP, PIM, CRM, DMS, MDM, DAM, etc.). It is an excellent tool for cost-effective and timely application development that comes with the powerful out-of-the-box functionality.

AtroCore is a single page application (SPA) with an API-centric, service-oriented architecture, and flexible data model based on configurable entities and relations. You can organize any data and business-related processes directly in AtroCore, many of them via a simple and user-friendly configuration.

### For Whom Is AtroCore?

AtroCore is the best fit **for businesses**, who want to:

* solve custom business problems;
* store data and organize business processes;
* set up and use a middleware to connect with the third-party systems;
* create added value and best experience for their employees, customers, and partners;
* extend the functionality of the existing software infrastructure.

### What Are AtroCore Extensions?

The following extensions are available on the AtroCore basis:
* [AtroPIM](https://github.com/atrocore/atropim)
* [AtroDAM](https://github.com/atrocore/atrodam)
* and many others extending the functionality of PIM and DAM.

Thanks to the configurable data model and layouts the application can be used as a Master Data Mangement System (MDM), if no extension is installed.

### What Is On Board?

AtroCore is a Platform for Rapid Application Development (RAD).

Here is a AtroCore package description in detail:

| Feature                     | Description                                                   |
| --------------------------- | ------------------------------------------------------------ |
| Dashboards                  | Use multiple dashboards to control all main activities in the system. |
| Module Manager              | It allows you to install and/or update any module directly from the administration panel. Just choose the version you want to use. |
| Entity Manager              | You can configure the data model directly from the administration panel, create new or edit existing entities and set relations of different types. |
| Dynamic field logic         | You can configure the conditions that make some fields invisible, read-only or editable. |
| Layout Manager              | Use it to configure any User Interface in the system or to show up the panels for related entities, via drag-and-drop. |
| Label Manager               | You can edit any label in the system, in all languages you want to use. |
| Configurable navigation     | Use the drag-and-drop functionality to set up the navigation as you wish, also separately for each user, if needed. |
| Scheduled Jobs              | You can configure, which jobs should be run by cron and at what schedule. |
| Notifications               | Set up a system or e-mail notifications for different events in the system. |
| Data import and export      | You can import or export any data to any and from any entity in the system, even those you have just created. |
| Advanced mass updates       | Choose the entries to be updated, set the new values and perform a mass update. |
| Advanced search and filters | You can configure the filters and search criteria as you wish, and save them, if you want to use the saved filters later. |
| Portals                     | Use this additional layer to give access to third parties to your system. Use portal roles to restrict their access. |
| Change Log and Stream       | See all changes to the entries (who, old and new value, when) and add your own posts with a timestamp and attachments. |
| Queue Manager               | Use it if you want to run or control processes in the background. |
| Access Control Lists (ACL)  | Enterprise Level ACL based on Teams and Roles, with access level (own, team, all). You are able to edit the permissions even for each field separately. |
| REST API                    | Integrate it with any third-party software, fully automated. |

### What Are the Advantages of Using It?

* Really quick time to market and low implementation costs!
* Configurable, flexible and customizable
* Free – 100% open source, licensed under GPLv3
* REST API
* Web-based and platform independent
* Based on modern technologies
* Good code quality
* Service-oriented architecture (SOA)
* Responsive and user-friendly UI
* Configurable (entities, relations, layouts, labels, navigation)
* Extensible with modules
* Very fast
* Easy to maintain and support
* Many out-of-the-box features
* Best for Rapid Application Development

### What Technologies Is It Based On?

AtroCore was created based on TreoCore and EspoCRM. It uses:

* PHP7 – pure PHP, without any frameworks to achieve the best possible performance,
* backbone.js – framework for SPA Frontend,
* Composer – dependency manager for PHP,
* Some libraries from Zend Framework,
* Some libraries from Symfony Framework,
* MySQL 5.

### Integrations

AtroCore has a REST API and can be integrated with any third-party system. You can also use import and export functions or use our modules (import feeds and export feeds) to get even more flexibility.

### Documentation

- We are working on documentation. The current version is available [here](https://atropim.com/help).
- Documentation for administrators is available [here](https://github.com/atrocore/atrocore-docs/tree/master/en/administration).

### Requirements

* Unix-based system. Linux Mint is recommended.
* PHP 7.1 or above (with pdo_mysql, openssl, json, zip, gd, mbstring, xml, curl,exif extensions).
* MySQL 5.5.3 or above.

### Configuration Instructions Based on Your Server

* [Apache server configuration](https://github.com/atrocore/atrocore-docs/blob/master/en/administration/apache-server-configuration.md)
* [Nginx server configuration](https://github.com/atrocore/atrocore-docs/blob/master/en/administration/nginx-server-configuration.md)

### Installation

> Installation guide is based on **Ubuntu**. Of course, you can use any unix-based system, but make sure that your OS supports the following commands.<br/>

To create your new AtroCore application, first make sure you're using PHP 7.1 or above and have [Composer](https://getcomposer.org/download/) installed.

1. Create your new project by running:
   ```
   composer create-project atrocore/skeleton-pim my-atrocore-project
   ```
   > **my-atrocore-project** – project name
   
2. Change recursively the user and group ownership for project files: 
   ```
   chown -R webserver_user:webserver_user my-atrocore-project/
   ```
   >**webserver_user** – depends on your webserver and can be one of the following: www, www-data, apache, etc.

3. Configure the crontab as described below.

   3.1. Run the following command:
      ```
      crontab -e -u webserver_user
      ```
   3.2. Add the following configuration:
      ```
      * * * * * /usr/bin/php /var/www/my-atrocore-project/index.php cron 
      ```
4. Install AtroCore following the installation wizard in web interface. Go to http://YOUR_PROJECT/

### License

AtroCore is published under the GNU GPLv3 [license](LICENSE.txt).

### Support

- For support please contact us - visit [our Website](https://atropim.com/).
