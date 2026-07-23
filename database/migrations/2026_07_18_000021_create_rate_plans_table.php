<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-room-type, per-date price override (used by the inventory calendar to
        // set a specific nightly price on a given date). Absent = fall back to base
        // price + weekend/seasonal adjustments computed in PricingService.
        Schema::create('rate_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->date('rate_date');
            $table->unsignedBigInteger('price'); // integer rupiah, overrides base for this date
            $table->timestamps();

            $table->unique(['room_type_id', 'rate_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_plans');
    }
};
