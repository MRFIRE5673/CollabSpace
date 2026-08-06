# ── PHP App (Apache) ─────────────────────────────────────────
FROM php:8.2-apache

# Enable required PHP extensions
RUN docker-php-ext-install pdo pdo_mysql
RUN a2enmod rewrite headers

# Install zip support (for file uploads)
RUN apt-get update && apt-get install -y libzip-dev zip unzip \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

# Copy app files
COPY . /var/www/html/

# Apache config — allow .htaccess
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/app.conf \
    && a2enconf app

# PHP production settings
RUN echo "upload_max_filesize = 20M\npost_max_size = 22M\nmax_execution_time = 60\nmemory_limit = 128M" \
    > /usr/local/etc/php/conf.d/app.ini

# Uploads directory
RUN mkdir -p /var/www/html/uploads && \
    chmod 755 /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
