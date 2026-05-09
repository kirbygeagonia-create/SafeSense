FROM php:8.2-apache

# Install PHP extensions SafeSense needs
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite (needed for SafeSense URL routing)
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy the entire repo into the container
WORKDIR /var/www/html
COPY . .

# Install PHP dependencies
RUN cd medical && composer install --no-dev --optimize-autoloader --no-interaction

# Create writable storage directories
RUN mkdir -p medical/storage/alert_images medical/storage/heartbeats \
    && chmod -R 755 medical/storage

# Point Apache document root at medical/public/
RUN sed -i 's|/var/www/html|/var/www/html/medical/public|g' \
    /etc/apache2/sites-available/000-default.conf

# Allow .htaccess overrides (needed for SafeSense URL rewriting)
RUN sed -i 's/AllowOverride None/AllowOverride All/g' \
    /etc/apache2/apache2.conf

EXPOSE 80
CMD ["apache2-foreground"]
