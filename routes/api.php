<?php

use App\Http\Controllers\Api\DokuNotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| The legacy DOKU endpoint remains active because existing DOKU dashboard
| registrations may continue posting to it. The primary endpoint is the
| /webhook route in routes/web.php.
|
*/

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::post('/payments/doku/notifications', DokuNotificationController::class)
    ->middleware('throttle:120,1')
    ->name('payments.doku.notifications.legacy');
