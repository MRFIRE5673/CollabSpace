#!/bin/sh
# Start PHP-FPM in background, then nginx in foreground
php-fpm -D
nginx -g "daemon off;"
