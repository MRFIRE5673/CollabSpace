# ── nginx + PHP-FPM (Railway compatible) ─────────────────────
FROM php:8.2-fpm-alpine

# Install nginx + gettext (envsubst) + tools
RUN apk add --no-cache nginx gettext curl zip unzip libzip-dev dos2unix netcat-openbsd

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql zip

# PHP production settings
RUN printf "upload_max_filesize = 20M\npost_max_size = 22M\nmax_execution_time = 60\nmemory_limit = 128M\n" \
    > /usr/local/etc/php/conf.d/app.ini

# Nginx + startup config
COPY docker/nginx.conf /etc/nginx/nginx.conf.template
COPY docker/start.sh   /start.sh
RUN dos2unix /start.sh && chmod +x /start.sh

# App files
COPY . /var/www/html/

# Ensure uploads writable
RUN mkdir -p /var/www/html/uploads /tmp/client_body /tmp/proxy /tmp/fastcgi \
 && chown -R www-data:www-data /var/www/html \
 && chmod 755 /var/www/html/uploads

# Railway sets PORT dynamically — expose hint only
EXPOSE 8080

CMD ["/start.sh"]
