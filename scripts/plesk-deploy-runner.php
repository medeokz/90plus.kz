<?php

declare(strict_types=1);

/**
 * @param  string  $root  Laravel project root (folder with artisan)
 */
return function (string $root): int {
    $logFile = $root.'/storage/logs/cron-deploy.log';

    $log = static function (string $message) use ($logFile): void {
        $line = date('Y-m-d H:i:s').' '.$message.PHP_EOL;
        @mkdir(dirname($logFile), 0775, true);
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        echo $line;
    };

    $log('--- deploy start ---');
    $log('root: '.$root);

    if (! is_file($root.'/.env')) {
        $log('ERROR: .env missing at '.$root);

        return 1;
    }

    $deployKey = null;
    foreach (file($root.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), 'DEPLOY_KEY=')) {
            $deployKey = trim(substr($line, strlen('DEPLOY_KEY=')), " \t\"'");
            break;
        }
    }

    $providedKey = $_GET['key'] ?? ($_SERVER['argv'][1] ?? '');
    if ($deployKey === null || $deployKey === '' || ! hash_equals($deployKey, (string) $providedKey)) {
        $log('ERROR: invalid DEPLOY_KEY (set DEPLOY_KEY=... in .env)');
        if (PHP_SAPI !== 'cli') {
            http_response_code(403);
        }

        return 1;
    }

    if (! is_file($root.'/vendor/autoload.php')) {
        $log('ERROR: vendor/ missing — run composer install --no-scripts');

        return 1;
    }

    require $root.'/vendor/autoload.php';

    $app = require $root.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    foreach ([
        ['migrate', ['--force' => true]],
        ['route:clear', []],
        ['config:cache', []],
        ['view:cache', []],
    ] as [$command, $args]) {
        try {
            $exit = $kernel->call($command, $args);
            $log("OK: artisan {$command} (exit {$exit})");
        } catch (Throwable $e) {
            $log("ERROR: {$command} — ".$e->getMessage());

            return 1;
        }
    }

    $log('--- deploy finished OK ---');

    return 0;
};
