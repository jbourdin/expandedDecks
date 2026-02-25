FROM php:8.5-fpm-alpine

# install-php-extensions: cleaner Alpine builds, auto-manages build deps
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions pdo_mysql intl opcache zip apcu

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
