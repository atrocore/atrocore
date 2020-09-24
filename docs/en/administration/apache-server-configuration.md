## Apache server configuration

#### PHP Requirements
To install all necessary libraries, run these commands in a terminal:
```
sudo apt-get update
sudo apt-get install php-mysql php-json php-gd php-zip php-imap php-mbstring php-curl
sudo phpenmod imap mbstring
sudo service apache2 restart
```
