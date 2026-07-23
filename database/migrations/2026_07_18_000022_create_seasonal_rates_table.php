<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A seasonal adjustment applies a percentage uplift/discount across a date
        // range, optionally scoped to a room type (null = all types).
        Schema::create('seasonal_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('adjustment_percent')->default(0); // e.g. +25 for high season
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seasonal_rates');
    }
};
