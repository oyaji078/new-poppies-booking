<?php

namespace App\Providers;

use App\Services\Doku\DokuEnvironmentService;
use App\Services\Settings\SettingService;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Resolves the stored DOKU environment into the flat config keys the payment
 * services read, on every request and console command.
 *
 * Deliberately fail-safe: if the settings table cannot be read (fresh install,
 * mid-migration, database down) the config defaults from `.env` stay in force
 * rather than the app booting with no gateway configuration at all.
 */
class DokuServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            $this->app->make(DokuEnvironmentService::class)->apply();

            // Payment methods chosen by a super admin (if any) override the .env
            // default. Stored as a comma list; empty string means "not set".
            $stored = $this->app->make(SettingService::class)->get('doku_payment_methods');
            if (is_string($stored) && $stored !== '') {
                config(['doku.payment_method_types' => array_values(array_filter(
                    array_map('trim', explode(',', $stored))
                ))]);
            }
        } catch (Throwable $e) {
            // Keep the .env defaults; DokuCheck surfaces any real problem.
        }
    }
}
