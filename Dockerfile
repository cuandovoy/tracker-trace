FROM php:8.4-fpm-bookworm AS base
RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev libicu-dev libpng-dev libonig-dev nginx supervisor curl unzip && docker-php-ext-install pdo_mysql mbstring zip intl gd opcache && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html

FROM base AS dependencies
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

FROM node:22-bookworm-slim AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.ts tsconfig.json ./
RUN npm run build

FROM base AS production
COPY --from=dependencies --chown=www-data:www-data /var/www/html /var/www/html
COPY --from=frontend --chown=www-data:www-data /app/public/build /var/www/html/public/build
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/fastcgi_params /etc/nginx/app_fastcgi_params
COPY docker/supervisord.conf /etc/supervisor/conf.d/app.conf
COPY docker/php-production.ini /usr/local/etc/php/conf.d/production.ini
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN mkdir -p /var/cache/nginx /var/log/supervisor /var/run/supervisor storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache && chown -R www-data:www-data /var/cache/nginx /var/log/supervisor /var/run/supervisor storage bootstrap/cache
USER www-data
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s CMD curl -fsS http://127.0.0.1:8080/up || exit 1
ENTRYPOINT ["app-entrypoint"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/app.conf"]
