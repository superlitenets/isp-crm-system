FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip intl \
    && a2enmod rewrite headers \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN echo "max_execution_time=300" >> /usr/local/etc/php/conf.d/timeouts.ini \
    && echo "max_input_time=300" >> /usr/local/etc/php/conf.d/timeouts.ini \
    && echo "default_socket_timeout=300" >> /usr/local/etc/php/conf.d/timeouts.ini \
    && echo "memory_limit=256M" >> /usr/local/etc/php/conf.d/timeouts.ini

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

COPY . /var/www/html/

RUN mkdir -p /var/www/html/uploads \
    && mkdir -p /var/www/html/logs \
    && rm -rf /var/www/html/.git \
    && rm -rf /var/www/html/.cache \
    && rm -rf /var/www/html/.upm \
    && rm -f /var/www/html/*.zip \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/uploads \
    && chmod -R 777 /var/www/html/logs

EXPOSE 80

CMD ["apache2-foreground"]
