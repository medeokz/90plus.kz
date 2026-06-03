<?php

declare(strict_types=1);

/**
 * @return string Directory that contains artisan and .env
 */
return function (string $startDir): string {
    $dir = realpath($startDir) ?: $startDir;

    for ($i = 0; $i < 4; $i++) {
        if (is_file($dir.'/artisan')) {
            return $dir;
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }

    throw new RuntimeException('Laravel root not found (no artisan) from '.$startDir);
};
