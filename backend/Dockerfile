# Production-style image for the Fone Ninja backend API.
#
# This is intentionally simple (single-stage, `artisan serve`) rather than a
# full PHP-FPM + nginx setup, matching the scope of a technical challenge.
# The project's actual dev/test tool is Sail (see compose.yaml) -- this
# Dockerfile + the root docker-compose.yml exist only to satisfy the
# original challenge README's requirement for a plain `docker compose up`
# without Sail.

FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev unzip zip libpng-dev \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html

RUN mkdir -p storage/framework/{cache,sessions,testing,views} storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=80"]
