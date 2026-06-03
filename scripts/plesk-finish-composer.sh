#!/usr/bin/env bash
# Finish deploy after composer failed on package:discover (proc_open disabled).
# 1) Enable proc_open in Plesk PHP Settings for 90plus.kz
# 2) Run this script

set -euo pipefail

DEPLOY_PATH="${DEPLOY_PATH:-/var/www/vhosts/90plus.kz/httpdocs}"
PHP_BIN="${PHP_BIN:-/opt/plesk/php/8.3/bin/php}"
cd "$DEPLOY_PATH"

if ! "$PHP_BIN" -r 'exit(function_exists("proc_open") ? 0 : 1);'; then
    echo "proc_open is still disabled. Fix in Plesk → PHP Settings first."
    exit 1
fi

export COMPOSER_ALLOW_SUPERUSER=1
if [[ -f /usr/local/psa/var/modules/composer/composer.phar ]]; then
    "$PHP_BIN" /usr/local/psa/var/modules/composer/composer.phar dump-autoload --no-dev --optimize --no-interaction
else
    "$PHP_BIN" "$(command -v composer)" dump-autoload --no-dev --optimize --no-interaction
fi

"$PHP_BIN" artisan package:discover --ansi
"$PHP_BIN" artisan filament:upgrade 2>/dev/null || true

echo "Composer post-install scripts completed."
