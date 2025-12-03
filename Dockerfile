FROM php:8.1-fpm

RUN apt-get update && apt-get install -y \
    libpq-dev \
    libicu-dev \
    libcurl4-openssl-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_pgsql intl curl zip opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction --ignore-platform-reqs || composer update --no-dev --optimize-autoloader --no-scripts --no-interaction

COPY . .

RUN chown -R www-data:www-data /var/www/html

EXPOSE 9000

CMD ["php-fpm"]
