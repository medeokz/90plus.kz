<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$resolve = require __DIR__.'/scripts/plesk-laravel-root.php';
$root = $resolve(__DIR__);

if (isset($_GET['job']) && $_GET['job'] !== '') {
    $job = (string) $_GET['job'];
    $key = (string) ($_GET['key'] ?? '');

    if ($job === 'scheduler') {
        $verify = require $root.'/scripts/plesk-verify-key.php';
        $secret = $verify($root);
        if ($secret === null || $secret === '' || ! hash_equals($secret, $key)) {
            http_response_code(403);
            echo "Forbidden\n";
            exit(1);
        }
        $early = require $root.'/scripts/plesk-early-response.php';
        $early("scheduler: accepted\n");
        $runner = $root.'/scripts/plesk-scheduler-runner.php';
        if (! is_file($runner)) {
            http_response_code(500);
            echo "ERROR: missing scripts/plesk-scheduler-runner.php\n";
            exit(1);
        }
        $run = require $runner;
        exit($run($root, $key));
    }

    $verify = require $root.'/scripts/plesk-verify-key.php';
    $secret = $verify($root);
    if ($secret === null || $secret === '' || ! hash_equals($secret, $key)) {
        http_response_code(403);
        echo "Forbidden\n";
        exit(1);
    }
    $early = require $root.'/scripts/plesk-early-response.php';
    $early("job: {$job} accepted\n");

    $runner = $root.'/scripts/plesk-artisan-runner.php';
    if (! is_file($runner)) {
        http_response_code(500);
        echo "ERROR: missing scripts/plesk-artisan-runner.php\n";
        exit(1);
    }
    $run = require $runner;
    [$code] = $run($root, $job, $key);
    exit($code);
}

if (isset($_GET['deploy']) && (string) $_GET['deploy'] === '1') {
    $runner = $root.'/scripts/plesk-deploy-runner.php';
    if (! is_file($runner)) {
        http_response_code(500);
        echo "ERROR: missing scripts/plesk-deploy-runner.php\n";
        exit(1);
    }
    $run = require $runner;
    exit($run($root));
}

if (isset($_GET['refresh-config']) && (string) $_GET['refresh-config'] === '1') {
    $runner = $root.'/scripts/plesk-config-refresh.php';
    if (! is_file($runner)) {
        http_response_code(500);
        echo "ERROR: missing scripts/plesk-config-refresh.php\n";
        exit(1);
    }
    $run = require $runner;
    exit($run($root));
}

if (isset($_GET['db-check']) && (string) $_GET['db-check'] === '1') {
    $runner = $root.'/scripts/plesk-db-check.php';
    if (! is_file($runner)) {
        http_response_code(500);
        echo "ERROR: missing scripts/plesk-db-check.php\n";
        exit(1);
    }
    exit((int) $run($root));
}

@mkdir($root.'/storage/logs', 0775, true);
file_put_contents($root.'/storage/logs/ping.log', date('c')." plesk-task OK\n", FILE_APPEND);

echo "OK laravel-root={$root}\n";
echo "Deploy:       ?deploy=1&key=YOUR_KEY\n";
echo "Config cache: ?refresh-config=1&key=YOUR_KEY\n";
echo "DB check:      ?db-check=1&key=YOUR_KEY\n";
echo "Scheduler:    ?job=scheduler&key=YOUR_KEY\n";
