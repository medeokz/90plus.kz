<?php

/**
 * Use when Plesk document root = httpdocs/public (recommended).
 * URL: https://YOUR-DOMAIN/plesk-deploy.php?key=DEPLOY_KEY
 *
 * Plesk cron "Run PHP script":
 * /var/www/vhosts/018.kz/90plus.kz/public/plesk-deploy.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$run = require $root.'/scripts/plesk-deploy-runner.php';
exit($run($root));
