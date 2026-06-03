#!/usr/bin/env bash
# Run on server as root or subscription user after uploading the project.
# Usage:
#   export DEPLOY_PATH=/var/www/vhosts/90plus.kz/httpdocs
#   bash scripts/plesk-deploy.sh

set -euo pipefail

DEPLOY_PATH="${DEPLOY_PATH:-/var/www/vhosts/90plus.kz/httpdocs}"
cd "$DEPLOY_PATH"

echo "Deploy path: $DEPLOY_PATH"

if [[ ! -f artisan ]]; then
    echo "Error: artisan not found in $DEPLOY_PATH"
    exit 1
fi

if [[ ! -f .env ]]; then
    if [[ -f .env.production.example ]]; then
        cp .env.production.example .env
        echo "Created .env from .env.production.example — edit DB_* and API keys, then re-run."
        exit 1
    fi
    echo "Error: .env missing"
    exit 1
fi

export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction

php artisan storage:link --force 2>/dev/null || true
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache 2>/dev/null || true

chmod -R ug+rwx storage bootstrap/cache

echo "Done. Set document root to: $DEPLOY_PATH/public"
echo "Cron (Plesk Scheduled Tasks): * * * * * php $DEPLOY_PATH/artisan schedule:run"
