FROM php:8.2-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends libicu-dev libzip-dev unzip \
    && docker-php-ext-install pdo pdo_mysql intl zip \
    && rm -rf /var/lib/apt/lists/*

# Fix Apache MPM conflict — directly remove conflicting MPM files
RUN rm -f /etc/apache2/mods-enabled/mpm_event.load \
          /etc/apache2/mods-enabled/mpm_event.conf \
          /etc/apache2/mods-enabled/mpm_worker.load \
          /etc/apache2/mods-enabled/mpm_worker.conf \
    && a2enmod mpm_prefork rewrite

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/reviewer_app

COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Point Apache document root to CakePHP's webroot/ with rewrite rules inline
RUN printf '<VirtualHost *:80>\n\
    DocumentRoot /var/www/reviewer_app/webroot\n\
    <Directory /var/www/reviewer_app/webroot>\n\
        Options -Indexes\n\
        AllowOverride All\n\
        Require all granted\n\
        RewriteEngine On\n\
        RewriteCond %%{REQUEST_FILENAME} !-f\n\
        RewriteRule ^ index.php [L]\n\
    </Directory>\n\
</VirtualHost>\n' > /etc/apache2/sites-available/000-default.conf

# Writable dirs CakePHP needs at runtime
RUN mkdir -p tmp/cache/models tmp/cache/persistent tmp/cache/views tmp/sessions tmp/tests logs \
    && chown -R www-data:www-data tmp logs \
    && chmod -R 775 tmp logs

COPY docker-start.sh /usr/local/bin/docker-start.sh
RUN chmod +x /usr/local/bin/docker-start.sh

EXPOSE 80
CMD ["/usr/local/bin/docker-start.sh"]
