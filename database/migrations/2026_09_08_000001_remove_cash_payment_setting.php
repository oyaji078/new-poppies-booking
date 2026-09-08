<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pay-at-hotel was removed, so the flag that used to switch it on is dead data.
 * The settings screen builds its fields from a hardcoded map, not from these
 * rows, so the row was invisible — but leaving it invites someone to "re-enable"
 * a feature that no longer has any code behind it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->where('key', 'cash_payment_enabled')->delete();
    }

    public function down(): void
    {
        // Nothing to restore: the feature this flag controlled no longer exists.
    }
};
