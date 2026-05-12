FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
    nginx \
    curl \
    zip \
    unzip \
    && docker-php-ext-install opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts || true

COPY . .

RUN mkdir -p storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/nginx.conf /etc/nginx/http.d/default.conf

RUN cp .env.example .env || true

EXPOSE 80

COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

CMD ["/start.sh"]
