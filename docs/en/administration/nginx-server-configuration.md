## Nginx server configuration

#### PHP Requirements
To install all necessary libraries, run these commands in a terminal:
```
sudo apt-get update
sudo apt-get install php-mysql php-json php-gd php-zip php-imap php-mbstring php-curl
sudo phpenmod imap mbstring
sudo service nginx restart
```

Please update your nginx configuration with the following:
```
location / {
  if (!-e $request_filename){
    rewrite ^(.*)$ /client/ redirect;
  }
  if (!-e $request_filename){
    rewrite ^(.*)$ /apidocs/index.html break;
  }
  if (!-e $request_filename){
    rewrite ^(.*)$ /index.php?treoq=$1 break;
  }
}

location ~ (notReadCount\.json|popupNotifications\.json)$ {
  allow all;
}

location ~ (composer\.json)$ {
  deny all;
}
```
