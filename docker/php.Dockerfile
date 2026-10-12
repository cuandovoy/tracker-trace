FROM php:8.4-fpm-bookworm AS php
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libzip-dev libicu-dev libpng-dev libonig-dev nginx supervisor curl && docker-php-ext-install pdo_mysql mbstring zip intl gd pcntl && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
