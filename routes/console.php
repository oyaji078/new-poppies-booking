<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
| Booking holds must be released promptly, so the expiry sweep runs every
| minute. withoutOverlapping stops a slow run from stacking.
*/
Schedule::command('bookings:expire-holds')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
