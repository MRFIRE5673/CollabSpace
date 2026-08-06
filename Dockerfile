# ── nginx + PHP-FPM (no Apache MPM issues) ───────────────────
FROM php:8.2-fpm-alpine

# Install nginx + required tools
RUN apk add --no-cache nginx curl zip unzip

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Install zip extension
RUN apk add --no-cache libzip-dev \
    && docker-php-ext-install zip

# ── nginx config ─────────────────────────────────────────────
RUN mkdir -p /run/nginx
COPY docker/nginx.conf /etc/nginx/nginx.conf

# ── PHP-FPM config ────────────────────────────────────────────
RUN echo "upload_max_filesize = 20M" >> /usr/local/etc/php/conf.d/app.ini \
 && echo "post_max_size = 22M"       >> /usr/local/etc/php/conf.d/app.ini \
 && echo "max_execution_time = 60"   >> /usr/local/etc/php/conf.d/app.ini \
 && echo "memory_limit = 128M"       >> /usr/local/etc/php/conf.d/app.ini

# ── App files ─────────────────────────────────────────────────
COPY . /var/www/html/

# Uploads directory
RUN mkdir -p /var/www/html/uploads \
 && chmod 755 /var/www/html/uploads \
 && chown -R www-data:www-data /var/www/html

# ── Startup script ────────────────────────────────────────────
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
