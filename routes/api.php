<?php

use App\Http\Controllers\Api\DokuNotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| The DOKU notification webhook is server-to-server. It is CSRF-exempt (see
| bootstrap/app.php) and authenticated by DOKU signature verification instead.
| Rate limited to blunt any attempt to brute-force signatures.
|
*/

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::post('/payments/doku/notifications', DokuNotificationController::class)
    ->middleware('throttle:120,1')
    ->name('payments.doku.notifications');
