FROM composer:2 AS composer-build
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader

FROM node:22-alpine AS asset-build
WORKDIR /assets
COPY package.json package-lock.json ./
RUN npm ci --omit=dev --ignore-scripts

FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libicu-dev unzip \
    && docker-php-ext-install curl intl pdo_mysql \
    && a2enmod headers rewrite \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY . .
COPY --from=composer-build /app/vendor ./vendor
COPY --from=asset-build /assets/node_modules/bootstrap/dist ./public/assets/vendor/bootstrap
COPY --from=asset-build /assets/node_modules/bootstrap-icons/font ./public/assets/vendor/bootstrap-icons
COPY --from=asset-build /assets/node_modules/sortablejs/Sortable.min.js ./public/assets/vendor/sortable/Sortable.min.js
COPY --from=asset-build /assets/node_modules/@fullcalendar/core/index.global.min.js ./public/assets/vendor/fullcalendar/core.min.js
COPY --from=asset-build /assets/node_modules/@fullcalendar/daygrid/index.global.min.js ./public/assets/vendor/fullcalendar/daygrid.min.js
COPY --from=asset-build /assets/node_modules/@fullcalendar/timegrid/index.global.min.js ./public/assets/vendor/fullcalendar/timegrid.min.js
COPY --from=asset-build /assets/node_modules/@fullcalendar/list/index.global.min.js ./public/assets/vendor/fullcalendar/list.min.js

RUN mkdir -p storage/uploads storage/cache storage/logs \
    && chown -R www-data:www-data storage

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 CMD curl --fail http://localhost/health || exit 1
