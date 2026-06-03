<?php

declare(strict_types=1);

/**
 * Фоновый HTTP-запрос к ?job=worker (Plesk cron не ждёт его завершения).
 */
return function (string $root, string $key): bool {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') {
        return false;
    }

    $https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $url = $scheme.'://'.$host.'/plesk-task.php?job=worker&key='.rawurlencode($key);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => 2000,
            CURLOPT_CONNECTTIMEOUT_MS => 1500,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        @curl_exec($ch);
        $err = curl_errno($ch);
        curl_close($ch);

        return $err === 0 || $err === 28; // timeout OK — worker runs in other request
    }

    if (! ini_get('allow_url_fopen')) {
        return false;
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 2,
            'ignore_errors' => true,
        ],
    ]);
    @file_get_contents($url, false, $ctx);

    return true;
};
