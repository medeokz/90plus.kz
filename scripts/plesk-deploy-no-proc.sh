#!/usr/bin/env bash
# Deploy when proc_open cannot be enabled on Plesk (shared hosting).
# Requires: vendor/ already installed (composer --no-scripts on server OR upload vendor from PC).
#
# On your PC (Windows):
#   composer install --no-dev --no-scripts
#   then upload vendor/ + project via FTP/Git (git pull without vendor — run composer on server with --no-scripts)
#
# Usage on server:
#   export PHP_BIN=/opt/plesk/php/8.3/bin/php
#   export DEPLOY_PATH=/var/www/vhosts/90plus.kz/httpdocs
#   bash scripts/plesk-deploy-no-proc.sh

set -euo pipefail

# Must be httpdocs (where artisan lives), NOT /var/www/vhosts/90plus.kz/
DEPLOY_PATH="${DEPLOY_PATH:-/var/www/vhosts/90plus.kz/httpdocs}"
PHP_BIN="${PHP_BIN:-/opt/plesk/php/8.3/bin/php}"
cd "$DEPLOY_PATH"

if [[ ! -f vendor/autoload.php ]]; then
    echo "vendor/ missing. On server run:"
    echo "  $PHP_BIN \$(which composer) install --no-dev --no-scripts --optimize-autoloader"
    echo "Or upload vendor/ from your computer after: composer install --no-dev --no-scripts"
    exit 1
fi

if [[ ! -f bootstrap/cache/packages.php ]]; then
    echo "bootstrap/cache/packages.php missing. Run git pull (committed from dev machine)."
    exit 1
fi

if [[ ! -f .env ]]; then
    echo "Create .env from .env.production.example first."
    exit 1
fi

"$PHP_BIN" artisan storage:link --force 2>/dev/null || true
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

chmod -R ug+rwx storage bootstrap/cache

echo ""
echo "OK (without proc_open)."
echo "Do NOT use: php artisan schedule:run"
echo "Add separate cron jobs in Plesk (see scripts/plesk-crontab.txt)"
