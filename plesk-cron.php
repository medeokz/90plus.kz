<?php

/**
 * Plesk Scheduled Tasks → Fetch URL or Run PHP script.
 *
 * Examples (replace KEY):
 *   https://90plus.kz/plesk-cron.php?job=clubs-daily&key=KEY
 *   https://90plus.kz/plesk-cron.php?job=articles&key=KEY
 *
 * PHP script path:
 *   /var/www/vhosts/018.kz/90plus.kz/plesk-cron.php
 * Arguments: clubs-daily KEY
 */

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$root = __DIR__;
$job = $_GET['job'] ?? ($_SERVER['argv'][1] ?? '');
$key = $_GET['key'] ?? ($_SERVER['argv'][2] ?? '');

$run = require $root.'/scripts/plesk-artisan-runner.php';
[$code] = $run($root, (string) $job, (string) $key);
exit($code);
