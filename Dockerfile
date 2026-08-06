# ── PHP App (Apache) ─────────────────────────────────────────
FROM php:8.2-apache

# Fix: disable conflicting MPM modules, keep only prefork
RUN a2dismod mpm_event mpm_worker 2>/dev/null || true \
    && a2enmod mpm_prefork rewrite headers

# Install required PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Install zip support (for file uploads)
RUN apt-get update && apt-get install -y libzip-dev zip unzip \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

# Apache config — allow .htaccess in app directory
RUN printf '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n' > /etc/apache2/conf-available/app.conf \
    && a2enconf app

# PHP production settings
RUN printf "upload_max_filesize = 20M\npost_max_size = 22M\nmax_execution_time = 60\nmemory_limit = 128M\n" \
    > /usr/local/etc/php/conf.d/app.ini

# Copy app files
COPY . /var/www/html/

# Ensure uploads directory exists with correct permissions
RUN mkdir -p /var/www/html/uploads && \
    chmod 755 /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
