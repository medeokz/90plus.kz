<?php

declare(strict_types=1);

/**
 * @return array<string, string>
 */
return function (string $root): array {
    $vars = [];
    $path = $root.'/.env';

    if (! is_file($path)) {
        return $vars;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (! str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $vars[trim($k)] = trim($v, " \t\"'");
    }

    return $vars;
};
