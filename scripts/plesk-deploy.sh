#!/usr/bin/env bash
# Run on server as root or subscription user after uploading the project.
# Requires PHP 8.2+ (Laravel 11). Plesk: set domain PHP to 8.3 before deploy.
#
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

# Prefer Plesk PHP 8.3/8.2 (CLI "php" is often 8.0 on Ubuntu)
PHP_BIN="${PHP_BIN:-}"
if [[ -z "$PHP_BIN" ]]; then
    for candidate in \
        /opt/plesk/php/8.3/bin/php \
        /opt/plesk/php/8.2/bin/php \
        /usr/bin/php8.3 \
        /usr/bin/php8.2 \
        php; do
        if command -v "$candidate" &>/dev/null || [[ -x "$candidate" ]]; then
            ver=$("$candidate" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo "0.0")
            major=${ver%%.*}
            minor=${ver#*.}
            if [[ "$major" -ge 8 && "$minor" -ge 2 ]]; then
                PHP_BIN="$candidate"
                break
            fi
        fi
    done
fi

if [[ -z "$PHP_BIN" ]]; then
    echo "Error: PHP 8.2+ not found."
    echo "In Plesk: Domains → 90plus.kz → PHP Settings → PHP 8.3 (plesk-php83-fpm)"
    echo "Then run: export PHP_BIN=/opt/plesk/php/8.3/bin/php && bash scripts/plesk-deploy.sh"
    exit 1
fi

echo "Using PHP: $PHP_BIN ($("$PHP_BIN" -v | head -1))"

export COMPOSER_ALLOW_SUPERUSER=1
if [[ -f /usr/local/psa/var/modules/composer/composer.phar ]]; then
    "$PHP_BIN" /usr/local/psa/var/modules/composer/composer.phar install --no-dev --optimize-autoloader --no-interaction
elif command -v composer &>/dev/null; then
    "$PHP_BIN" "$(command -v composer)" install --no-dev --optimize-autoloader --no-interaction
else
    echo "Error: composer not found"
    exit 1
fi

"$PHP_BIN" artisan storage:link --force 2>/dev/null || true
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan event:cache 2>/dev/null || true

chmod -R ug+rwx storage bootstrap/cache

echo "Done. Document root must be: $DEPLOY_PATH/public"
echo "Cron: * * * * * $PHP_BIN $DEPLOY_PATH/artisan schedule:run >> /dev/null 2>&1"
