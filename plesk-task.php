<?php

/**
 * Если document root = корень проекта (не public/).
 *
 * https://90plus.kz/plesk-task.php?key=KEY
 */

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$root = __DIR__;

if (isset($_GET['job']) && $_GET['job'] !== '') {
    $runner = $root.'/scripts/plesk-artisan-runner.php';
    if (! is_file($runner)) {
        http_response_code(500);
        echo "ERROR: git pull — need scripts/plesk-artisan-runner.php\n";
        exit(1);
    }
    $run = require $runner;
    [$code] = $run($root, (string) $_GET['job'], (string) ($_GET['key'] ?? ''));
    exit($code);
}

if (isset($_GET['deploy']) && (string) $_GET['deploy'] === '1') {
    $runner = $root.'/scripts/plesk-deploy-runner.php';
    if (! is_file($runner)) {
        http_response_code(500);
        echo "ERROR: git pull — need scripts/plesk-deploy-runner.php\n";
        exit(1);
    }
    $run = require $runner;
    exit($run($root));
}

@mkdir($root.'/storage/logs', 0775, true);
file_put_contents($root.'/storage/logs/ping.log', date('c')." plesk-task OK-root\n", FILE_APPEND);

echo 'OK-root root='.$root."\n";
echo "Deploy:  ?deploy=1&key=YOUR_KEY\n";
echo "Cron:    ?job=articles&key=YOUR_KEY\n";
