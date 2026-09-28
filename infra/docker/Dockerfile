FROM node:24-bookworm-slim@sha256:0e0ff40c39bc087845bfb27465a0df4ea419520094bc35842ff83dd8cbe6f9b6 AS frontend
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM php:8.4-fpm-bookworm@sha256:43e1ac38217031dbbecae60e84ccf8593722031559178d199bf56adb0145d5d0 AS app
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev libpq-dev libzip-dev libicu-dev libxml2-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev unzip git && docker-php-ext-configure gd --with-freetype --with-jpeg && docker-php-ext-install pdo_pgsql bcmath intl zip pcntl gd mbstring opcache && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2@sha256:9715c7f69044da2a212a5fbde29ee7da24e364d426560ae6367b060236f847d7 /usr/bin/composer /usr/bin/composer
COPY infra/docker/php-production.ini /usr/local/etc/php/conf.d/99-nadi-production.ini
WORKDIR /var/www
COPY . .
RUN mkdir -p bootstrap/cache storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
COPY --from=frontend /build/dist ./dist
RUN mkdir -p storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache && chmod -R 770 storage bootstrap/cache
USER www-data
EXPOSE 9000
CMD ["php-fpm"]

FROM nginx:stable-alpine@sha256:0985e772fb9f729e6fa0980da05fca5d9c468e870eed43071545afa9d2e27d94 AS web
COPY infra/nginx/security-headers.conf /etc/nginx/security-headers.conf
COPY infra/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=frontend /build/dist /var/www/dist
