<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$storagePath = '/tmp/laravel-storage';

foreach ([
    $storagePath.'/app',
    $storagePath.'/framework/cache/data',
    $storagePath.'/framework/sessions',
    $storagePath.'/framework/views',
    $storagePath.'/logs',
] as $directory) {
    if (! is_dir($directory)) {
        mkdir($directory, 0775, true);
    }
}

/** @var \Illuminate\Foundation\Application $app */
$app = require __DIR__.'/../bootstrap/app.php';
$app->useStoragePath($storagePath);

$app->handleRequest(Request::capture());
