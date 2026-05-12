#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

php-fpm -D

exec nginx -g 'daemon off;'
