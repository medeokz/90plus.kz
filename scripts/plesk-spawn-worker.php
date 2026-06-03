<?php

declare(strict_types=1);

/**
 * Фоновый HTTP-запрос к ?job=worker (Plesk cron не ждёт его завершения).
 */
return function (string $root, string $key): bool {
    $readEnv = require __DIR__.'/plesk-env.php';
    $env = $readEnv($root);

    $base = rtrim($env['APP_URL'] ?? '', '/');
    if ($base === '') {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') {
            return false;
        }
        $https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $base = ($https ? 'https' : 'http').'://'.$host;
    }

    $url = $base.'/plesk-task.php?job=worker&key='.rawurlencode($key);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => 3000,
            CURLOPT_CONNECTTIMEOUT_MS => 2000,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        @curl_exec($ch);
        $err = curl_errno($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($err === 0 || $err === 28) && ($http === 0 || $http < 500);
    }

    if (! ini_get('allow_url_fopen')) {
        return false;
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 3,
            'ignore_errors' => true,
        ],
    ]);
    @file_get_contents($url, false, $ctx);

    return true;
};
