#!/bin/bash
set -e

cd /var/www/html

if [ ! -f .env ]; then
  cp .env.docker .env
fi

if ! grep -q "^APP_KEY=base64:" .env; then
  php artisan key:generate --force
fi

php artisan config:cache || true

exec "$@"
