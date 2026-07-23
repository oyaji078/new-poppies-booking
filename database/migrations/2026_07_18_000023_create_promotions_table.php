<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->unique();      // null = automatic promotion
            $table->string('type', 20);                         // percentage | fixed
            $table->unsignedBigInteger('value');                // percent (1-100) or fixed rupiah
            $table->unsignedBigInteger('max_discount')->nullable(); // cap for percentage promos
            $table->boolean('is_automatic')->default(false);
            $table->unsignedSmallInteger('min_nights')->default(1);
            $table->unsignedBigInteger('min_transaction')->default(0);
            $table->unsignedInteger('usage_limit')->nullable(); // null = unlimited
            $table->unsignedInteger('used_count')->default(0);
            $table->date('booking_start')->nullable();          // booking-date window
            $table->date('booking_end')->nullable();
            $table->date('stay_start')->nullable();             // stay-date window
            $table->date('stay_end')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index('code');
        });

        Schema::create('promotion_room_type', function (Blueprint $table) {
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->primary(['promotion_id', 'room_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_room_type');
        Schema::dropIfExists('promotions');
    }
};
