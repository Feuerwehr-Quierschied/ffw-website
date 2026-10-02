# syntax=docker/dockerfile:1

# 1) PHP-Abhängigkeiten (ohne Dev-Pakete)
FROM serversideup/php:8.4-fpm-nginx AS vendor
USER root
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

# 2) Frontend bauen (Tailwind/Vite -> public/build)
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .
RUN npm run build

# 3) Laufzeit-Image: PHP-FPM + Nginx auf Port 8080
FROM serversideup/php:8.4-fpm-nginx

ENV PHP_OPCACHE_ENABLE=1 \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=false \
    LOG_CHANNEL=stderr

WORKDIR /var/www/html

COPY --chown=www-data:www-data --from=vendor /var/www/html/vendor ./vendor
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev --no-interaction
