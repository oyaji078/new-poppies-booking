<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

register_shutdown_function(function (): void {
    if ($error = error_get_last()) {
        error_log('Laravel Vercel shutdown: '.$error['message'].' in '.$error['file'].':'.$error['line']);
    }
});

require __DIR__.'/../vendor/autoload.php';

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

try {
    /** @var Application $app */
    $app = require __DIR__.'/../bootstrap/app.php';
    $app->useStoragePath($storagePath);

    $app->handleRequest(Request::capture());
} catch (Throwable $exception) {
    error_log($exception::class.': '.$exception->getMessage().PHP_EOL.$exception->getTraceAsString());
    http_response_code(500);

    if (filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOL)) {
        header('Content-Type: text/plain; charset=UTF-8');
        echo $exception::class.': '.$exception->getMessage().PHP_EOL.$exception->getTraceAsString();
    }
}
