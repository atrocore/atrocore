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
  location ~ (composer\.json)$ {
    deny all;
  }
  
  location ~ /\.ht {
    deny all;
  }
  
  location ~* ^.+\.(html|zip|jpg|jpeg|gif|png|ico|css|pdf|ppt|txt|bmp|rtf|js|json|tpl|ttf|woff|eot|svg|woff2)$ {
    access_log off;
    add_header Pragma public;
    add_header Cache-Control "public, must-revalidate, proxy-revalidate";
    expires 30d;
  }

  location ~ \.php$ {
    try_files $fastcgi_script_name =404;
    fastcgi_keep_conn on;
    fastcgi_pass unix:/var/run/php-fpm.sock;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
  }
    
  location / {
    rewrite ^/(.*)$ /index.php?treoq=$1;
  }
  
  location /apidocs {
    root   html;
    index index.html;
    try_files $uri $uri/ /apidocs/index.html;
  }
```
