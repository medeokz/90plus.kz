<?php

declare(strict_types=1);

/**
 * Run whitelisted artisan commands from Plesk cron (no proc_open / no bash).
 *
 * @return array{0: int, 1: string} exit code and job id
 */
return function (string $root, string $job, string $providedKey): array {
    $logFile = $root.'/storage/logs/cron-'.preg_replace('/[^a-z0-9_-]+/i', '_', $job).'.log';

    $log = static function (string $message) use ($logFile): void {
        $line = date('Y-m-d H:i:s').' '.$message.PHP_EOL;
        @mkdir(dirname($logFile), 0775, true);
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        echo $line;
    };

    /** @var array<string, array{0: string, 1: array<string, mixed>}> */
    $jobs = [
        'articles' => ['articles:fetch-hourly', []],
        'standings' => ['standings:fetch', []],
        'world-cup' => ['world-cup:sync', []],
        'premier-liga' => ['premier-liga:sync', []],
        'fixtures-live' => ['fixtures:sync', ['--live' => true]],
        'fixtures-tracked' => ['fixtures:sync', ['--tracked' => true]],
        'transfers' => ['transfers:sync', []],
        'clubs-daily' => ['clubs:sync-daily', ['--batch' => 15]],
    ];

    if (! isset($jobs[$job])) {
        $log('ERROR: unknown job "'.$job.'". Allowed: '.implode(', ', array_keys($jobs)));

        return [1, $job];
    }

    if (! is_file($root.'/.env')) {
        $log('ERROR: .env missing');

        return [1, $job];
    }

    $secret = null;
    foreach (file($root.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if (str_starts_with($line, 'DEPLOY_KEY=') || str_starts_with($line, 'CRON_KEY=')) {
            $secret = trim(substr($line, strpos($line, '=') + 1), " \t\"'");
            break;
        }
    }

    if ($secret === null || $secret === '' || ! hash_equals($secret, $providedKey)) {
        $log('ERROR: invalid key (set DEPLOY_KEY or CRON_KEY in .env)');
        if (PHP_SAPI !== 'cli') {
            http_response_code(403);
        }

        return [1, $job];
    }

    if (! is_file($root.'/vendor/autoload.php')) {
        $log('ERROR: vendor/ missing');

        return [1, $job];
    }

    [$command, $args] = $jobs[$job];
    $log("--- job start: {$job} → artisan {$command} ---");

    require $root.'/vendor/autoload.php';

    $app = require $root.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    try {
        $exit = $kernel->call($command, $args);
        $log("OK: artisan {$command} (exit {$exit})");

        return [$exit === 0 ? 0 : 1, $job];
    } catch (Throwable $e) {
        $log('ERROR: '.$e->getMessage());

        return [1, $job];
    }
};
