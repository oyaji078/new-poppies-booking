<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->date('stay_from');   // denormalised from the booking for overlap checks
            $table->date('stay_to');     // exclusive (checkout date)
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->timestamps();

            $table->index(['room_id', 'stay_from', 'stay_to']);
            $table->index('booking_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_assignments');
    }
};
