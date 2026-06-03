#!/bin/bash
# For Plesk Scheduled Task → "Run a command" (NOT "Run PHP script").
# Logs: storage/logs/cron-deploy.log
#
# Plesk command field (one line):
# /bin/bash /var/www/vhosts/90plus.kz/httpdocs/scripts/plesk-cron-deploy.sh

DEPLOY="/var/www/vhosts/90plus.kz/httpdocs"
PHP="/opt/plesk/php/8.3/bin/php"
LOG="$DEPLOY/storage/logs/cron-deploy.log"

mkdir -p "$DEPLOY/storage/logs"
exec >> "$LOG" 2>&1

echo "======== $(date -Iseconds) ========"

if [[ ! -d "$DEPLOY" ]]; then
    echo "ERROR: folder missing: $DEPLOY"
    exit 1
fi

cd "$DEPLOY" || exit 1
echo "PWD: $(pwd)"

if [[ ! -f artisan ]]; then
    echo "ERROR: artisan not found. Fix path (need httpdocs)."
    exit 1
fi

if command -v git >/dev/null 2>&1 && [[ -d .git ]]; then
    echo "git pull..."
    git pull origin main || git pull || echo "WARN: git pull failed"
else
    echo "SKIP git (no git or no .git)"
fi

if [[ ! -f vendor/autoload.php ]]; then
    echo "ERROR: vendor/ missing — run composer install --no-scripts on server"
    exit 1
fi

if [[ ! -f .env ]]; then
    echo "ERROR: .env missing — copy .env.production.example to .env"
    exit 1
fi

if [[ ! -x "$PHP" ]]; then
    echo "ERROR: PHP not found: $PHP"
    exit 1
fi

echo "$($PHP -v | head -1)"

"$PHP" artisan migrate --force || { echo "ERROR: migrate"; exit 1; }
"$PHP" artisan config:cache || { echo "ERROR: config:cache"; exit 1; }
"$PHP" artisan route:cache || { echo "ERROR: route:cache"; exit 1; }
"$PHP" artisan view:cache || { echo "ERROR: view:cache"; exit 1; }

chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true

echo "OK — deploy finished"
exit 0
