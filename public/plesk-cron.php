<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__);
$job = $_GET['job'] ?? ($_SERVER['argv'][1] ?? '');
$key = $_GET['key'] ?? ($_SERVER['argv'][2] ?? '');

$run = require $root.'/scripts/plesk-artisan-runner.php';
[$code] = $run($root, (string) $job, (string) $key);
exit($code);
