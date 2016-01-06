SabreDAV for ZeyOS
==================

Purpose
-------

This is a setup for SabreDAV, an open source CalDAV and CardDAV server.
The main objective of this setup is to create a seamless integration with [ZeyOS](https://www.zeyos.com)
so that users can login with their ZeyOS username and password.
It includes an auto-provisioning service for ZeyOS, so that group calendars and 
address books are automatically created and distributed amoung the members.


Installation
------------

We have installed SabreDAV on Ubuntu 14.04 LTS, but the process should be pretty similar for other Linux distributions as well. 
For this installation, we are using MySQL and Nginx.

1. Clone the repository into `/srv/sabredav/`.

    cd /srv/sabredav/
    git clone https://github.com/zeyosinc/sabredav.git

2. Enter the directory and install the required libraries using [composer](https://getcomposer.org/download/)

    composer install

3. Install and configure MySQL

    apt-get install mysql-server
    
4. Open a MySQL terminal (`mysql -u root -p`) and create a database and a user for SabreDAV

    CREATE USER 'sabredav'@'localhost' IDENTIFIED BY 'MySecretPassword';
    CREATE DATABASE sabredav CHARACTER SET utf8;
    GRANT ALL PRIVILEGES ON sabredav.* TO 'sabredav'@'localhost';
    exit

5. Import the MySQL database schema for calendar, contacts and priviledges

    cd /srv/sabredav/res/mysql/
    mysql -u sabredav -p < schema.sql
    
6. Create a config file and insert your database credentials as well as your ZeyOS instance ID

    cd /srv/sabredav/
    cp config.template.php config.php
    nano config.php
    
7. Install PHP and nginx

    apt-get install php5-fpm nginx
    
8. Copy the sample configuration for nginx and adapt the settings

    cp /srv/sabredav/res/nginx/sabredav /etc/nginx/sites-available/
    rm /etc/nginx/sites-enabled/default
    ln -s /etc/nginx/sites-available/sabredav /etc/nginx/sites-enabled/sabredav
    nano /etc/nginx/sites-available/sabredav

This should be it! Restart the webserver, open your webbrowser and enter your URL.

