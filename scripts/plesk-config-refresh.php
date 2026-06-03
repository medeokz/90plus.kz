<?php

declare(strict_types=1);

/**
 * Обновить config:cache без SSH и без полного deploy.
 */
return function (string $root): int {
    $logFile = $root.'/storage/logs/config-refresh.log';

    $log = static function (string $message) use ($logFile): void {
        $line = date('Y-m-d H:i:s').' '.$message.PHP_EOL;
        @mkdir(dirname($logFile), 0775, true);
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        echo $line;
    };

    $providedKey = $_GET['key'] ?? '';
    $verify = require __DIR__.'/plesk-verify-key.php';
    $secret = $verify($root);

    if ($secret === null || $secret === '' || ! hash_equals($secret, (string) $providedKey)) {
        $log('ERROR: invalid key');
        if (PHP_SAPI !== 'cli') {
            http_response_code(403);
        }

        return 1;
    }

    if (! is_file($root.'/vendor/autoload.php')) {
        $log('ERROR: vendor/ missing');

        return 1;
    }

    require $root.'/vendor/autoload.php';

    $app = require $root.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    @unlink($root.'/bootstrap/cache/config.php');

    foreach (['config:clear', 'config:cache'] as $command) {
        try {
            $exit = $kernel->call($command);
            $log("OK: artisan {$command} (exit {$exit})");
        } catch (Throwable $e) {
            $log("ERROR: {$command} — ".$e->getMessage());

            return 1;
        }
    }

    $user = (string) config('database.connections.mysql.username');
    $db = (string) config('database.connections.mysql.database');
    $host = (string) config('database.connections.mysql.host');
    $log('--- config refresh done ---');
    $log("Active config: host={$host} database={$db} username={$user}");

    if ($user === '' || $user === 'root') {
        $log('WARNING: username still empty/root — check .env DB_USERNAME on server');

        return 1;
    }

    return 0;
};
