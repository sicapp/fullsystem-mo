#!/bin/bash
cd /var/www/html/
mv /tmp/vendor .
mv /tmp/.env .
php artisan optimize:clear
php artisan view:clear
php artisan route:clear
php artisan config:clear
php artisan cache:clear
sudo chown -R www-data:www-data /var/www/html
sudo supervisorctl restart all