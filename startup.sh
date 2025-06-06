#!/bin/bash
# Copy nginx config and restart nginx
cp /home/site/wwwroot/nginx.conf /etc/nginx/conf.d/upload.conf
nginx -s reload
# Start PHP-FPM
php-fpm
