<?php

declare(strict_types=1);

/**
 * @return string|null Secret from DEPLOY_KEY or CRON_KEY
 */
return function (string $root): ?string {
    if (! is_file($root.'/.env')) {
        return null;
    }

    foreach (file($root.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if (str_starts_with($line, 'DEPLOY_KEY=') || str_starts_with($line, 'CRON_KEY=')) {
            return trim(substr($line, strpos($line, '=') + 1), " \t\"'");
        }
    }

    return null;
};
