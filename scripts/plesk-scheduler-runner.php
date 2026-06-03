<?php

declare(strict_types=1);

/**
 * One Plesk hit = at most ONE parser (avoids nginx 504).
 * Response is sent immediately via fastcgi_finish_request when available.
 *
 * @return int exit code
 */
return function (string $root, string $providedKey): int {
    $logFile = $root.'/storage/logs/cron-scheduler.log';
    $stateFile = $root.'/storage/app/plesk-scheduler-state.json';

    $log = static function (string $message) use ($logFile): void {
        $line = date('Y-m-d H:i:s').' '.$message.PHP_EOL;
        @mkdir(dirname($logFile), 0775, true);
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        echo $line;
    };

    $verify = require __DIR__.'/plesk-verify-key.php';
    $secret = $verify($root);

    if ($secret === null || $secret === '' || ! hash_equals($secret, $providedKey)) {
        $log('ERROR: invalid key (set DEPLOY_KEY or CRON_KEY in .env)');
        if (PHP_SAPI !== 'cli') {
            http_response_code(403);
        }

        return 1;
    }

    /** @var array<string, array{type: string, seconds?: int, at?: string, heavy?: bool}> */
    $schedule = [
        'premier-liga' => ['type' => 'interval', 'seconds' => 300],
        'fixtures-tracked' => ['type' => 'interval', 'seconds' => 900],
        'world-cup' => ['type' => 'interval', 'seconds' => 1800],
        'standings' => ['type' => 'interval', 'seconds' => 1800],
        'articles' => ['type' => 'interval', 'seconds' => 3600],
        'clubs-daily' => ['type' => 'daily', 'at' => '04:00', 'heavy' => true],
        'transfers' => ['type' => 'daily', 'at' => '06:00'],
    ];

    @mkdir(dirname($stateFile), 0775, true);

    $lockFile = $root.'/storage/app/plesk-scheduler.lock';
    $lockFp = fopen($lockFile, 'c');
    if ($lockFp === false || ! flock($lockFp, LOCK_EX | LOCK_NB)) {
        echo "scheduler: skip (already running)\n";

        return 0;
    }

    $saveState = static function (array $state) use ($stateFile): void {
        file_put_contents(
            $stateFile,
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    };

    $raw = is_file($stateFile) ? file_get_contents($stateFile) : false;
    /** @var array<string, int|string> $state */
    $state = $raw !== false && $raw !== '' ? (json_decode($raw, true) ?: []) : [];

    $now = time();
    /** @var array<string, int> $due overdue seconds (higher = more urgent) */
    $due = [];

    foreach ($schedule as $jobId => $cfg) {
        if ($cfg['type'] === 'interval') {
            $last = (int) ($state[$jobId] ?? 0);
            $overdue = $now - $last - $cfg['seconds'];
            if ($overdue >= 0) {
                $due[$jobId] = $overdue;
            }
        } elseif ($cfg['type'] === 'daily') {
            $today = date('Y-m-d');
            if (($state[$jobId.'_date'] ?? '') === $today) {
                continue;
            }
            [$h, $m] = array_map('intval', explode(':', $cfg['at']));
            $nowMinutes = (int) date('G') * 60 + (int) date('i');
            $targetMinutes = $h * 60 + $m;
            if ($nowMinutes >= $targetMinutes) {
                $due[$jobId] = 100000 + ($nowMinutes - $targetMinutes);
            }
        }
    }

    if ($due === []) {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        echo "scheduler: nothing due\n";

        return 0;
    }

    arsort($due);
    $jobId = array_key_first($due);

    echo "scheduler: starting {$jobId}\n";

    if (PHP_SAPI !== 'cli') {
        @ini_set('max_execution_time', '0');
        ignore_user_abort(true);

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            header('Connection: close');
            header('Content-Length: '.strlen("scheduler: starting {$jobId}\n"));
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }
    }

    $log("--- tick: {$jobId} ---");
    $runJob = require __DIR__.'/plesk-artisan-runner.php';
    [$code] = $runJob($root, $jobId, $providedKey);

    if ($code === 0) {
        if ($schedule[$jobId]['type'] === 'daily') {
            $state[$jobId.'_date'] = date('Y-m-d');
        } else {
            $state[$jobId] = $now;
        }
        $saveState($state);
        $log("OK: finished {$jobId}");
    } else {
        $log("WARN: {$jobId} failed (exit {$code})");
    }

    flock($lockFp, LOCK_UN);
    fclose($lockFp);

    return $code === 0 ? 0 : 1;
};
