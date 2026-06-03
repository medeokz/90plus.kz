<?php

/**
 * Ping:  https://90plus.kz/plesk-ping.php
 * Deploy: https://90plus.kz/plesk-ping.php?deploy=1&key=YOUR_DEPLOY_KEY
 */

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$root = __DIR__;

if (isset($_GET['deploy']) && (string) $_GET['deploy'] === '1') {
    $runner = $root.'/scripts/plesk-deploy-runner.php';
    if (! is_file($runner)) {
        http_response_code(500);
        echo "ERROR: upload scripts/plesk-deploy-runner.php (git pull)\n";
        exit(1);
    }
    $run = require $runner;
    exit($run($root));
}

@mkdir($root.'/storage/logs', 0775, true);
file_put_contents(
    $root.'/storage/logs/ping.log',
    date('c').' ping OK-root'.PHP_EOL,
    FILE_APPEND
);

echo 'OK-root root='.$root."\n";
echo "Deploy: /plesk-ping.php?deploy=1&key=YOUR_DEPLOY_KEY\n";
