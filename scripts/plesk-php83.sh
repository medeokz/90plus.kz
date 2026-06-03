#!/usr/bin/env bash
# Switch 90plus.kz to PHP 8.3 in Plesk (run as root).
# Usage: bash scripts/plesk-php83.sh [domain]

DOMAIN="${1:-90plus.kz}"

echo "Setting PHP 8.3 for $DOMAIN ..."
plesk bin site --update "$DOMAIN" -php_handler_id plesk-php83-fpm 2>/dev/null \
    || plesk bin domain --update "$DOMAIN" -php_handler_id plesk-php83-fpm

echo "Verify CLI PHP for composer:"
/opt/plesk/php/8.3/bin/php -v

echo ""
echo "Deploy with:"
echo "  export PHP_BIN=/opt/plesk/php/8.3/bin/php"
echo "  cd /var/www/vhosts/$DOMAIN/httpdocs && bash scripts/plesk-deploy.sh"
