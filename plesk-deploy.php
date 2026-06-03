<?php

/**
 * Use when Plesk document root = httpdocs (NOT httpdocs/public).
 * URL: https://YOUR-DOMAIN/plesk-deploy.php?key=DEPLOY_KEY
 *
 * Plesk cron "Run PHP script":
 * /var/www/vhosts/90plus.kz/httpdocs/plesk-deploy.php
 * Argument: your DEPLOY_KEY
 */

declare(strict_types=1);

$root = __DIR__;
$run = require $root.'/scripts/plesk-deploy-runner.php';
exit($run($root));
