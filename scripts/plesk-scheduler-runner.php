<?php

declare(strict_types=1);

/**
 * One Plesk URL (every 2 min) runs all parsers when they are due.
 *
 * Intervals match routes/console.php (except fixtures-live — too heavy for shared hosting).
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

    /** @var array<string, array{type: string, seconds?: int, at?: string}> */
    $schedule = [
        'premier-liga' => ['type' => 'interval', 'seconds' => 120],
        'fixtures-tracked' => ['type' => 'interval', 'seconds' => 300],
        'world-cup' => ['type' => 'interval', 'seconds' => 900],
        'standings' => ['type' => 'interval', 'seconds' => 1800],
        'articles' => ['type' => 'interval', 'seconds' => 3600],
        'clubs-daily' => ['type' => 'daily', 'at' => '04:00'],
        'transfers' => ['type' => 'daily', 'at' => '06:00'],
    ];

    @mkdir(dirname($stateFile), 0775, true);

    $lockFile = $root.'/storage/app/plesk-scheduler.lock';
    $lockFp = fopen($lockFile, 'c');
    if ($lockFp === false || ! flock($lockFp, LOCK_EX | LOCK_NB)) {
        $log('OK: previous scheduler tick still running');
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
    $due = [];

    foreach ($schedule as $jobId => $cfg) {
        if ($cfg['type'] === 'interval') {
            $last = (int) ($state[$jobId] ?? 0);
            if ($now - $last >= $cfg['seconds']) {
                $due[$jobId] = $now - $last;
            }
        } elseif ($cfg['type'] === 'daily') {
            $today = date('Y-m-d');
            $doneKey = $jobId.'_date';
            if (($state[$doneKey] ?? '') === $today) {
                continue;
            }
            [$h, $m] = array_map('intval', explode(':', $cfg['at']));
            $nowMinutes = (int) date('G') * 60 + (int) date('i');
            $targetMinutes = $h * 60 + $m;
            if ($nowMinutes >= $targetMinutes) {
                $due[$jobId] = $nowMinutes - $targetMinutes;
            }
        }
    }

    if ($due === []) {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        $log('OK: nothing due');
        echo "scheduler: nothing due at ".date('c')."\n";

        return 0;
    }

    arsort($due);
    $runJob = require __DIR__.'/plesk-artisan-runner.php';
    $failed = false;

    foreach (array_keys($due) as $jobId) {
        $log("--- running due job: {$jobId} ---");
        [$code] = $runJob($root, $jobId, $providedKey);

        if ($code !== 0) {
            $failed = true;
            $log("WARN: job {$jobId} failed, will retry next tick");

            continue;
        }

        if ($schedule[$jobId]['type'] === 'daily') {
            $state[$jobId.'_date'] = date('Y-m-d');
        } else {
            $state[$jobId] = $now;
        }

        $saveState($state);
    }

    flock($lockFp, LOCK_UN);
    fclose($lockFp);

    $log('--- scheduler tick done ---');

    return $failed ? 1 : 0;
};
