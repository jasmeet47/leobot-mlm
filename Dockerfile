
# ==================================================
# LeoBot MLM - Laravel 13 Production Dockerfile
# PHP 8.3 + PostgreSQL + Vite
# ==================================================

# Stage 1: Build frontend assets
FROM node:22-bookworm-slim AS frontend

WORKDIR /app

# Install Node dependencies
COPY package*.json ./
RUN npm install

# Copy project files needed by Vite
COPY . .

# Generate public/build/manifest.json
RUN npm run build


# Stage 2: Laravel application
FROM php:8.3-apache

# Install PHP extensions and system packages
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libpq-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install \
        gd \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        bcmath \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest \
    /usr/bin/composer \
    /usr/bin/composer

# Copy Laravel project
COPY . /var/www/html

# Set Apache document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri \
    -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf

RUN sed -ri \
    -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/apache2.conf

# Set application working directory
WORKDIR /var/www/html

# Install production PHP dependencies
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction

# Copy generated Vite assets from frontend stage
COPY --from=frontend --chown=www-data:www-data \
    /app/public/build \
    /var/www/html/public/build

# Set Laravel file permissions
RUN chown -R www-data:www-data \
        /var/www/html/storage \
        /var/www/html/bootstrap/cache \
    && chmod -R 775 \
        /var/www/html/storage \
        /var/www/html/bootstrap/cache

# Render web port
EXPOSE 80

# Use Render secret file only if provided.
# Environment variables configured in Render
# are also supported by Laravel.
#
# No automatic migrations or seeding.
CMD ["sh", "-c", "if [ -f /etc/secrets/.env ]; then ln -sfn /etc/secrets/.env /var/www/html/.env; fi; exec apache2-foreground"]
