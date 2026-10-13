# Build aset frontend
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY vite.config.ts tsconfig.json tailwind.config.js postcss.config.js ./
COPY resources ./resources
RUN npm run build

# Dependensi PHP
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev

# Image aplikasi (PHP-FPM)
FROM php:8.3-fpm-alpine AS app
RUN apk add --no-cache postgresql-dev libzip-dev icu-dev oniguruma-dev libpng-dev \
    && docker-php-ext-install pdo_pgsql pgsql zip intl mbstring bcmath gd opcache \
    && rm -rf /var/cache/apk/*
COPY docker/php/php.ini /usr/local/etc/php/conf.d/procfolio.ini
WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build
RUN chown -R www-data:www-data storage bootstrap/cache
EXPOSE 9000
CMD ["php-fpm"]
