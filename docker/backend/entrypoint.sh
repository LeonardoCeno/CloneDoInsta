#!/usr/bin/env bash
set -euo pipefail

cd /app

# Ensure .env exists
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
    else
        touch .env
    fi
fi

# Generate APP_KEY if missing
if [ -z "${APP_KEY:-}" ] && ! grep -q '^APP_KEY=.\+' .env 2>/dev/null; then
    php artisan key:generate --force
fi

# Wait for MySQL
if [ "${DB_CONNECTION:-}" = "mysql" ] && [ -n "${DB_HOST:-}" ]; then
    echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT:-3306}..."
    for i in $(seq 1 60); do
        if mysqladmin ping -h "${DB_HOST}" -P "${DB_PORT:-3306}" \
            -u "${DB_USERNAME:-root}" -p"${DB_PASSWORD:-}" --ssl=0 --silent >/dev/null 2>&1; then
            echo "MySQL ready."
            break
        fi
        if [ "$i" -eq 60 ]; then
            echo "MySQL did not become ready in time." >&2
            exit 1
        fi
        sleep 1
    done
fi

# Run migrations
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

# Storage link
php artisan storage:link --force 2>/dev/null || true

# Laravel caches
if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan route:cache
    php artisan event:cache
else
    php artisan config:clear
    php artisan route:clear
fi

php artisan l5-swagger:generate 2>/dev/null || true

exec "$@"
