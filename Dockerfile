
FROM php:8.3-apache

# Install required PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libpq-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql pdo_pgsql bcmath

# Enable Apache rewrite module
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy Laravel project
COPY . /var/www/html

# Set Laravel public directory
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage

# Install Laravel dependencies
WORKDIR /var/www/html

RUN composer install --no-dev --optimize-autoloader

EXPOSE 80

# Connect Render's runtime secret file to Laravel.
# Never copy the secret file into the Docker image.
# Keep the secret outside Laravel's public directory.
#
# Then run migrations, seed database, and start Apache.
CMD ["sh", "-c", "if [ -f /etc/secrets/.env ]; then ln -sfn /etc/secrets/.env /var/www/html/.env; fi; php artisan migrate --force && php artisan db:seed --force && apache2-foreground"]
