#!/usr/bin/env bash
set -e

DB_HOST="${DB_HOST:-mysql}"
DB_PORT="${DB_PORT:-3306}"
DB_USERNAME="${DB_USERNAME:-root}"
DB_PASSWORD="${DB_PASSWORD:-secret}"
DB_DATABASE="${DB_DATABASE:-address_book}"

# Ensure a .env exists (copy the example on first boot)
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Wait for the MySQL service to accept connections.
# --skip-ssl: the MySQL 8 server presents a self-signed cert the CLI client would
# otherwise reject; Laravel's PDO connection does not verify it and is unaffected.
echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT}..."
until mysqladmin ping -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" --skip-ssl --silent 2>/dev/null; do
    sleep 2
done
echo "MySQL is up."

# Generate an app key if one is not already provided
if [ -z "${APP_KEY}" ] && ! grep -q "^APP_KEY=base64" .env; then
    php artisan key:generate --force
fi

# Cache config from the container environment (DB_HOST=mysql, CACHE_STORE=file, ...).
# `php artisan serve` otherwise forwards the .env file values to the request
# subprocess, which would shadow these; cached config takes precedence per request.
php artisan config:cache

# Migrations are idempotent — always safe to run
php artisan migrate --force

# Seed only when the address book is empty (fresh database), so restarts
# with a persisted volume don't duplicate the demo data.
ROWS=$(mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" --skip-ssl -N -s \
    -e "SELECT COUNT(*) FROM address_book;" "${DB_DATABASE}" 2>/dev/null || echo 0)
if [ "${ROWS:-0}" -eq 0 ]; then
    echo "Empty database detected — seeding demo data."
    php artisan db:seed --force
else
    echo "Existing data found (${ROWS} contacts) — skipping seed."
fi

# Hand off to the container command (php artisan serve ...)
exec "$@"
