<?php

use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'staff' => EnsureUserIsStaff::class,
            'superadmin' => EnsureUserIsSuperAdmin::class,
        ]);

        // The DOKU notification webhook is server-to-server and carries no CSRF
        // token; it is authenticated by signature instead (see DokuNotificationVerifier).
        $middleware->validateCsrfTokens(except: [
            'webhook/doku/notifications',
        ]);

        // Behind a tunnel/reverse proxy (Cloudflare, ngrok) the TLS terminates at
        // the proxy, so Laravel only sees http unless it trusts the forwarded
        // headers. Without this, generated URLs and the payment callback would be
        // http even though the public address is https.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
