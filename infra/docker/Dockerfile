FROM node:24.20.0-bookworm-slim AS frontend
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM php:8.4.12-fpm-bookworm AS app
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libzip-dev libicu-dev libxml2-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev unzip git && docker-php-ext-configure gd --with-freetype --with-jpeg && docker-php-ext-install pdo_pgsql bcmath intl zip pcntl gd && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2.8.12 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www
COPY . .
RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
COPY --from=frontend /build/dist ./dist
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache && chmod -R 770 storage bootstrap/cache
USER www-data
EXPOSE 9000
CMD ["php-fpm"]

FROM nginx:1.28.0-alpine AS web
COPY infra/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=frontend /build/dist /var/www/dist
