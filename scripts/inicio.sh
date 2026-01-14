#!/bin/bash
mv /var/www/html/vendor /tmp/
mv /var/www/html/.env /tmp/
sudo find /var/www/html -mindepth 1 -delete
