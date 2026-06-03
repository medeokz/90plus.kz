<?php

/**
 * Plesk Scheduled Task → "Run a PHP script" (NOT bash).
 * Script path: /var/www/vhosts/90plus.kz/httpdocs/public/plesk-deploy.php
 *
 * Set in .env: DEPLOY_KEY=your-long-random-secret
 * Optional: run once in browser: https://90plus.kz/plesk-deploy.php?key=YOUR_KEY
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$logFile = $root.'/storage/logs/cron-deploy.log';

function deploy_log(string $message): void
{
    global $logFile;
    $line = date('Y-m-d H:i:s').' '.$message.PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

deploy_log('--- deploy start ---');

if (! is_file($root.'/.env')) {
    deploy_log('ERROR: .env missing');
    exit(1);
}

// Load .env for DEPLOY_KEY only (before full bootstrap)
$deployKey = null;
foreach (file($root.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), 'DEPLOY_KEY=')) {
        $deployKey = trim(substr($line, strlen('DEPLOY_KEY=')), " \t\"'");
        break;
    }
}

$providedKey = $_GET['key'] ?? $_SERVER['argv'][1] ?? '';
if ($deployKey === null || $deployKey === '' || ! hash_equals($deployKey, (string) $providedKey)) {
    deploy_log('ERROR: invalid or missing DEPLOY_KEY');
    http_response_code(403);
    exit(1);
}

if (! is_file($root.'/vendor/autoload.php')) {
    deploy_log('ERROR: vendor/ missing');
    exit(1);
}

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$commands = [
    ['migrate', ['--force' => true]],
    ['config:cache', []],
    ['route:cache', []],
    ['view:cache', []],
];

foreach ($commands as [$command, $args]) {
    try {
        $exit = $kernel->call($command, $args);
        deploy_log("OK: php artisan {$command} (exit {$exit})");
    } catch (Throwable $e) {
        deploy_log("ERROR: {$command} — ".$e->getMessage());
        exit(1);
    }
}

deploy_log('--- deploy finished OK ---');
exit(0);
