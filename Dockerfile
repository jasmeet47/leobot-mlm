FROM php:8.3-apache

# आवश्यक PHP एक्सटेंशन इंस्टॉल करना
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libpq-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql pdo_pgsql

# अपाचे (Apache) का मोड-रीराइट चालू करना
RUN a2enmod rewrite

# कम्पोज़र (Composer) इंस्टॉल करना
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# प्रोजेक्ट की सभी फाइलें सर्वर पर कॉपी करना
COPY . /var/www/html

# Apache का डॉक्यूमेंट रूट Laravel के public फोल्डर पर सेट करना
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# सही परमिशन सेट करना
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage

# Laravel के लिए dependencies install करना
WORKDIR /var/www/html

RUN composer install --no-dev --optimize-autoloader

EXPOSE 80

CMD ["sh", "-c", "php artisan migrate --force && php artisan db:seed --force && apache2-foreground"]
