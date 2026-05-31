FROM php:8.2-apache

# Enable mod_rewrite, MySQL, and intl extensions
RUN apt-get update && apt-get install -y libicu-dev \
    && docker-php-ext-install pdo pdo_mysql intl \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/reviewer_app

# Copy source and install dependencies
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Point Apache document root to CakePHP's webroot/
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/reviewer_app/webroot\n\
    <Directory /var/www/reviewer_app/webroot>\n\
        Options -Indexes\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Writable dirs CakePHP needs at runtime
RUN mkdir -p tmp/cache/models tmp/cache/persistent tmp/cache/views tmp/sessions tmp/tests logs \
    && chown -R www-data:www-data tmp logs \
    && chmod -R 775 tmp logs

EXPOSE 80
