FROM node:24-alpine AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pdo_pgsql \
        zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri \
    -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY docker/render-entrypoint.sh /usr/local/bin/render-entrypoint

RUN chmod +x /usr/local/bin/render-entrypoint \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 10000
ENTRYPOINT ["render-entrypoint"]
