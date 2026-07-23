<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| NOTE: on serverless hosts such as Vercel the /api/* path is reserved by the
| platform's own function routing and never reaches Laravel, so nothing critical
| may live here. The DOKU notification webhook therefore lives in routes/web.php
| at /webhook/doku/notifications, and the health check is mirrored there at
| /health so it works everywhere.
|
*/

Route::get('/health', fn () => response()->json(['status' => 'ok']));
