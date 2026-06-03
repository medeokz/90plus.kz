<?php

declare(strict_types=1);

/**
 * Диагностика БД без полного bootstrap Laravel.
 * GET ?db-check=1&key=DEPLOY_KEY
 */
return function (string $root): int {
    header('Content-Type: text/plain; charset=utf-8');

    $providedKey = (string) ($_GET['key'] ?? '');
    $verify = require __DIR__.'/plesk-verify-key.php';
    $secret = $verify($root);

    if ($secret === null || $secret === '' || ! hash_equals($secret, $providedKey)) {
        http_response_code(403);
        echo "Forbidden\n";

        return 1;
    }

    $envPath = $root.'/.env';
    echo "=== DB diagnostic ===\n";
    echo 'root: '.$root."\n";
    echo '.env exists: '.(is_file($envPath) ? 'yes' : 'NO — create from .env.production.example'."\n";

    $envVars = [];
    if (is_file($envPath)) {
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (preg_match('/^(DB_[A-Z_]+)=(.*)$/', $line, $m)) {
                $envVars[$m[1]] = trim($m[2], " \t\"'");
            }
        }
    }

    foreach (['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $k) {
        $v = $envVars[$k] ?? '(not set)';
        if ($k === 'DB_PASSWORD' && $v !== '(not set)') {
            $v = $v === '' ? '(empty!)' : '(set, hidden)';
        }
        echo "{$k}={$v}\n";
    }

    $configCache = $root.'/bootstrap/cache/config.php';
    echo "\nconfig.php cached: ".(is_file($configCache) ? 'YES (Laravel uses this!)' : 'no'."\n";
    if (is_file($configCache)) {
        echo "→ Delete bootstrap/cache/config.php and refresh-config, or fix .env and run refresh-config again.\n";
    }

    if (($envVars['DB_USERNAME'] ?? '') === '' || ($envVars['DB_USERNAME'] ?? '') === 'root') {
        echo "\nPROBLEM: DB_USERNAME empty or root. In config/database.php fallback is 'root' if env is empty.\n";
        echo "Fix: set DB_USERNAME and DB_PASSWORD in .env (Plesk database user).\n";
    }

    return 0;
};
