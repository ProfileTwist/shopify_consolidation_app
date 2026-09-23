#!/bin/bash
set -e

cd /var/www/html

# First-run bootstrap: create .env from the docker template if absent.
if [ ! -f .env ]; then
  cp .env.docker .env
fi

# Generate an app key if one is not set.
if ! grep -q "^APP_KEY=base64:" .env; then
  php artisan key:generate --force
fi

# Wait for MySQL to accept connections.
echo "Waiting for MySQL at ${DB_HOST:-mysql}:${DB_PORT:-3306}..."
until php -r "new PDO('mysql:host=${DB_HOST:-mysql};port=${DB_PORT:-3306}', '${DB_USERNAME:-sfc}', '${DB_PASSWORD:-secret}');" 2>/dev/null; do
  sleep 2
done
echo "MySQL is up."

# Only the main API container runs migrations/seeds (idempotent seeder).
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force
  php artisan db:seed --force
  php artisan config:cache
fi

exec "$@"
