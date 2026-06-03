<?php

/**
 * Test: is document root = httpdocs/ (project root)?
 * Open: https://YOUR-DOMAIN/plesk-ping.php
 * Expected text: OK-root
 */

header('Content-Type: text/plain; charset=utf-8');

$root = __DIR__;
@mkdir($root.'/storage/logs', 0775, true);
file_put_contents(
    $root.'/storage/logs/ping.log',
    date('c').' ping from httpdocs docroot'.PHP_EOL,
    FILE_APPEND
);

echo 'OK-root root='.$root;
