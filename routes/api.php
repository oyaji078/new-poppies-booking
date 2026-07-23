<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Vercel reserves the /api prefix before Laravel routing. Keep critical
| server-to-server endpoints, including DOKU notifications, under /webhook.
|
*/

Route::get('/health', fn () => response()->json(['status' => 'ok']));
