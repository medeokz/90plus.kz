<?php

/**
 * Test: is document root = public/ ?
 * Open: https://YOUR-DOMAIN/plesk-ping.php
 * Expected text: OK-public
 */

header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__);
@mkdir($root.'/storage/logs', 0775, true);
file_put_contents(
    $root.'/storage/logs/ping.log',
    date('c').' ping from public/ docroot'.PHP_EOL,
    FILE_APPEND
);

echo 'OK-public root='.$root;
