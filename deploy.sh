#!/bin/bash

# Update and install dependencies
sudo apt-get update
sudo apt-get install -y mysql-server php-mysql
sudo systemctl restart apache2

# Pull latest code
cd /var/www/html/contact-book
git pull origin master

# Import database
sudo mysql -u root < /var/www/html/contact-book/data.sql
